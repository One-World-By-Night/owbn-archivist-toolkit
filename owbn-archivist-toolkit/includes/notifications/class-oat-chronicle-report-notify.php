<?php
/**
 * Chronicle Report → targeted Coordinator email.
 *
 * When a Chronicle Report (chronicle_actions / ca_reporting) is approved:
 *  1. Confirm receipt to the submitter ("received and logged") — always, once.
 *  2. Email the report's key details to every Coordinator office the submitter
 *     selected in the "Notify Coordinators" field. Recipients are resolved from
 *     AccessSchema (the office holders), so mail always reaches whoever currently
 *     holds the office. Nothing is sent to coordinators if none were selected.
 */

defined( 'ABSPATH' ) || exit;

class OAT_Chronicle_Report_Notify {

	public static function init() {
		add_action( 'oat_entry_approved', array( __CLASS__, 'on_approved' ), 25, 3 );
		add_action( 'oat_entry_denied', array( __CLASS__, 'on_denied' ), 25, 3 );
		add_action( 'oat_entry_changes_requested', array( __CLASS__, 'on_changes_requested' ), 25, 3 );
	}

	/**
	 * True when the entry is a Chronicle Report (chronicle_actions/ca_reporting)
	 * and the meta API is available.
	 *
	 * @param object|null $entry
	 * @return bool
	 */
	private static function is_ca_report( $entry ) {
		return class_exists( 'OAT_Entry_Meta' )
			&& $entry
			&& 'chronicle_actions' === $entry->domain
			&& ! empty( $entry->form_slug )
			&& 'ca_reporting' === $entry->form_slug;
	}

	/**
	 * Report was denied (terminal). Tell the originator, with the reviewer's
	 * reason, so they know it was not accepted and can correct + resubmit.
	 *
	 * @param int   $entry_id
	 * @param int   $user_id  Actor who denied.
	 * @param array $data     Includes 'note' (required by the deny action).
	 */
	public static function on_denied( $entry_id, $user_id, $data ) {
		if ( ! class_exists( 'OAT_Entry' ) ) {
			return;
		}
		$entry = OAT_Entry::find( (int) $entry_id );
		if ( ! self::is_ca_report( $entry ) ) {
			return;
		}
		$to = self::submitter_address( $entry );
		if ( '' === $to ) {
			return;
		}

		$chronicle = self::chronicle_label( $entry );
		$subject   = sprintf( '[OWbN] Chronicle Report not accepted — %s', $chronicle ? $chronicle : 'chronicle' );

		$lines   = array();
		$lines[] = 'Your Chronicle Report was reviewed by the Archivist team and was not accepted.';
		$lines[] = '';
		$lines[] = 'Chronicle: ' . ( $chronicle ? $chronicle : '—' );
		$lines[] = '';
		$lines[] = 'Reason given:';
		$lines[] = self::plain( isset( $data['note'] ) ? $data['note'] : '' );
		$lines[] = '';
		$lines[] = 'You may correct the issue and submit a new report.';
		$lines[] = '';
		$lines[] = 'This is an automated notification from the OWbN Archivist Toolkit.';

		wp_mail( $to, $subject, implode( "\n", $lines ) );
	}

	/**
	 * Changes were requested — the report moved back one level. Notify whoever
	 * it landed on (the new current step's assignees; for a Chronicle Report
	 * that is the originator at the submit step), with the reviewer's note.
	 *
	 * @param int   $entry_id
	 * @param int   $user_id  Actor who requested changes.
	 * @param array $data     Includes 'note'.
	 */
	public static function on_changes_requested( $entry_id, $user_id, $data ) {
		if ( ! class_exists( 'OAT_Entry' ) ) {
			return;
		}
		$entry = OAT_Entry::find( (int) $entry_id );
		if ( ! self::is_ca_report( $entry ) ) {
			return;
		}

		// Recipients = assignees now sitting at the (new) current step. Fall back
		// to the submitter if none resolve to a real address.
		$emails    = array();
		$assignees = OAT_Assignee::for_entry_step( (int) $entry->id, $entry->current_step );
		foreach ( $assignees as $a ) {
			if ( 'pending' !== $a->status ) {
				continue;
			}
			$u = ( (int) $a->user_id > 1 ) ? get_user_by( 'id', (int) $a->user_id ) : null;
			if ( $u && is_email( $u->user_email ) && 'web@owbn.net' !== strtolower( $u->user_email ) ) {
				$emails[ strtolower( $u->user_email ) ] = $u->user_email;
			}
		}
		if ( empty( $emails ) ) {
			$fallback = self::submitter_address( $entry );
			if ( '' === $fallback ) {
				return;
			}
			$emails[ strtolower( $fallback ) ] = $fallback;
		}

		$chronicle = self::chronicle_label( $entry );
		$subject   = sprintf( '[OWbN] Chronicle Report needs changes — %s', $chronicle ? $chronicle : 'chronicle' );

		$lines   = array();
		$lines[] = 'A Chronicle Report was returned for changes by the Archivist team.';
		$lines[] = '';
		$lines[] = 'Chronicle: ' . ( $chronicle ? $chronicle : '—' );
		$lines[] = '';
		$lines[] = 'What needs attention:';
		$lines[] = self::plain( isset( $data['note'] ) ? $data['note'] : '' );
		$lines[] = '';
		$lines[] = 'Please update the report and submit it again.';
		$lines[] = '';
		$lines[] = 'This is an automated notification from the OWbN Archivist Toolkit.';
		$body    = implode( "\n", $lines );

		foreach ( $emails as $to ) {
			wp_mail( $to, $subject, $body );
		}
	}

