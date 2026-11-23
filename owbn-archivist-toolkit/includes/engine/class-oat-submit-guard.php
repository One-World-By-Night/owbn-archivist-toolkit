<?php

defined( 'ABSPATH' ) || exit;

/**
 * Refuses a submission carrying a choice its form would not have offered the sender: a form from another kind of
 * request, a select option its role_filter withholds from the sender, a chronicle the chronicle picker would not
 * list for them, or a character the character search would not have found for them. Each rule is the one the form
 * itself applies when it is drawn, with the sender's own roles.
 */
class OAT_Submit_Guard {

	/**
	 * The chronicle roles the picker's script counts as the sender's own chronicles.
	 */
	const OWN_CHRONICLE_ROLES = array( 'hst', 'staff', 'cm', 'player' );

	/**
	 * The character picker fields that name the character a request is about. Other character pickers on a form
	 * (the teacher of learned content) name someone else's character and are not checked.
	 */
	const REQUEST_CHARACTER_FIELDS = array( 'character_name', 'character' );

	/**
	 * What a newcomer, a signed-in account that is a player nowhere and holds no office, may file: a Registration, and at
	 * most this many that are still open.
	 */
	const NEWCOMER_FORM     = 'cl_registration';
	const NEWCOMER_OPEN_CAP = 3;

	/**
	 * @param object $entry Entry row, or an unsaved one with the same fields (domain, form_slug, originator_id).
	 * @param array  $meta  Field values as submitted.
	 * @return true|WP_Error
	 */
	public static function check( $entry, array $meta ) {
		$domain = (string) ( $entry->domain ?? '' );
		$form   = (string) ( $entry->form_slug ?? '' );
		if ( '' === $domain || ! class_exists( 'OAT_Form_Field' ) ) {
			return true;
		}
		if ( '' !== $form && $form !== $domain && ! self::form_in_domain( $form, $domain ) ) {
			return new WP_Error( 'oat_form_not_in_domain', __( 'That form does not belong to this kind of request.', 'owbn-archivist-toolkit' ) );
		}
		$user    = self::sender( $entry );
		$lookup  = self::lookup_roles( $user );
		$roles   = null === $lookup ? array() : $lookup;
		$super   = $user && function_exists( 'owc_oat_is_super_user' ) && owc_oat_is_super_user( $user->ID );
		// A failed role lookup never makes anyone a newcomer.
		$refused = ( $super || null === $lookup ) ? null : self::check_newcomer( $entry, $form, $user, $roles );
		if ( is_wp_error( $refused ) ) {
			return $refused;
		}

		$fields = OAT_Form_Field::get_fields( '' !== $form ? $form : $domain, 'submit' );
		if ( ! $fields ) {
			return true;
		}

		foreach ( $fields as $field ) {
			$key   = (string) ( $field['key'] ?? '' );
			$value = ( '' !== $key && isset( $meta[ $key ] ) && is_scalar( $meta[ $key ] ) ) ? trim( (string) $meta[ $key ] ) : '';
			if ( '' === $value ) {
				continue;
			}
			$attrs = is_array( $field['attributes'] ?? null ) ? $field['attributes'] : array();
			$label = (string) ( $field['label'] ?? $key );

			if ( 'select' === ( $field['type'] ?? '' ) && ! empty( $attrs['role_filter'] ) && is_array( $attrs['role_filter'] ) && isset( $attrs['role_filter'][ $value ] ) ) {
				if ( ! self::matches_any( $roles, $attrs['role_filter'][ $value ] ) ) {
					/* translators: %s: the field's label, e.g. "Your Role" */
					return new WP_Error( 'oat_choice_not_yours', sprintf( __( 'You don\'t hold the role you chose for "%s".', 'owbn-archivist-toolkit' ), $label ) );
				}
			}

			if ( 'chronicle_picker' === ( $field['type'] ?? '' ) && ! $super ) {
				$allowed = self::chronicles_offered( $attrs, $meta, $roles );
				if ( null !== $allowed && ! in_array( strtolower( $value ), $allowed, true ) && ! self::is_own_characters_chronicle( $fields, $meta, $user, $value ) ) {
					/* translators: %s: the field's label, e.g. "Chronicle Name" */
					return new WP_Error( 'oat_chronicle_not_yours', sprintf( __( 'You can\'t submit this for the chronicle chosen in "%s".', 'owbn-archivist-toolkit' ), $label ) );
				}
			}
		}
		if ( ! $super ) {
			$refused = self::check_characters( $fields, $meta, $user, $roles );
			if ( is_wp_error( $refused ) ) {
				return $refused;
			}
		}
		return true;
	}

