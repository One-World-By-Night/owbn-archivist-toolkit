<?php
/**
 * Gives a new player the player role in a chronicle when the chronicle's staff accept their Registration.
 *
 * A signed-in account that is a player nowhere may create a character in any running chronicle and file a Registration
 * for it (OAT_Submit_Guard). Such a request is tagged when it is filed. When the chronicle's HST, CM or staff approve it
 * at Staff Review, this asks the accessSchema server for chronicle/<slug>/player for the person who filed it, through a
 * route that can grant that one role and no other. Only a Registration tagged that way, approved at Staff Review by staff
 * of that chronicle, ever asks, and each request asks once.
 *
 * Settings, as site options: oat_player_grant_key (the route's own key) and oat_player_grant_url (the accessSchema
 * site's address; the owbn-core accessSchema address when unset). With no key nothing is asked and the request's History
 * says the role was not granted.
 */

defined( 'ABSPATH' ) || exit;

class OAT_Player_Grant {

	const OPTION_KEY  = 'oat_player_grant_key';
	const OPTION_URL  = 'oat_player_grant_url';
	const FLAG_META   = '_oat_newcomer_registration';
	const RESULT_META = '_oat_player_grant';
	const FORM        = 'cl_registration';
	const STEP        = 'staff_review';

	public static function init() {
		add_action( 'oat_entry_step_approved', array( __CLASS__, 'on_step_approved' ), 20, 3 );
	}

	/**
	 * Tags a Registration filed by a newcomer, so its approval may grant the player role.
	 *
	 * @param object $entry The request.
	 * @return void
	 */
	public static function tag_if_newcomer( $entry ) {
		if ( ! is_object( $entry ) || self::FORM !== (string) ( $entry->form_slug ?? '' ) || empty( $entry->id ) ) {
			return;
		}
		if ( OAT_Submit_Guard::sender_is_newcomer( $entry ) ) {
			OAT_Entry_Meta::set( (int) $entry->id, self::FLAG_META, '1' );
		}
	}

	/**
	 * A step of a request was approved.
	 *
	 * @param int    $entry_id The request.
	 * @param string $step_id  The step that was approved.
	 * @param int    $user_id  Who approved it.
	 * @return void
	 */
	public static function on_step_approved( $entry_id, $step_id, $user_id ) {
		if ( self::STEP !== (string) $step_id ) {
			return;
		}
		$entry = OAT_Entry::find( (int) $entry_id );
		if ( ! $entry || self::FORM !== (string) $entry->form_slug ) {
			return;
		}
		if ( '1' !== (string) OAT_Entry_Meta::get( (int) $entry->id, self::FLAG_META ) || '' !== (string) OAT_Entry_Meta::get( (int) $entry->id, self::RESULT_META ) ) {
			return;
		}

		$slug = self::chronicle_of( $entry );
		if ( '' === $slug || ! self::is_chronicle_staff( (int) $user_id, $slug ) ) {
			return;
		}
		$player = get_userdata( (int) $entry->originator_id );
		if ( ! $player || ! is_email( $player->user_email ) ) {
			self::record( $entry, (int) $user_id, array( 'ok' => false, 'message' => 'the person who filed it has no account email' ), $slug );
			return;
		}
		if ( function_exists( 'owc_oat_chronicle_is_active' ) && ! owc_oat_chronicle_is_active( $slug ) ) {
			self::record( $entry, (int) $user_id, array( 'ok' => false, 'message' => 'the chronicle is not running' ), $slug );
			return;
		}

		$result = self::request( $player->user_email, $slug, (int) $entry->id );
		if ( ! empty( $result['ok'] ) && function_exists( 'owc_asc_cache_delete' ) ) {
			// This site's copy of their roles is a day old at most; drop it so they are a player here at once.
			owc_asc_cache_delete( (int) $player->ID );
		}
		self::record( $entry, (int) $user_id, $result, $slug );
	}

