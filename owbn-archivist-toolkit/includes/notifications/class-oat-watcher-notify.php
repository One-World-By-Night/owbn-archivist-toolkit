<?php
/**
 * Watcher emails. When a request moves to a new step, is sent back, or finishes, everyone watching it is told where
 * it now stands: one email per watcher per page load, sent at the end of it, describing the request as it ended up.
 * Left out: the person who acted, the system account, and anyone already emailed about that request during the same
 * page load (the filer's outcome emails, the people it now waits on).
 */

defined( 'ABSPATH' ) || exit;

class OAT_Watcher_Notify {

	/**
	 * Requests that changed during this page load: entry id => array( 'actor' => int, 'sent_back' => bool ).
	 *
	 * @var array
	 */
	private static $changed = array();

	public static function init() {
		add_action( 'oat_entry_step_assigned', array( __CLASS__, 'on_step' ), 40, 1 );
		add_action( 'oat_entry_approved', array( __CLASS__, 'on_outcome' ), 40, 2 );
		add_action( 'oat_entry_denied', array( __CLASS__, 'on_outcome' ), 40, 2 );
		add_action( 'oat_entry_changes_requested', array( __CLASS__, 'on_sent_back' ), 40, 2 );
		add_action( 'shutdown', array( __CLASS__, 'flush' ), 5 );
	}

	public static function on_step( $entry_id ) {
		self::note( (int) $entry_id, get_current_user_id(), false );
	}

	public static function on_outcome( $entry_id, $user_id = 0 ) {
		self::note( (int) $entry_id, (int) $user_id ?: get_current_user_id(), false );
	}

	public static function on_sent_back( $entry_id, $user_id = 0 ) {
		self::note( (int) $entry_id, (int) $user_id ?: get_current_user_id(), true );
	}

	/**
	 * Records that a request changed.
	 *
	 * @param int  $entry_id  Request.
	 * @param int  $actor     Who acted.
	 * @param bool $sent_back Whether it was sent back for changes.
	 */
	private static function note( $entry_id, $actor, $sent_back ) {
		if ( $entry_id <= 0 ) {
			return;
		}
		// While a group of requests is being moved together, the watchers hear once, about the group's first line.
		if ( class_exists( 'OAT_Batch' ) && OAT_Batch::muted() ) {
			$entry_id = OAT_Batch::lead_id( $entry_id );
		}
		if ( ! isset( self::$changed[ $entry_id ] ) ) {
			self::$changed[ $entry_id ] = array( 'actor' => (int) $actor, 'sent_back' => false );
		}
		if ( $sent_back ) {
			self::$changed[ $entry_id ]['sent_back'] = true;
		}
	}

	/**
	 * Sends the watcher emails for every request that changed during this page load.
	 *
	 * @return int Emails sent or queued.
	 */
	public static function flush() {
		$changed        = self::$changed;
		self::$changed  = array();
		$count          = 0;
		if ( ! $changed || ! class_exists( 'OAT_Entry' ) || ! class_exists( 'OAT_Watcher' ) || ! class_exists( 'OAT_Notify_Mail' ) ) {
			return 0;
		}
		foreach ( $changed as $entry_id => $info ) {
			$entry = OAT_Entry::find( (int) $entry_id );
			if ( ! $entry ) {
				continue;
			}
			$what = self::what_happened( $entry, ! empty( $info['sent_back'] ) );
			if ( '' === $what ) {
				continue;
			}
			$label   = self::label( $entry );
			/* translators: 1: kind of request, 2: its number, 3: what happened */
			$subject = sprintf( __( '[OWbN] %1$s #%2$d %3$s', 'owbn-archivist-toolkit' ), OAT_Request_Title::for_entry( $entry ), (int) $entry->id, $what );
			$lines   = array(
				/* translators: 1: kind of request, 2: its number, 3: what happened */
				sprintf( __( 'A %1$s you watch (#%2$d) %3$s.', 'owbn-archivist-toolkit' ), $label, (int) $entry->id, $what ),
				'',
				__( 'Open it here:', 'owbn-archivist-toolkit' ),
				home_url( '/?auth=sso&redirect_uri=' . rawurlencode( '/oat-entry/?oat_entry=' . (int) $entry->id ) ),
				'',
				__( 'You get this because you watch this request. To stop, open it and click Watching.', 'owbn-archivist-toolkit' ),
				'',
				OAT_Notify_Mail::FOOTER,
			);
			$body = implode( "\n", $lines );
			foreach ( (array) OAT_Watcher::user_ids_for_entry( (int) $entry->id ) as $uid ) {
				$uid = (int) $uid;
				if ( $uid <= 1 || $uid === (int) $info['actor'] ) {
					continue;
				}
				$user = get_user_by( 'id', $uid );
				if ( ! $user || ! is_email( $user->user_email ) || 'web@owbn.net' === strtolower( $user->user_email ) ) {
					continue;
				}
				if ( OAT_Notify_Mail::already_sent( (int) $entry->id, $user->user_email ) ) {
					continue;
				}
				if ( OAT_Notify_Mail::send( $user->user_email, $subject, $body, (int) $entry->id ) ) {
					$count++;
				}
			}
			OAT_Notify_Mail::forget( (int) $entry->id );
		}
		return $count;
	}

	/**
	 * Where the request stands now, in words ("was approved", "moved to Staff Review"), or '' for nothing to tell.
	 *
	 * @param object $entry     Request.
	 * @param bool   $sent_back Whether it was sent back during this page load.
	 * @return string
	 */
	private static function what_happened( $entry, $sent_back ) {
		switch ( (string) $entry->status ) {
			case 'approved':
			case 'auto_approved':
				return __( 'was approved', 'owbn-archivist-toolkit' );
			case 'denied':
			case 'auto_denied':
				return __( 'was not accepted', 'owbn-archivist-toolkit' );
			case 'cancelled':
				return __( 'was cancelled', 'owbn-archivist-toolkit' );
		}
		$step = self::step_label( $entry, (string) $entry->current_step );
		if ( '' === $step ) {
			return '';
		}
		/* translators: %s: the step it is at now */
		return sprintf( $sent_back ? __( 'was sent back and is now at %s', 'owbn-archivist-toolkit' ) : __( 'moved to %s', 'owbn-archivist-toolkit' ), $step );
	}

	private static function step_label( $entry, $step_id ) {
		if ( '' === $step_id ) {
			return '';
		}
		$config = class_exists( 'OAT_Workflow_Engine' ) ? OAT_Workflow_Engine::get_step_config( $entry, $step_id ) : null;
		if ( is_array( $config ) && ! empty( $config['label'] ) ) {
			return (string) $config['label'];
		}
		return ucwords( str_replace( array( '_', '-' ), ' ', $step_id ) );
	}

	private static function label( $entry ) {
		$domain = class_exists( 'OAT_Domain_Registry' ) ? OAT_Domain_Registry::get_php_domain( $entry->domain ) : null;
		if ( $domain && method_exists( $domain, 'get_label' ) && '' !== (string) $domain->get_label() ) {
			return (string) $domain->get_label();
		}
		return ucwords( str_replace( array( '_', '-' ), ' ', (string) $entry->domain ) );
	}
}
