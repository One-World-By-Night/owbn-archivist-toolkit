<?php

defined( 'ABSPATH' ) || exit;

/**
 * A player connects a character to their account by proving they can read the email on the character's sheet.
 *
 * Only a player character with no account link, with an email on its sheet that no account holds, can be claimed (a character
 * whose email an account holds is linked to that account by the account sync). The claimant finds the character by name, and a
 * one-time link is emailed to the address on the sheet. Opening the link shows who asked and which character, and the
 * character is linked only when the button on that page is pressed (a mail scanner that opens the link does not link it).
 * The links are stored as transients, so a link that is not used expires on its own.
 */
class OAT_Character_Claim {

	const TTL               = 172800;
	const PREFIX            = 'oat_claim_';
	const LOG               = 'oat_claim_log';
	const LOG_MAX           = 300;
	const QUERY_VAR         = 'oat_claim';
	const MIN_TERM          = 3;
	const MAX_RESULTS       = 10;
	const SEARCHES_PER_HOUR = 30;
	const REQUESTS_PER_HOUR = 5;
	const REQUESTS_PER_DAY  = 3;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_handle' ), 1 );
	}

	/**
	 * The condition a character must meet to be claimed: a player character with no account link, with an email on its sheet that no
	 * account holds.
	 *
	 * @param string $alias Table alias of the characters table.
	 * @return string SQL.
	 */
	private static function claimable_sql( $alias = 'c' ) {
		global $wpdb;
		return "( {$alias}.wp_user_id IS NULL OR {$alias}.wp_user_id = 0 ) AND LOWER({$alias}.pc_npc) = 'pc' AND TRIM({$alias}.player_email) <> ''"
			. " AND NOT EXISTS ( SELECT 1 FROM {$wpdb->users} u WHERE u.user_email = TRIM({$alias}.player_email) )";
	}

	/**
	 * Who may claim: a real account other than the web admin account.
	 *
	 * @param int $user_id Account id.
	 * @return WP_User|WP_Error
	 */
	private static function claimant( $user_id ) {
		$user = (int) $user_id > 1 ? get_userdata( (int) $user_id ) : false;
		if ( ! $user || ! is_email( $user->user_email ) ) {
			return new WP_Error( 'oat_claim_account', 'Sign in with your own account to connect a character.' );
		}
		return $user;
	}

	/**
	 * Counts a try against a limit.
	 *
	 * @param string $key     Counter name.
	 * @param int    $max     Tries allowed in the window.
	 * @param int    $seconds Window length.
	 * @return bool False when the limit is already reached.
	 */
	private static function allow( $key, $max, $seconds ) {
		$state = get_transient( self::PREFIX . 'rl_' . $key );
		$now   = time();
		if ( ! is_array( $state ) || $now - (int) $state['t'] >= $seconds ) {
			$state = array( 'n' => 0, 't' => $now );
		}
		if ( (int) $state['n'] >= $max ) {
			return false;
		}
		$state['n']++;
		set_transient( self::PREFIX . 'rl_' . $key, $state, max( 60, $seconds - ( $now - (int) $state['t'] ) ) );
		return true;
	}

	/**
	 * Characters a player can claim, found by name.
	 *
	 * @param int    $user_id Who is searching.
	 * @param string $term    Part of the character's name (at least MIN_TERM characters).
	 * @return array|WP_Error Rows of id, name, chronicle, status. The player's name and email are never returned.
	 */
	public static function search( $user_id, $term ) {
		global $wpdb;
		$user = self::claimant( $user_id );
		if ( is_wp_error( $user ) ) {
			return $user;
		}
		$term = trim( (string) $term );
		if ( mb_strlen( $term ) < self::MIN_TERM ) {
			return new WP_Error( 'oat_claim_term', 'Type at least ' . self::MIN_TERM . ' letters of the character\'s name.' );
		}
		if ( ! self::allow( 'search_' . (int) $user_id, self::SEARCHES_PER_HOUR, HOUR_IN_SECONDS ) ) {
			return new WP_Error( 'oat_claim_limit', 'Too many searches this hour. Try again later.' );
		}
		$c    = $wpdb->prefix . 'oat_characters';
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT c.id, c.character_name, c.chronicle_slug, c.status FROM {$c} c WHERE " . self::claimable_sql( 'c' ) . ' AND c.character_name LIKE %s ORDER BY c.character_name ASC LIMIT %d',
			'%' . $wpdb->esc_like( $term ) . '%',
			self::MAX_RESULTS
		) );
		$out = array();
		foreach ( (array) $rows as $r ) {
			$out[] = array(
				'id'        => (int) $r->id,
				'name'      => (string) $r->character_name,
				'chronicle' => self::chronicle_title( (string) $r->chronicle_slug ),
				'status'    => (string) $r->status,
			);
		}
		return $out;
	}

	/**
	 * Emails the one-time link to the address on the character's sheet.
	 *
	 * @param int $user_id      Who is asking.
	 * @param int $character_id The character.
	 * @return array|WP_Error array( 'sent' => true ) on success.
	 */
	public static function request( $user_id, $character_id ) {
		global $wpdb;
		$user = self::claimant( $user_id );
		if ( is_wp_error( $user ) ) {
			return $user;
		}
		$character = self::claimable_character( (int) $character_id );
		if ( ! $character ) {
			return new WP_Error( 'oat_claim_unavailable', 'That character cannot be connected this way. Ask the Web Team to link it.' );
		}
		if ( ! self::allow( 'req_user_' . (int) $user_id, self::REQUESTS_PER_HOUR, HOUR_IN_SECONDS ) ) {
			return new WP_Error( 'oat_claim_limit', 'Too many requests this hour. Try again later.' );
		}
		if ( ! self::allow( 'req_char_' . (int) $character->id, self::REQUESTS_PER_DAY, DAY_IN_SECONDS ) ) {
			return new WP_Error( 'oat_claim_limit', 'A link for that character was sent several times today already. Check the email on its sheet, or ask the Web Team.' );
		}
		$email = strtolower( trim( (string) $character->player_email ) );
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'oat_claim_unavailable', 'That character cannot be connected this way. Ask the Web Team to link it.' );
		}
		$token = bin2hex( random_bytes( 24 ) );
		set_transient(
			self::PREFIX . hash( 'sha256', $token ),
			array( 'c' => (int) $character->id, 'u' => (int) $user->ID, 'h' => hash( 'sha256', $email ), 't' => time() ),
			self::TTL
		);

		$name    = self::plain( $character->character_name );
		$where   = self::plain( self::chronicle_title( (string) $character->chronicle_slug ) );
		$asker   = self::plain( $user->display_name );
		$url     = add_query_arg( self::QUERY_VAR, $token, home_url( '/' ) );
		$subject = 'Confirm: connect ' . $name . ' to an OWbN account';
		$body    = "Hello,\n\n"
			. $asker . ' asked to connect the character "' . $name . '"' . ( '' !== $where ? ' (' . $where . ')' : '' ) . " to their OWbN account. This is the email address on that character's sheet.\n\n"
			. "If that is you, open this link within 48 hours and press the button on the page:\n" . $url . "\n\n"
			. "If it is not you, ignore this email. Nothing is connected unless the button is pressed.\n\n"
			. "---\n\n"
			. 'Olá,' . "\n\n"
			. $asker . ' pediu para ligar a personagem "' . $name . '"' . ( '' !== $where ? ' (' . $where . ')' : '' ) . " à sua conta OWbN. Este é o e-mail que consta na ficha da personagem.\n\n"
			. "Se foi você, abra este link nas próximas 48 horas e carregue no botão da página:\n" . $url . "\n\n"
			. "Se não foi você, ignore este e-mail. Nada é ligado sem carregar no botão.\n\n"
			. OAT_Notify_Mail::FOOTER . "\n";

		if ( ! OAT_Notify_Mail::send( $email, $subject, $body ) ) {
			delete_transient( self::PREFIX . hash( 'sha256', $token ) );
			return new WP_Error( 'oat_claim_mail', 'The email could not be sent. Try again later, or ask the Web Team.' );
		}
		self::log( 'requested', (int) $character->id, (int) $user->ID );
		return array( 'sent' => true );
	}

	/**
	 * What a link is for, to show on the confirmation page.
	 *
	 * @param string $token The token from the link.
	 * @return array|WP_Error array( 'character' => name, 'chronicle' => title, 'account' => display name ).
	 */
	public static function peek( $token ) {
		$check = self::check( $token );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		return array(
			'character' => self::plain( $check['character']->character_name ),
			'chronicle' => self::plain( self::chronicle_title( (string) $check['character']->chronicle_slug ) ),
			'account'   => self::plain( $check['user']->display_name ),
		);
	}

	/**
	 * Links the character to the account the link was requested for. A link works once.
	 *
	 * @param string $token The token from the link.
	 * @return array|WP_Error array( 'character' => name ).
	 */
	public static function confirm( $token ) {
		global $wpdb;
		$check = self::check( $token );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		$character = $check['character'];
		$user      = $check['user'];
		delete_transient( self::PREFIX . hash( 'sha256', (string) $token ) );

		$c = $wpdb->prefix . 'oat_characters';
		$n = (int) $wpdb->query( $wpdb->prepare(
			"UPDATE {$c} SET wp_user_id = %d, updated_at = %d WHERE id = %d AND ( wp_user_id IS NULL OR wp_user_id = 0 ) AND LOWER(pc_npc) = 'pc' AND LOWER(TRIM(player_email)) = %s",
			(int) $user->ID,
			time(),
			(int) $character->id,
			strtolower( trim( (string) $character->player_email ) )
		) );
		if ( 1 !== $n ) {
			return new WP_Error( 'oat_claim_changed', 'That character changed while the link was waiting, so nothing was connected. Ask the Web Team.' );
		}
		self::log( 'linked', (int) $character->id, (int) $user->ID );
		return array( 'character' => (string) $character->character_name );
	}

	/**
	 * The stored link and the facts it depends on, checked again now.
	 *
	 * @param string $token The token from the link.
	 * @return array|WP_Error array( 'character' => row, 'user' => WP_User ).
	 */
	private static function check( $token ) {
		$token = (string) $token;
		if ( ! preg_match( '/^[a-f0-9]{48}$/', $token ) ) {
			return new WP_Error( 'oat_claim_expired', 'This link is not valid, has expired, or was already used.' );
		}
		$stored = get_transient( self::PREFIX . hash( 'sha256', $token ) );
		if ( ! is_array( $stored ) ) {
			return new WP_Error( 'oat_claim_expired', 'This link is not valid, has expired, or was already used.' );
		}
		$user = self::claimant( (int) $stored['u'] );
		if ( is_wp_error( $user ) ) {
			return new WP_Error( 'oat_claim_expired', 'This link is not valid, has expired, or was already used.' );
		}
		$character = self::claimable_character( (int) $stored['c'] );
		$email     = $character ? strtolower( trim( (string) $character->player_email ) ) : '';
		if ( ! $character || ! hash_equals( (string) $stored['h'], hash( 'sha256', $email ) ) ) {
			return new WP_Error( 'oat_claim_changed', 'That character changed while the link was waiting, so nothing was connected. Ask the Web Team.' );
		}
		return array( 'character' => $character, 'user' => $user );
	}

	/**
	 * A character that can still be claimed, by id.
	 *
	 * @param int $id Character id.
	 * @return object|null
	 */
	private static function claimable_character( $id ) {
		global $wpdb;
		$c = $wpdb->prefix . 'oat_characters';
		return $wpdb->get_row( $wpdb->prepare( "SELECT c.* FROM {$c} c WHERE c.id = %d AND " . self::claimable_sql( 'c' ), (int) $id ) );
	}

	/**
	 * Text for an email or page: no tags, one line, at most 60 characters.
	 *
	 * @param string $text Any text.
	 * @return string
	 */
	private static function plain( $text ) {
		return mb_substr( trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $text ) ) ), 0, 60 );
	}

	private static function chronicle_title( $slug ) {
		if ( '' === $slug ) {
			return '';
		}
		$title = function_exists( 'owc_entity_get_title' ) ? owc_entity_get_title( 'chronicle', $slug ) : '';
		return '' !== $title ? (string) $title : strtoupper( $slug );
	}

	/**
	 * Keeps a short record of requests and links, newest last.
	 *
	 * @param string $event        requested or linked.
	 * @param int    $character_id Character.
	 * @param int    $user_id      Account.
	 */
	private static function log( $event, $character_id, $user_id ) {
		$log   = (array) get_option( self::LOG, array() );
		$log[] = array( 'at' => time(), 'event' => $event, 'character_id' => $character_id, 'user_id' => $user_id );
		update_option( self::LOG, array_slice( $log, -self::LOG_MAX ), false );
	}

	/**
	 * The page behind the emailed link: GET shows who asked and for which character; POST links it.
	 */
	public static function maybe_handle() {
		if ( empty( $_REQUEST[ self::QUERY_VAR ] ) ) {
			return;
		}
		$token = sanitize_text_field( wp_unslash( $_REQUEST[ self::QUERY_VAR ] ) );
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );
		header( 'Referrer-Policy: no-referrer' );

		$posted = 'POST' === ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) && isset( $_POST['oat_claim_confirm'] );
		if ( $posted ) {
			$done = self::confirm( $token );
			if ( is_wp_error( $done ) ) {
				self::page( 'Not connected', '<p>' . esc_html( $done->get_error_message() ) . '</p>' );
			}
			self::page(
				'Connected',
				'<p><strong>' . esc_html( $done['character'] ) . '</strong> is now connected to the account. Sign in to OWbN with that account and open My Characters to see it.</p>'
			);
		}
		$info = self::peek( $token );
		if ( is_wp_error( $info ) ) {
			self::page( 'Link not valid', '<p>' . esc_html( $info->get_error_message() ) . '</p>' );
		}
		$what = '<strong>' . esc_html( $info['character'] ) . '</strong>' . ( '' !== $info['chronicle'] ? ' (' . esc_html( $info['chronicle'] ) . ')' : '' );
		self::page(
			'Connect a character',
			'<p>' . esc_html( $info['account'] ) . ' asked to connect ' . $what . ' to their OWbN account.</p>'
			. '<p>Press the button only if that is you. If it is not, close this page: nothing is connected.</p>'
			. '<form method="post" action="' . esc_url( home_url( '/' ) ) . '">'
			. '<input type="hidden" name="' . esc_attr( self::QUERY_VAR ) . '" value="' . esc_attr( $token ) . '">'
			. '<button type="submit" name="oat_claim_confirm" value="1" style="padding:10px 18px;font-size:16px;cursor:pointer;">Yes, connect it</button>'
			. '</form>'
		);
	}

	/**
	 * Prints a plain page and stops.
	 *
	 * @param string $title Heading.
	 * @param string $html  Body, already escaped.
	 */
	private static function page( $title, $html ) {
		status_header( 200 );
		header( 'Content-Type: text/html; charset=utf-8' );
		echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>'
			. esc_html( $title ) . '</title></head><body style="font-family:system-ui,sans-serif;max-width:560px;margin:48px auto;padding:0 16px;line-height:1.5;color:#1d2327;"><h1 style="font-size:24px;">'
			. esc_html( $title ) . '</h1>' . $html . '</body></html>';
		exit;
	}
}
