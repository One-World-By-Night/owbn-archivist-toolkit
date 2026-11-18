<?php
/**
 * Global workflow notifications — every OAT domain EXCEPT chronicle reports,
 * which keep their own tailored emails (OAT_Chronicle_Report_Notify). Skipping
 * chronicle_actions here avoids double-sending.
 *
 * Targets (per office rule):
 *   approved (terminal)  -> the originator ("approved and recorded"), once.
 *   denied   (terminal)  -> the originator ("not accepted"), with the reason.
 *   changes requested    -> whoever it went back to, i.e. the pending assignees
 *                           at the new current step (one level back), with the
 *                           note; falls back to the originator.
 *   reaches a step       -> every person assigned to that step, in every domain
 *                           chronicle reports included, with a link that signs
 *                           them in and opens the request; never the person
 *                           who moved it there.
 *
 * Recipients are resolved to a real person's WordPress email; the system
 * web@owbn.net account and drafts (originator id <= 1) are never emailed.
 */

defined( 'ABSPATH' ) || exit;

class OAT_Workflow_Notify {

	public static function init() {
		add_action( 'oat_entry_approved', array( __CLASS__, 'on_approved' ), 30, 3 );
		add_action( 'oat_entry_denied', array( __CLASS__, 'on_denied' ), 30, 3 );
		add_action( 'oat_entry_changes_requested', array( __CLASS__, 'on_changes_requested' ), 30, 3 );
		add_action( 'oat_entry_step_assigned', array( __CLASS__, 'on_step_assigned' ), 30, 3 );
	}

	/**
	 * A request reached a step: email each person assigned to it, with a link to the request.
	 *
	 * @param int    $entry_id Request id.
	 * @param string $step_id  Step the request is now at.
	 * @param int[]  $user_ids People assigned to the step.
	 * @return void
	 */
	public static function on_step_assigned( $entry_id, $step_id, $user_ids ) {
		if ( ! class_exists( 'OAT_Entry' ) ) {
			return;
		}
		$entry = OAT_Entry::find( (int) $entry_id );
		if ( ! $entry || ( class_exists( 'OAT_Constants' ) && OAT_Constants::is_terminal_status( $entry->status ) ) ) {
			return;
		}

		$actor   = get_current_user_id();
		$targets = array();
		foreach ( (array) $user_ids as $uid ) {
			$uid = (int) $uid;
			if ( $uid <= 1 || $uid === $actor ) {
				continue;
			}
			$u = get_user_by( 'id', $uid );
			if ( ! $u ) {
				continue;
			}
			$position = class_exists( 'OAT_Position_Mail' ) ? OAT_Position_Mail::for_assignee( $u, $entry, $step_id ) : null;
			if ( $position ) {
				$targets[ strtolower( $position['email'] ) ] = $position;
			} elseif ( is_email( $u->user_email ) && 'web@owbn.net' !== strtolower( $u->user_email ) ) {
				$targets[ strtolower( $u->user_email ) ] = array( 'email' => $u->user_email, 'label' => '' );
			}
		}
		if ( empty( $targets ) ) {
			return;
		}

		$label = self::label( $entry );
		foreach ( $targets as $target ) {
			$subject = '' === $target['label']
				? sprintf( '[OWbN] %s #%d is waiting for you', $label, (int) $entry->id )
				: sprintf( '[OWbN] %s: %s #%d is waiting for you', $target['label'], $label, (int) $entry->id );

			$lines = array();
			if ( '' !== $target['label'] ) {
				$lines[] = sprintf( 'You are receiving this for the position: %s.', $target['label'] );
				$lines[] = '';
			}
			$lines[] = sprintf( 'A %s (#%d) is now at %s and waiting on you.', $label, (int) $entry->id, self::step_label( $entry, $step_id ) );
			$filer   = self::filer_name( $entry );
			if ( '' !== $filer ) {
				$lines[] = '';
				$lines[] = 'Filed by: ' . $filer;
			}
			$lines[] = '';
			$lines[] = 'Open it here:';
			$lines[] = self::entry_link( $entry );
			$lines[] = '';
			$lines[] = 'This is an automated notification from the OWbN Archivist Toolkit.';

			OAT_Notify_Mail::send( $target['email'], $subject, implode( "\n", $lines ), (int) $entry->id );
		}
	}

