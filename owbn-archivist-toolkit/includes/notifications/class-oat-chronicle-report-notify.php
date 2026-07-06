<?php
/**
 * Chronicle Report → targeted Coordinator email.
 *
 * When a Chronicle Report (chronicle_actions / ca_reporting) is approved, email
 * the report's key details to every Coordinator office the submitter selected in
 * the "Notify Coordinators" field. Recipients are resolved from AccessSchema
 * (the office holders), so mail always reaches whoever currently holds the office.
 * Nothing is sent if no coordinators were selected.
 */

defined( 'ABSPATH' ) || exit;

class OAT_Chronicle_Report_Notify {

	public static function init() {
		add_action( 'oat_entry_approved', array( __CLASS__, 'on_approved' ), 25, 3 );
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