	/**
	 * The submitter's email: the address captured on the form, else the
	 * submitting WordPress account. Returns '' for a missing address, a draft,
	 * or the system web@owbn.net account.
	 *
	 * @param object $entry
	 * @return string
	 */
	private static function submitter_address( $entry ) {
		$to = trim( (string) OAT_Entry_Meta::get( (int) $entry->id, 'submitter_email' ) );
		if ( '' === $to || ! is_email( $to ) ) {
			$u  = ( (int) $entry->originator_id > 1 ) ? get_user_by( 'id', (int) $entry->originator_id ) : null;
			$to = ( $u && is_email( $u->user_email ) ) ? $u->user_email : '';
		}
		if ( '' === $to || 'web@owbn.net' === strtolower( $to ) ) {
			return '';
		}
		return $to;
	}

	/**
	 * Human label for the report's chronicle: the slug, else the chronicle_slug
	 * meta.
	 *
	 * @param object $entry
	 * @return string
	 */
	private static function chronicle_label( $entry ) {
		return ! empty( $entry->chronicle_slug )
			? $entry->chronicle_slug
			: (string) OAT_Entry_Meta::get( (int) $entry->id, 'chronicle_slug' );
	}

	/**
	 * @param int   $entry_id
	 * @param int   $user_id   Actor who approved.
	 * @param array $data
	 */
	public static function on_approved( $entry_id, $user_id, $data ) {
		if ( ! class_exists( 'OAT_Entry' ) || ! class_exists( 'OAT_Entry_Meta' ) ) {
			return;
		}

		$entry = OAT_Entry::find( (int) $entry_id );
		if ( ! $entry || 'chronicle_actions' !== $entry->domain ) {
			return;
		}
		if ( empty( $entry->form_slug ) || 'ca_reporting' !== $entry->form_slug ) {
			return;
		}

		// Confirm receipt to the submitter. Runs on every approved chronicle
		// report, independent of whether any Coordinator office was flagged
		// below — otherwise reports with no flagged coordinators (the common
		// case) would notify no one that they were logged.
		self::notify_submitter( $entry );

		$slugs = self::selected_slugs( OAT_Entry_Meta::get( (int) $entry->id, 'notify_coordinators' ) );
		if ( empty( $slugs ) ) {
			return; // none selected — nothing to notify
		}

		$emails = self::resolve_emails( $slugs );
		if ( empty( $emails ) ) {
			return;
		}

		$chronicle = ! empty( $entry->chronicle_slug ) ? $entry->chronicle_slug : (string) OAT_Entry_Meta::get( (int) $entry->id, 'chronicle_slug' );
		$submitter = (string) OAT_Entry_Meta::get( (int) $entry->id, 'submitter_name' );
		$dates     = (string) OAT_Entry_Meta::get( (int) $entry->id, 'game_dates' );
		$attention = (string) OAT_Entry_Meta::get( (int) $entry->id, 'coord_attention' );

		$subject = sprintf( '[OWbN] Chronicle Report needs your attention — %s', $chronicle ? $chronicle : 'chronicle' );

		$link = admin_url( 'admin.php?page=owc-oat-reports&report=chronicle_reports&chronicle=' . rawurlencode( (string) $chronicle ) );

		$lines   = array();
		$lines[] = 'A Chronicle Report was approved and flagged for your Coordinator office.';
		$lines[] = '';
		$lines[] = 'Chronicle:    ' . ( $chronicle ? $chronicle : '—' );
		$lines[] = 'Submitted by: ' . ( $submitter ? $submitter : '—' );
		$lines[] = 'Game dates:   ' . ( $dates ? $dates : '—' );
		$lines[] = '';
		$lines[] = 'Plots that may require Coordinator help or attention:';
		$lines[] = self::plain( $attention );
		$lines[] = '';
		$lines[] = 'View the full chronicle reports here (login required):';
		$lines[] = $link;
		$lines[] = '';
		$lines[] = 'This is an automated notification from the OWbN Archivist Toolkit.';
		$body    = implode( "\n", $lines );

		foreach ( $emails as $to ) {
			wp_mail( $to, $subject, $body );
		}
	}

