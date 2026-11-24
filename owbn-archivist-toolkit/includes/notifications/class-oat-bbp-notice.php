<?php
/**
 * The notice to Council that a request passed by Bump Bump Pass: which Coordinator had an auto-approval processed.
 *
 * Goes to the address or addresses in the option oat_bbp_notice_to (set under Archivist Settings); with none set,
 * nothing is sent.
 */

defined( 'ABSPATH' ) || exit;

class OAT_Bbp_Notice {

	const OPTION = 'oat_bbp_notice_to';

	/**
	 * Listens for an auto-approval.
	 */
	public static function init() {
		add_action( 'oat_entry_auto_approved', array( __CLASS__, 'on_auto_approved' ), 30, 3 );
	}

	/**
	 * The valid addresses in the option, separated by commas, semicolons or white space.
	 *
	 * @return string[]
	 */
	public static function addresses() {
		$out = array();
		foreach ( preg_split( '/[\s,;]+/', (string) get_option( self::OPTION, '' ) ) as $address ) {
			$address = trim( $address );
			if ( '' !== $address && is_email( $address ) ) {
				$out[ strtolower( $address ) ] = $address;
			}
		}
		return array_values( $out );
	}

	/**
	 * Emails the notice for one request.
	 *
	 * @param int    $entry_id The request.
	 * @param string $step     The step that passed.
	 * @param int    $bumps    How many times the requester bumped it.
	 * @return void
	 */
	public static function on_auto_approved( $entry_id, $step, $bumps ) {
		$to = self::addresses();
		if ( ! $to ) {
			return;
		}
		$entry = OAT_Entry::find( (int) $entry_id );
		if ( ! $entry ) {
			return;
		}

		$responsible = self::responsible( $entry, (string) $step );
		$title       = class_exists( 'OAT_Request_Title' ) ? OAT_Request_Title::for_entry( $entry ) : '';
		$subject     = sprintf( '[OWbN] Auto-approval processed: %s', $responsible );
		$body        = sprintf( "%s had an auto-approval processed.\n\nRequest #%d%s\nThe step went unanswered past its deadline and the requester bumped the request %d times.\nProcessed: %s UTC\n", $responsible, (int) $entry_id, '' !== $title ? ': ' . $title : '', (int) $bumps, gmdate( 'Y-m-d H:i' ) );

		foreach ( $to as $address ) {
			OAT_Notify_Mail::send( $address, $subject, $body, (int) $entry_id );
		}
	}

	/**
	 * The Coordinator office or offices that did not answer, or the step's name when it is not a Coordinator step.
	 *
	 * @param object $entry The request.
	 * @param string $step  The step that passed.
	 * @return string
	 */
	private static function responsible( $entry, $step ) {
		$config = OAT_Workflow_Engine::get_step_config( $entry, $step );
		if ( $config && ! empty( $config['assignee_role'] ) && false !== strpos( $config['assignee_role'], '{coordinator_genre}' ) ) {
			$labels = array();
			foreach ( OAT_Workflow_Engine::coordinator_offices( $entry ) as $slug ) {
				$labels[] = self::office_label( (string) $slug );
			}
			if ( $labels ) {
				return implode( ', ', $labels );
			}
		}
		return $config && ! empty( $config['label'] ) ? (string) $config['label'] : $step;
	}

	/**
	 * An office's title ("Gangrel Coordinator"), or its slug when the office is not on the list.
	 *
	 * @param string $slug Office slug.
	 * @return string
	 */
	private static function office_label( $slug ) {
		$list = function_exists( 'owc_get_coordinators' ) ? owc_get_coordinators() : array();
		foreach ( (array) $list as $record ) {
			$record = (array) $record;
			if ( isset( $record['slug'], $record['title'] ) && strtolower( (string) $record['slug'] ) === strtolower( $slug ) && '' !== trim( (string) $record['title'] ) ) {
				return (string) $record['title'];
			}
		}
		return $slug;
	}
}