	/**
	 * Asks the accessSchema server for chronicle/<slug>/player for the account with this email.
	 *
	 * @param string $email    The player's account email.
	 * @param string $slug     The chronicle.
	 * @param int    $entry_id The request, for the server's records.
	 * @return array ok (bool), already (bool), message (string).
	 */
	public static function request( $email, $slug, $entry_id ) {
		$key = trim( (string) get_option( self::OPTION_KEY, '' ) );
		$url = trim( (string) get_option( self::OPTION_URL, '' ) );
		if ( '' === $url && function_exists( 'owc_asc_get_remote_url' ) ) {
			$url = (string) owc_asc_get_remote_url();
		}
		if ( '' === $key || '' === $url ) {
			return array( 'ok' => false, 'already' => false, 'message' => 'this site has no player-grant key or no accessSchema address' );
		}
		$response = wp_remote_post(
			trailingslashit( untrailingslashit( $url ) ) . 'wp-json/access-schema/v1/grant-player',
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
					'x-api-key'    => $key,
				),
				'body'    => wp_json_encode( array( 'email' => $email, 'chronicle_slug' => $slug, 'source' => 'oat:' . (int) $entry_id ) ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return array( 'ok' => false, 'already' => false, 'message' => 'accessSchema could not be reached: ' . $response->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$data = is_array( $data ) ? $data : array();
		if ( 200 === $code && ! empty( $data['granted'] ) ) {
			return array( 'ok' => true, 'already' => ! empty( $data['already'] ), 'message' => '' );
		}
		if ( array() === $data ) {
			return array( 'ok' => false, 'already' => false, 'message' => sprintf( 'accessSchema sent back an answer this site could not read (HTTP %d)', $code ) );
		}
		$why = isset( $data['message'] ) ? (string) $data['message'] : ( isset( $data['reason'] ) ? (string) $data['reason'] : 'HTTP ' . $code );
		return array( 'ok' => false, 'already' => false, 'message' => $why );
	}

	/**
	 * The chronicle a request is for: its own, else its character's.
	 *
	 * @param object $entry The request.
	 * @return string Lower-case slug, or ''.
	 */
	private static function chronicle_of( $entry ) {
		$slug = strtolower( trim( (string) ( $entry->chronicle_slug ?? '' ) ) );
		if ( '' === $slug && ! empty( $entry->character_id ) && class_exists( 'OAT_Character' ) ) {
			$char = OAT_Character::find( (int) $entry->character_id );
			$slug = $char ? strtolower( trim( (string) $char->chronicle_slug ) ) : '';
		}
		return $slug;
	}

	/**
	 * Whether a person is HST, CM or staff of a chronicle.
	 *
	 * @param int    $user_id The person.
	 * @param string $slug    The chronicle.
	 * @return bool
	 */
	private static function is_chronicle_staff( $user_id, $slug ) {
		$user = get_userdata( (int) $user_id );
		if ( ! $user ) {
			return false;
		}
		return in_array( $slug, OAT_Authorization::staff_chronicle_slugs( OAT_Submit_Guard::role_paths( $user ) ), true );
	}

	/**
	 * Writes down what happened: on the request, and in its History for the staff.
	 *
	 * @param object $entry  The request.
	 * @param int    $actor  Who approved the step.
	 * @param array  $result The outcome of request().
	 * @param string $slug   The chronicle.
	 * @return void
	 */
	private static function record( $entry, $actor, array $result, $slug ) {
		$ok      = ! empty( $result['ok'] );
		$already = ! empty( $result['already'] );
		OAT_Entry_Meta::set( (int) $entry->id, self::RESULT_META, wp_json_encode( array( 'at' => time(), 'ok' => $ok, 'already' => $already, 'message' => (string) ( $result['message'] ?? '' ), 'role' => 'chronicle/' . $slug . '/player' ) ) );
		if ( $ok ) {
			$note = $already
				? sprintf( 'Already a player in this chronicle (chronicle/%s/player). Nothing to grant.', $slug )
				: sprintf( 'Player role granted: chronicle/%s/player.', $slug );
		} else {
			$note = sprintf( 'Player role NOT granted (%s). Add the player on the chronicle\'s page.', (string) ( $result['message'] ?? 'unknown' ) );
		}
		if ( class_exists( 'OAT_Timeline' ) ) {
			OAT_Timeline::append( array(
				'entry_id'        => (int) $entry->id,
				'action_type'     => OAT_Constants::ACTION_PLAYER_ROLE,
				'actor_id'        => $actor,
				'step'            => self::STEP,
				'visibility_tier' => OAT_Constants::TIER_STAFF,
				'note'            => $note,
			) );
		}
	}
}