	/**
	 * The step's name from its configuration (e.g. "Staff Review"), else its id in words.
	 *
	 * @param object $entry
	 * @param string $step_id
	 * @return string
	 */
	private static function step_label( $entry, $step_id ) {
		$config = class_exists( 'OAT_Workflow_Engine' ) ? OAT_Workflow_Engine::get_step_config( $entry, $step_id ) : null;
		if ( is_array( $config ) && ! empty( $config['label'] ) ) {
			return (string) $config['label'];
		}
		return ucwords( str_replace( array( '_', '-' ), ' ', (string) $step_id ) );
	}

	/**
	 * The display name of the person who filed the request, or '' for a draft or the system account.
	 *
	 * @param object $entry
	 * @return string
	 */
	private static function filer_name( $entry ) {
		$u = ( (int) $entry->originator_id > 1 ) ? get_user_by( 'id', (int) $entry->originator_id ) : null;
		return $u ? (string) $u->display_name : '';
	}

	/**
	 * Address that signs the reader in through OWbN sign-on and opens the request on this site.
	 *
	 * @param object $entry
	 * @return string
	 */
	private static function entry_link( $entry ) {
		return home_url( '/?auth=sso&redirect_uri=' . rawurlencode( '/oat-entry/?oat_entry=' . (int) $entry->id ) );
	}

	/**
	 * Handle every domain except chronicle reports (their own notifier owns
	 * chronicle_actions). Requires the meta API for lookups.
	 *
	 * @param object|null $entry
	 * @return bool
	 */
	private static function eligible( $entry ) {
		return class_exists( 'OAT_Entry_Meta' ) && $entry && 'chronicle_actions' !== $entry->domain;
	}

	/**
	 * Terminal approval — confirm to the originator, once per entry.
	 */
	public static function on_approved( $entry_id, $user_id, $data ) {
		if ( ! class_exists( 'OAT_Entry' ) ) {
			return;
		}
		$entry = OAT_Entry::find( (int) $entry_id );
		if ( ! self::eligible( $entry ) ) {
			return;
		}
		// Once only — approve + record + auto-approve can all fire the hook.
		if ( OAT_Entry_Meta::get( (int) $entry->id, 'wf_approved_notified' ) ) {
			return;
		}
		$to = self::originator_address( $entry );
		if ( '' === $to ) {
			return;
		}

		$label   = self::label( $entry );
		$subject = sprintf( '[OWbN] %s approved', $label );

		$lines   = array();
		$lines[] = sprintf( 'Your %s (#%d) has been approved and recorded.', $label, (int) $entry->id );
		$lines[] = '';
		$lines[] = 'No further action is needed on your part.';
		$lines[] = '';
		$lines[] = 'This is an automated notification from the OWbN Archivist Toolkit.';

		if ( OAT_Notify_Mail::send( $to, $subject, implode( "\n", $lines ), (int) $entry->id ) ) {
			OAT_Entry_Meta::set( (int) $entry->id, 'wf_approved_notified', (string) time() );
		}
	}

	/**
	 * Terminal denial — tell the originator, with the reviewer's reason.
	 */
	public static function on_denied( $entry_id, $user_id, $data ) {
		if ( ! class_exists( 'OAT_Entry' ) ) {
			return;
		}
		$entry = OAT_Entry::find( (int) $entry_id );
		if ( ! self::eligible( $entry ) ) {
			return;
		}
		$to = self::originator_address( $entry );
		if ( '' === $to ) {
			return;
		}

		$label   = self::label( $entry );
		$subject = sprintf( '[OWbN] %s not accepted', $label );

		$lines   = array();
		$lines[] = sprintf( 'Your %s (#%d) was reviewed and was not accepted.', $label, (int) $entry->id );
		$lines[] = '';
		$lines[] = 'Reason given:';
		$lines[] = self::plain( isset( $data['note'] ) ? $data['note'] : '' );
		$lines[] = '';
		$lines[] = 'You may correct the issue and submit again.';
		$lines[] = '';
		$lines[] = 'This is an automated notification from the OWbN Archivist Toolkit.';

		OAT_Notify_Mail::send( $to, $subject, implode( "\n", $lines ), (int) $entry->id );
	}