	/**
	 * Email the submitter a "received and logged" confirmation once the report
	 * is approved. Sends at most once per entry (guarded by a meta flag), and
	 * only to a real person — never the system web@owbn.net account or a draft.
	 *
	 * @param object $entry
	 */
	private static function notify_submitter( $entry ) {
		// Idempotency: never confirm the same report twice (approve + record +
		// auto-approve can all fire oat_entry_approved for one entry).
		if ( OAT_Entry_Meta::get( (int) $entry->id, 'submitter_confirmed_at' ) ) {
			return;
		}

		$to = self::submitter_address( $entry );
		if ( '' === $to ) {
			return;
		}

		$chronicle = self::chronicle_label( $entry );
		$dates     = (string) OAT_Entry_Meta::get( (int) $entry->id, 'game_dates' );

		$subject = sprintf( '[OWbN] Chronicle Report received and logged — %s', $chronicle ? $chronicle : 'chronicle' );

		$lines   = array();
		$lines[] = 'Your Chronicle Report has been received and logged by the Archivist team.';
		$lines[] = '';
		$lines[] = 'Chronicle:  ' . ( $chronicle ? $chronicle : '—' );
		$lines[] = 'Game dates: ' . ( $dates ? $dates : '—' );
		$lines[] = '';
		$lines[] = 'No further action is needed on your part. If anything requires follow-up,';
		$lines[] = 'an Archivist or Coordinator will be in touch.';
		$lines[] = '';
		$lines[] = 'This is an automated notification from the OWbN Archivist Toolkit.';
		$body    = implode( "\n", $lines );

		if ( wp_mail( $to, $subject, $body ) ) {
			OAT_Entry_Meta::set( (int) $entry->id, 'submitter_confirmed_at', (string) time() );
		}
	}

	/**
	 * Normalise the stored field value (JSON array, comma string, or array) to
	 * a clean list of coordinator slugs.
	 *
	 * @return string[]
	 */
	private static function selected_slugs( $raw ) {
		if ( is_array( $raw ) ) {
			$arr = $raw;
		} elseif ( is_string( $raw ) && '' !== $raw ) {
			$decoded = json_decode( $raw, true );
			$arr     = is_array( $decoded ) ? $decoded : array_map( 'trim', explode( ',', $raw ) );
		} else {
			$arr = array();
		}

		$out = array();
		foreach ( (array) $arr as $s ) {
			$s = sanitize_title( (string) $s );
			if ( '' !== $s ) {
				$out[ $s ] = true; // dedupe
			}
		}
		return array_keys( $out );
	}

	/**
	 * Resolve selected office slugs to the email addresses of the current
	 * Coordinator + Sub-Coordinator holders via AccessSchema. Deduped.
	 *
	 * @return string[]
	 */
	private static function resolve_emails( array $slugs ) {
		$emails = array();
		if ( ! function_exists( 'owc_asc_get_users_by_role' ) ) {
			return $emails;
		}
		foreach ( $slugs as $slug ) {
			foreach ( array( 'coordinator', 'sub-coordinator' ) as $level ) {
				$users = owc_asc_get_users_by_role( 'oat', 'coordinator/' . $slug . '/' . $level );
				if ( is_wp_error( $users ) || ! is_array( $users ) ) {
					continue;
				}
				foreach ( $users as $u ) {
					$u = is_object( $u ) ? (array) $u : $u;
					$e = isset( $u['email'] ) ? trim( (string) $u['email'] ) : '';
					if ( $e && is_email( $e ) ) {
						$emails[ strtolower( $e ) ] = $e;
					}
				}
			}
		}
		return array_values( $emails );
	}

	private static function plain( $html ) {
		$t = trim( wp_strip_all_tags( (string) $html ) );
		return '' === $t ? '—' : $t;
	}
}
