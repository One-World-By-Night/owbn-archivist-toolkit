<?php
/**
 * The title of a request as it reads in an email subject: "Character Lifecycle - Transfer: Jane Doe".
 *
 * Domain, then the form the filer picked, then the character the request is about. A part that is missing is left
 * out, and a form named like its domain is not repeated.
 */

defined( 'ABSPATH' ) || exit;

class OAT_Request_Title {

	const MAX_NAME = 60;

	/**
	 * Title of one request.
	 *
	 * @param object|null $entry The request row.
	 * @return string '' when there is no request.
	 */
	public static function for_entry( $entry ) {
		if ( ! is_object( $entry ) ) {
			return '';
		}
		return self::build( self::domain_label( $entry ), self::form_label( $entry ), self::character_name( $entry ) );
	}

	/**
	 * Joins the parts: "Domain - Form: Character".
	 *
	 * @param string $domain    Domain label.
	 * @param string $form      Form label.
	 * @param string $character Character name.
	 * @return string
	 */
	public static function build( $domain, $form, $character ) {
		$domain    = self::plain( $domain );
		$form      = self::plain( $form );
		$character = self::plain( $character );

		$title = $domain;
		if ( '' !== $form && 0 !== strcasecmp( $form, $domain ) ) {
			$title .= ( '' === $title ? '' : ' - ' ) . $form;
		}
		if ( '' !== $character ) {
			if ( mb_strlen( $character ) > self::MAX_NAME ) {
				$character = rtrim( mb_substr( $character, 0, self::MAX_NAME - 1 ) ) . '…';
			}
			$title .= ( '' === $title ? '' : ': ' ) . $character;
		}
		return $title;
	}

	/**
	 * One line of plain text: no tags, entities decoded, runs of white space (line breaks included) as one space.
	 *
	 * @param string $text
	 * @return string
	 */
	private static function plain( $text ) {
		$text = wp_specialchars_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES );
		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}

	/**
	 * @param object $entry
	 * @return string
	 */
	private static function domain_label( $entry ) {
		$domain = class_exists( 'OAT_Domain_Registry' ) ? OAT_Domain_Registry::get_php_domain( $entry->domain ) : null;
		if ( $domain && method_exists( $domain, 'get_label' ) ) {
			$label = (string) $domain->get_label();
			if ( '' !== $label ) {
				return $label;
			}
		}
		return ucwords( str_replace( array( '_', '-' ), ' ', (string) $entry->domain ) );
	}

	/**
	 * The form's name as the Submit page shows it ("Transfer", "R&U Request").
	 *
	 * @param object $entry
	 * @return string
	 */
	private static function form_label( $entry ) {
		$slug = isset( $entry->form_slug ) ? (string) $entry->form_slug : '';
		if ( '' === $slug || ! class_exists( 'OAT_Form' ) ) {
			return '';
		}
		$form = OAT_Form::find_by_slug( $slug );
		return $form && isset( $form->label ) ? (string) $form->label : '';
	}

	/**
	 * The character the request is about: its linked character, else the one picked on the form, else a typed name.
	 *
	 * @param object $entry
	 * @return string
	 */
	private static function character_name( $entry ) {
		$char = ! empty( $entry->character_id ) && class_exists( 'OAT_Character' ) ? OAT_Character::find( (int) $entry->character_id ) : null;
		if ( $char && '' !== trim( (string) $char->character_name ) ) {
			return (string) $char->character_name;
		}
		if ( ! class_exists( 'OAT_Entry_Meta' ) || empty( $entry->id ) ) {
			return '';
		}
		$picked = trim( (string) OAT_Entry_Meta::get( (int) $entry->id, 'character_name' ) );
		if ( '' === $picked ) {
			return '';
		}
		$found = class_exists( 'OAT_Character' ) ? OAT_Character::find_by_uuid( $picked ) : null;
		if ( $found ) {
			return (string) $found->character_name;
		}
		return preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $picked ) ? '' : $picked;
	}
}