	/**
	 * Changes requested — the entry moved back one level. Notify whoever it
	 * landed on (the pending assignees at the new current step), with the note.
	 */
	public static function on_changes_requested( $entry_id, $user_id, $data ) {
		if ( ! class_exists( 'OAT_Entry' ) ) {
			return;
		}
		$entry = OAT_Entry::find( (int) $entry_id );
		if ( ! self::eligible( $entry ) ) {
			return;
		}

		$emails = self::assignee_addresses( $entry, $entry->current_step );
		if ( empty( $emails ) ) {
			$fallback = self::originator_address( $entry );
			if ( '' === $fallback ) {
				return;
			}
			$emails = array( $fallback );
		}

		$label   = self::label( $entry );
		$subject = sprintf( '[OWbN] %s needs changes', $label );

		$lines   = array();
		$lines[] = sprintf( 'A %s (#%d) was returned for changes.', $label, (int) $entry->id );
		$lines[] = '';
		$lines[] = 'What needs attention:';
		$lines[] = self::plain( isset( $data['note'] ) ? $data['note'] : '' );
		$lines[] = '';
		$lines[] = 'Please update it and submit again.';
		$lines[] = '';
		$lines[] = 'This is an automated notification from the OWbN Archivist Toolkit.';
		$body    = implode( "\n", $lines );

		foreach ( $emails as $to ) {
			OAT_Notify_Mail::send( $to, $subject, $body, (int) $entry->id );
		}
	}

	/**
	 * The originator's email, or '' for a draft/system account/missing address.
	 *
	 * @param object $entry
	 * @return string
	 */
	private static function originator_address( $entry ) {
		$u  = ( (int) $entry->originator_id > 1 ) ? get_user_by( 'id', (int) $entry->originator_id ) : null;
		$to = ( $u && is_email( $u->user_email ) ) ? $u->user_email : '';
		return ( '' === $to || 'web@owbn.net' === strtolower( $to ) ) ? '' : $to;
	}

	/**
	 * Emails of the pending assignees at a step — real people only, deduped.
	 *
	 * @param object $entry
	 * @param string $step
	 * @return string[]
	 */
	private static function assignee_addresses( $entry, $step ) {
		$out = array();
		if ( ! class_exists( 'OAT_Assignee' ) ) {
			return $out;
		}
		foreach ( OAT_Assignee::for_entry_step( (int) $entry->id, $step ) as $a ) {
			if ( 'pending' !== $a->status ) {
				continue;
			}
			$u = ( (int) $a->user_id > 1 ) ? get_user_by( 'id', (int) $a->user_id ) : null;
			if ( ! $u ) {
				continue;
			}
			$position = class_exists( 'OAT_Position_Mail' ) ? OAT_Position_Mail::for_assignee( $u, $entry, $step ) : null;
			if ( $position ) {
				$out[ strtolower( $position['email'] ) ] = $position['email'];
			} elseif ( is_email( $u->user_email ) && 'web@owbn.net' !== strtolower( $u->user_email ) ) {
				$out[ strtolower( $u->user_email ) ] = $u->user_email;
			}
		}
		return array_values( $out );
	}

	/**
	 * Human label for the entry's domain (e.g. "Player Action").
	 *
	 * @param object $entry
	 * @return string
	 */
	private static function label( $entry ) {
		$domain = class_exists( 'OAT_Domain_Registry' ) ? OAT_Domain_Registry::get_php_domain( $entry->domain ) : null;
		if ( $domain && method_exists( $domain, 'get_label' ) ) {
			$l = (string) $domain->get_label();
			if ( '' !== $l ) {
				return $l;
			}
		}
		return ucwords( str_replace( array( '_', '-' ), ' ', (string) $entry->domain ) );
	}

	/**
	 * Plain-text a note; em dash when empty.
	 *
	 * @param string $html
	 * @return string
	 */
	private static function plain( $html ) {
		$t = trim( wp_strip_all_tags( (string) $html ) );
		return '' === $t ? '—' : $t;
	}
}
