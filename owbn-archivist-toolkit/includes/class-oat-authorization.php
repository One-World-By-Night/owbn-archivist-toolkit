<?php
/**
 * OAT Authorization.
 *
 * Bridge between accessSchema role paths and WordPress capabilities.
 * Uses owc_asc_* wrappers from owbn-client's centralized ASC module.
 * Fallback: WP capability check when ASC is unavailable.
 */

defined( 'ABSPATH' ) || exit;

class OAT_Authorization {

    /**
     * ASC client identifier.
     */
    const CLIENT_ID = 'oat';

    /**
     * Check if the current user has access via ASC role path,
     * with WordPress capability as fallback.
     *
     * @param string      $capability WP capability name (fallback).
     * @param string|null $role_path  ASC role path (primary).
     * @return bool
     */
    public static function check( $capability, $role_path = null ) {
        $user = wp_get_current_user();
        if ( ! $user || ! $user->exists() ) {
            return false;
        }

        // Primary: ASC centralized check.
        if ( $role_path && function_exists( 'owc_asc_check_access' ) ) {
            $result = owc_asc_check_access(
                self::CLIENT_ID,
                $user->user_email,
                $role_path,
                true
            );
            if ( is_bool( $result ) ) {
                return $result;
            }
        }

        // Fallback: WordPress capability.
        return current_user_can( $capability );
    }

    /**
     * Get all ASC roles for the current user.
     *
     * @return array
     */
    public static function get_user_roles() {
        $user = wp_get_current_user();
        if ( ! $user || ! $user->exists() ) {
            return array();
        }

        if ( function_exists( 'owc_asc_get_user_roles' ) ) {
            $result = owc_asc_get_user_roles( self::CLIENT_ID, $user->user_email );
            if ( is_array( $result ) && isset( $result['roles'] ) ) {
                return $result['roles'];
            }
            return is_array( $result ) ? $result : array();
        }

        return array();
    }

    /**
     * Get all user IDs that hold a given ASC role path.
     *
     * @param string $role_path ASC role path.
     * @return array User data.
     */
    public static function get_users_with_role( $role_path ) {
        // Support a pipe-delimited UNION of role paths so a single workflow step
        // can assign more than one office (e.g. a Coordinator AND their
        // Sub-Coordinator). AccessSchema has no wildcard/prefix query, so each
        // exact path is resolved separately and the local WP ids are merged.
        if ( is_string( $role_path ) && strpos( $role_path, '|' ) !== false ) {
            $merged = array();
            foreach ( explode( '|', $role_path ) as $one ) {
                $one = trim( $one );
                if ( '' !== $one ) {
                    $merged = array_merge( $merged, self::get_users_with_role( $one ) );
                }
            }
            return array_values( array_unique( array_filter( $merged ) ) );
        }
        if ( ! function_exists( 'owc_asc_get_users_by_role' ) ) {
            return array();
        }
        $result = owc_asc_get_users_by_role( self::CLIENT_ID, $role_path );
        if ( ! is_array( $result ) ) {
            return array();
        }

        // Normalize to LOCAL WordPress user IDs. AccessSchema now returns holder
        // objects — {user_id, display_name, email, user_login, player_id} — whose
        // 'user_id' is the AccessSchema id, NOT the local WP id. Casting the whole
        // object with (int) yields 1, which silently assigned everything to WP
        // user 1 (web@owbn.net). Resolve the local user via email, then login.
        $ids = array();
        foreach ( $result as $holder ) {
            if ( is_int( $holder ) || ( is_string( $holder ) && ctype_digit( $holder ) ) ) {
                $ids[] = (int) $holder; // legacy shape: bare WP user id
                continue;
            }
            if ( is_array( $holder ) ) {
                $wp = 0;
                if ( ! empty( $holder['email'] ) ) {
                    $u  = get_user_by( 'email', $holder['email'] );
                    $wp = $u ? (int) $u->ID : 0;
                }
                if ( ! $wp && ! empty( $holder['user_login'] ) ) {
                    $u  = get_user_by( 'login', $holder['user_login'] );
                    $wp = $u ? (int) $u->ID : 0;
                }
                if ( $wp ) {
                    $ids[] = $wp;
                }
            }
        }
        return array_values( array_unique( array_filter( $ids ) ) );
    }

    /**
     * Get the character-search roles the current user qualifies for.
     *
     * Returns an array of submitter_role values the user is allowed to use,
     * ordered from most permissive to least:
     *   archivist   → all characters
     *   coordinator → all characters
     *   staff       → characters in the user's chronicle(s)
     *   player      → own characters only (always granted)
     *
     * @return array e.g. ['staff', 'player'] or ['archivist', 'coordinator', 'staff', 'player']
     */
    public static function get_character_search_roles() {
        $roles   = array();
        $user    = wp_get_current_user();

        if ( ! $user || ! $user->exists() ) {
            return array( 'player' );
        }

        // Archivist / Exec / WP Admin.
        if ( current_user_can( OAT_Constants::CAP_ARCHIVIST ) || current_user_can( OAT_Constants::CAP_EXEC_OVERSIGHT ) || current_user_can( 'manage_options' ) ) {
            $roles[] = 'archivist';
        }

        $asc_roles = self::get_user_roles();
        $has_coord = false;
        $has_staff = false;

        foreach ( $asc_roles as $role ) {
            if ( preg_match( '#^coordinator/[^/]+/(coordinator|sub-coordinator)$#i', $role ) ) {
                $has_coord = true;
            }
            // Coordinator-tier: any exec office's coordinator grants archivist scope.
            // Staff-tier: only exec/archivist/staff (the dedicated archivist staff role).
            if ( preg_match( '#^exec/(archivist|web|head-coordinator|ahc1|ahc2|admin)/coordinator$#i', $role )
                || preg_match( '#^exec/archivist/staff$#i', $role ) ) {
                if ( ! in_array( 'archivist', $roles, true ) ) {
                    $roles[] = 'archivist';
                }
            }
            if ( preg_match( '#^chronicle/[^/]+/(hst|staff|cm|ast|sug)#i', $role ) ) {
                $has_staff = true;
            }
        }

        if ( $has_coord ) {
            $roles[] = 'coordinator';
        }
        if ( $has_staff ) {
            $roles[] = 'staff';
        }

        // Player is always available.
        $roles[] = 'player';

        return $roles;
    }

    /**
     * Cap a requested submitter_role to the user's actual permissions.
     *
     * If the requested role is not in the user's allowed roles,
     * returns the most permissive role they do have.
     *
     * @param string $requested_role The role the client submitted.
     * @return string The enforced role.
     */
    public static function enforce_character_search_role( $requested_role ) {
        $allowed = self::get_character_search_roles();

        if ( in_array( $requested_role, $allowed, true ) ) {
            return $requested_role;
        }

        // Return the most permissive role the user actually has.
        return $allowed[0];
    }

    /**
     * Return a WP_Error for permission denied.
     *
     * @param string $message
     * @return WP_Error
     */
    public static function denied( $message = 'You do not have permission.' ) {
        return new WP_Error( 'oat_forbidden', $message, array( 'status' => 403 ) );
    }
}
