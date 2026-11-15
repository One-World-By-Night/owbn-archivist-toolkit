<?php
/**
 * Every OAT email goes through send(). A member who chose one email a day (user meta oat_notify_mode = daily) has
 * the message queued instead, and the daily digest sends the queue as one email. An address that isn't a member's
 * is always sent at once. send() also remembers who was emailed about each request during this page load, so the
 * watcher emails can leave them out.
 */

defined( 'ABSPATH' ) || exit;

class OAT_Notify_Mail {

	const MODE_META  = 'oat_notify_mode';
	const QUEUE_META = 'oat_notify_queue';
	const CRON       = 'oat_notify_digest';
	const QUEUE_MAX  = 200;
	const FOOTER     = 'This is an automated notification from the OWbN Archivist Toolkit.';

	/**
	 * Addresses emailed per request during this page load: entry id => lowercase address => true.
	 *
	 * @var array
	 */
	private static $sent = array();

	public static function init() {
		add_action( self::CRON, array( __CLASS__, 'send_digests' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ) );
	}

	/**
	 * The daily digest runs once a day at 13:00 UTC.
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::CRON ) ) {
			$next = strtotime( gmdate( 'Y-m-d' ) . ' 13:00:00 UTC' );
			if ( $next <= time() ) {
				$next += DAY_IN_SECONDS;
			}
			wp_schedule_event( $next, 'daily', self::CRON );
		}
	}

	/**
	 * A member's choice: 'each' (an email per event, the default) or 'daily'.
	 *
	 * @param int $user_id Member.
	 * @return string
	 */
	public static function mode( $user_id ) {
		return 'daily' === get_user_meta( (int) $user_id, self::MODE_META, true ) ? 'daily' : 'each';
	}

	/**
	 * Saves a member's choice. Going back to 'each' sends what was queued at once.
	 *
	 * @param int    $user_id Member.
	 * @param string $mode    'each' or 'daily'.
	 * @return string The saved choice.
	 */
	public static function set_mode( $user_id, $mode ) {
		$mode = 'daily' === $mode ? 'daily' : 'each';
		update_user_meta( (int) $user_id, self::MODE_META, $mode );
		if ( 'each' === $mode ) {
			self::send_digest( (int) $user_id );
		}
		return $mode;
	}

	/**
	 * Sends an OAT email now, or queues it for a member who chose one email a day.
	 *
	 * @param string $to       Address.
	 * @param string $subject  Subject.
	 * @param string $body     Plain-text body.
	 * @param int    $entry_id The request it's about, 0 for none.
	 * @return bool Whether it was sent or queued.
	 */
	public static function send( $to, $subject, $body, $entry_id = 0 ) {
		$to = trim( (string) $to );
		if ( ! is_email( $to ) ) {
			return false;
		}
		if ( $entry_id ) {
			self::$sent[ (int) $entry_id ][ strtolower( $to ) ] = true;
		}
		$user = get_user_by( 'email', $to );
		if ( $user && 'daily' === self::mode( $user->ID ) ) {
			return self::queue( $user->ID, (string) $subject, (string) $body );
		}
		return (bool) wp_mail( $to, $subject, $body );
	}

	/**
	 * Whether an address was already emailed about a request during this page load.
	 *
	 * @param int    $entry_id Request.
	 * @param string $email    Address.
	 * @return bool
	 */
	public static function already_sent( $entry_id, $email ) {
		return ! empty( self::$sent[ (int) $entry_id ][ strtolower( trim( (string) $email ) ) ] );
	}

	/**
	 * Clears the record of who was emailed about a request, once its watcher emails are out.
	 *
	 * @param int $entry_id Request.
	 */
	public static function forget( $entry_id ) {
		unset( self::$sent[ (int) $entry_id ] );
	}

	/**
	 * Adds a message to a member's queue, keeping the newest QUEUE_MAX.
	 *
	 * @param int    $user_id Member.
	 * @param string $subject Subject.
	 * @param string $body    Body.
	 * @return bool
	 */
	private static function queue( $user_id, $subject, $body ) {
		$queue   = get_user_meta( $user_id, self::QUEUE_META, true );
		$queue   = is_array( $queue ) ? $queue : array();
		$queue[] = array(
			't' => time(),
			's' => $subject,
			'b' => $body,
		);
		update_user_meta( $user_id, self::QUEUE_META, array_slice( $queue, -self::QUEUE_MAX ) );
		return true;
	}

	/**
	 * The daily run: one digest to each member with a queue.
	 */
	public static function send_digests() {
		global $wpdb;
		foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s", self::QUEUE_META ) ) as $user_id ) {
			self::send_digest( (int) $user_id );
		}
	}

	/**
	 * Sends one member's queue as a single email and empties it once sent.
	 *
	 * @param int $user_id Member.
	 * @return bool Whether a digest went out.
	 */
	public static function send_digest( $user_id ) {
		$queue = get_user_meta( $user_id, self::QUEUE_META, true );
		$user  = get_userdata( $user_id );
		if ( ! is_array( $queue ) || ! $queue || ! $user || ! is_email( $user->user_email ) ) {
			delete_user_meta( $user_id, self::QUEUE_META );
			return false;
		}
		$count   = count( $queue );
		/* translators: %d: number of updates */
		$subject = sprintf( _n( '[OWbN] Your Archivist update (%d)', '[OWbN] Your Archivist updates (%d)', $count, 'owbn-archivist-toolkit' ), $count );
		$lines   = array( __( 'Your Archivist updates since the last summary:', 'owbn-archivist-toolkit' ), '' );
		foreach ( $queue as $item ) {
			$lines[] = '— ' . preg_replace( '/^\[OWbN\]\s*/', '', (string) ( $item['s'] ?? '' ) ) . ' (' . gmdate( 'j M, H:i', (int) ( $item['t'] ?? 0 ) ) . ' UTC)';
			foreach ( explode( "\n", (string) ( $item['b'] ?? '' ) ) as $line ) {
				if ( self::FOOTER !== trim( $line ) ) {
					$lines[] = '  ' . $line;
				}
			}
			$lines[] = '';
		}
		$lines[] = __( 'You chose one email a day for Archivist updates. To get each one as it happens, change it on your Archivist dashboard:', 'owbn-archivist-toolkit' );
		$lines[] = home_url( '/?auth=sso&redirect_uri=' . rawurlencode( '/oat-dashboard/' ) );
		if ( wp_mail( $user->user_email, $subject, implode( "\n", $lines ) ) ) {
			delete_user_meta( $user_id, self::QUEUE_META );
			return true;
		}
		return false;
	}
}