	/**
	 * Whether a person is a newcomer: not a site admin, an office holder, a chronicle's staff or a player anywhere.
	 *
	 * @param WP_User|null $user  The sender.
	 * @param array        $roles The sender's role paths.
	 * @return bool
	 */
	public static function is_newcomer( $user, array $roles ) {
		if ( ! $user || ( function_exists( 'owc_oat_is_super_user' ) && owc_oat_is_super_user( $user->ID ) ) ) {
			return false;
		}
		foreach ( $roles as $path ) {
			$path = strtolower( (string) $path );
			if ( 'player/approved' === $path
				|| preg_match( '#^chronicle/[^/]+/(player|hst|cm|staff|st|ast)$#', $path )
				|| preg_match( '#^exec/[^/]+/coordinator$#', $path )
				|| preg_match( '#^coordinator/[^/]+/(coordinator|sub-coordinator)$#', $path ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Whether the person a request is from is a newcomer.
	 *
	 * @param object $entry The request.
	 * @return bool
	 */
	public static function sender_is_newcomer( $entry ) {
		$user   = self::sender( $entry );
		$lookup = self::lookup_roles( $user );
		return null !== $lookup && self::is_newcomer( $user, $lookup );
	}

	/**
	 * A newcomer files a Registration for their own character and nothing else, and has at most NEWCOMER_OPEN_CAP of them
	 * open. Once the chronicle's staff accept one they are a player there and the rest of the forms open to them.
	 *
	 * @param object       $entry The request, saved or not.
	 * @param string       $form  The form slug.
	 * @param WP_User|null $user  The sender.
	 * @param array        $roles The sender's role paths.
	 * @return true|WP_Error
	 */
	private static function check_newcomer( $entry, $form, $user, array $roles ) {
		if ( ! self::is_newcomer( $user, $roles ) ) {
			return true;
		}
		if ( self::NEWCOMER_FORM !== $form ) {
			return new WP_Error( 'oat_newcomer_registration_only', __( 'Start with a Registration for your character. When the chronicle\'s staff accept it you are a player there, and the other forms open to you.', 'owbn-archivist-toolkit' ) );
		}
		global $wpdb;
		$open = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}oat_entries WHERE originator_id = %d AND form_slug = %s AND status IN ('pending','in_progress','on_hold') AND id <> %d",
			(int) $user->ID,
			self::NEWCOMER_FORM,
			(int) ( $entry->id ?? 0 )
		) );
		if ( $open >= self::NEWCOMER_OPEN_CAP ) {
			/* translators: %d: how many Registrations may be open at once */
			return new WP_Error( 'oat_newcomer_cap', sprintf( __( 'You already have %d Registrations waiting. Wait for a chronicle\'s staff to answer one before you file another.', 'owbn-archivist-toolkit' ), self::NEWCOMER_OPEN_CAP ) );
		}
		return true;
	}

	/**
	 * Refuses a request about a character the sender could not have found in the character search: every character
	 * the request names (the form's character field, and a character_id sent with it) must be the sender's own, in a
	 * chronicle they are staff of, or any character for a Coordinator or the Archivist.
	 *
	 * @param array        $fields The form's submit fields.
	 * @param array        $meta   Field values as submitted.
	 * @param WP_User|null $user   The sender.
	 * @param array        $roles  The sender's role paths.
	 * @return true|WP_Error
	 */
	private static function check_characters( array $fields, array $meta, $user, array $roles ) {
		if ( ! class_exists( 'OAT_Character' ) ) {
			return true;
		}
		$named = array();
		foreach ( $fields as $field ) {
			$key = (string) ( $field['key'] ?? '' );
			if ( 'character_picker' !== ( $field['type'] ?? '' ) || ! in_array( $key, self::REQUEST_CHARACTER_FIELDS, true ) ) {
				continue;
			}
			$uuid = isset( $meta[ $key ] ) && is_scalar( $meta[ $key ] ) ? trim( (string) $meta[ $key ] ) : '';
			$char = '' !== $uuid ? OAT_Character::find_by_uuid( $uuid ) : null;
			if ( $char ) {
				$named[ (int) $char->id ] = $char;
			}
		}
		$sent_id = isset( $meta['character_id'] ) && is_scalar( $meta['character_id'] ) ? (int) $meta['character_id'] : 0;
		if ( $sent_id > 0 && ! isset( $named[ $sent_id ] ) ) {
			$char = OAT_Character::find( $sent_id );
			if ( $char ) {
				$named[ $sent_id ] = $char;
			}
		}
		foreach ( $named as $char ) {
			if ( ! self::may_file_for( $char, $user, $roles ) ) {
				return new WP_Error( 'oat_character_not_yours', __( 'You can\'t file this for that character. Pick one of your own, or one in a chronicle you are staff of.', 'owbn-archivist-toolkit' ) );
			}
		}
		return true;
	}

	/**
	 * Whether the sender may file a request about a character: it is theirs (linked to their account or carrying
	 * their player email), they are a Coordinator or the Archivist, or they are staff in its chronicle.
	 *
	 * @param object       $char  The character.
	 * @param WP_User|null $user  The sender.
	 * @param array        $roles The sender's role paths.
	 * @return bool
	 */
	private static function may_file_for( $char, $user, array $roles ) {
		if ( ! $user ) {
			return false;
		}
		$linked   = (int) $char->wp_user_id > 0 && (int) $char->wp_user_id === (int) $user->ID;
		$by_email = '' !== (string) $user->user_email && '' !== (string) $char->player_email && 0 === strcasecmp( (string) $char->player_email, (string) $user->user_email );
		if ( $linked || $by_email ) {
			return true;
		}
		$tiers = OAT_Authorization::character_search_roles_for( $user, $roles );
		if ( in_array( 'archivist', $tiers, true ) || in_array( 'coordinator', $tiers, true ) ) {
			return true;
		}
		return in_array( 'staff', $tiers, true )
			&& '' !== (string) $char->chronicle_slug
			&& in_array( strtolower( (string) $char->chronicle_slug ), OAT_Authorization::staff_chronicle_slugs( $roles ), true );
	}

	/**
	 * Whether the chronicle chosen is the chronicle of a character the sender plays: the form's character is linked to
	 * the sender's account or carries their player email, and its own chronicle is the one chosen. A player can then
	 * file for their own character in a chronicle where they hold no role.
	 *
	 * @param array        $fields    The form's submit fields.
	 * @param array        $meta      Field values as submitted.
	 * @param WP_User|null $user      The sender.
	 * @param string       $chronicle The chronicle slug chosen.
	 * @return bool
	 */
	private static function is_own_characters_chronicle( array $fields, array $meta, $user, $chronicle ) {
		if ( ! $user || '' === (string) $chronicle || ! class_exists( 'OAT_Character' ) ) {
			return false;
		}
		foreach ( $fields as $field ) {
			if ( 'character_picker' !== ( $field['type'] ?? '' ) ) {
				continue;
			}
			$key  = (string) ( $field['key'] ?? '' );
			$uuid = ( '' !== $key && isset( $meta[ $key ] ) && is_scalar( $meta[ $key ] ) ) ? trim( (string) $meta[ $key ] ) : '';
			$char = '' !== $uuid ? OAT_Character::find_by_uuid( $uuid ) : null;
			if ( ! $char || '' === (string) $char->chronicle_slug || 0 !== strcasecmp( (string) $char->chronicle_slug, (string) $chronicle ) ) {
				continue;
			}
			$linked   = (int) $char->wp_user_id > 0 && (int) $char->wp_user_id === (int) $user->ID;
			$by_email = '' !== (string) $user->user_email && '' !== (string) $char->player_email && 0 === strcasecmp( (string) $char->player_email, (string) $user->user_email );
			if ( $linked || $by_email ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The chronicle slugs the picker offers the sender, or null when it offers every chronicle.
	 *
	 * @param array $attrs The field's attributes.
	 * @param array $meta  Field values as submitted.
	 * @param array $roles The sender's role paths.
	 * @return string[]|null
	 */
	public static function chronicles_offered( array $attrs, array $meta, array $roles ) {
		$filter_by = (string) ( $attrs['filter_by'] ?? '' );
		$scopes    = is_array( $attrs['role_scopes'] ?? null ) ? $attrs['role_scopes'] : array();
		if ( '' !== $filter_by && $scopes ) {
			$chosen = isset( $meta[ $filter_by ] ) && is_scalar( $meta[ $filter_by ] ) ? (string) $meta[ $filter_by ] : '';
			if ( in_array( '*', (array) ( $scopes[ $chosen ] ?? array() ), true ) ) {
				return null;
			}
			$own = self::chronicles_held( $roles, self::OWN_CHRONICLE_ROLES );
			return $own ? $own : null;
		}
		$wanted = is_array( $attrs['roles'] ?? null ) ? $attrs['roles'] : array();
		if ( ! $wanted || in_array( '*', $wanted, true ) ) {
			return null;
		}
		$wanted = array_map( 'strtolower', array_map( 'strval', $wanted ) );
		// A storyteller is offered the chronicles an assistant storyteller is, as on the form.
		if ( in_array( 'ast', $wanted, true ) && ! in_array( 'st', $wanted, true ) ) {
			$wanted[] = 'st';
		}
		return self::chronicles_held( $roles, $wanted );
	}

	/**
	 * The chronicle slugs where the sender holds one of the given roles; a path naming only the chronicle counts for
	 * any role.
	 *
	 * @param array    $roles  The sender's role paths.
	 * @param string[] $wanted Lowercase role names (hst, staff, ...).
	 * @return string[]
	 */
	public static function chronicles_held( array $roles, array $wanted ) {
		$slugs = array();
		foreach ( $roles as $path ) {
			$parts = explode( '/', trim( strtolower( $path ), '/' ) );
			if ( count( $parts ) < 2 || 'chronicle' !== $parts[0] || '' === $parts[1] ) {
				continue;
			}
			if ( isset( $parts[2] ) && '' !== $parts[2] && ! in_array( $parts[2], $wanted, true ) ) {
				continue;
			}
			$slugs[ $parts[1] ] = true;
		}
		return array_keys( $slugs );
	}

	/**
	 * Whether any role path matches any pattern ("Chronicle/*\/HST"), case ignored.
	 *
	 * @param array        $roles    Role paths.
	 * @param array|string $patterns Patterns, or one string of them joined by "|".
	 * @return bool
	 */
	public static function matches_any( array $roles, $patterns ) {
		$patterns = is_array( $patterns ) ? $patterns : explode( '|', (string) $patterns );
		foreach ( $roles as $path ) {
			foreach ( $patterns as $pattern ) {
				if ( '' !== (string) $pattern && fnmatch( (string) $pattern, $path, FNM_CASEFOLD ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Whether a form belongs to a kind of request.
	 *
	 * @param string $form   Form slug.
	 * @param string $domain Domain slug.
	 * @return bool
	 */
	private static function form_in_domain( $form, $domain ) {
		foreach ( (array) OAT_Domain_Registry::get_forms( $domain ) as $f ) {
			$slug = is_object( $f ) ? (string) ( $f->slug ?? '' ) : (string) ( $f['slug'] ?? '' );
			if ( $slug === $form ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The person the request is from: its originator, else the signed-in user.
	 *
	 * @param object $entry Entry row.
	 * @return WP_User|null
	 */
	private static function sender( $entry ) {
		$id   = (int) ( $entry->originator_id ?? 0 );
		$user = $id ? get_userdata( $id ) : wp_get_current_user();
		return ( $user instanceof WP_User && $user->exists() ) ? $user : null;
	}

	/**
	 * The sender's AccessSchema role paths, as strings.
	 *
	 * @param WP_User|null $user The sender.
	 * @return string[]
	 */
	public static function role_paths( $user ) {
		$paths = self::lookup_roles( $user );
		return null === $paths ? array() : $paths;
	}

	/**
	 * The sender's role paths, or null when they could not be looked up (no account, no accessSchema client, or no usable
	 * answer): an empty list means the lookup worked and they hold no role.
	 *
	 * @param WP_User|null $user The sender.
	 * @return string[]|null
	 */
	private static function lookup_roles( $user ) {
		if ( ! $user || ! $user->user_email || ! function_exists( 'owc_asc_get_user_roles' ) ) {
			return null;
		}
		$response = owc_asc_get_user_roles( OAT_Authorization::CLIENT_ID, $user->user_email );
		if ( ! is_array( $response ) || ! isset( $response['roles'] ) || ! is_array( $response['roles'] ) ) {
			return null;
		}
		$list  = $response['roles'];
		$paths = array();
		foreach ( $list as $role ) {
			if ( is_string( $role ) ) {
				$paths[] = $role;
			} elseif ( is_array( $role ) && isset( $role['role_path'] ) ) {
				$paths[] = (string) $role['role_path'];
			} elseif ( is_object( $role ) && isset( $role->role_path ) ) {
				$paths[] = (string) $role->role_path;
			}
		}
		return $paths;
	}
}
