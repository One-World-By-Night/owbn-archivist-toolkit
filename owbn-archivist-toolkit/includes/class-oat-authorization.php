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
        if ( function_exists( 'owc_asc_get_users_by_role' ) ) {
            $result = owc_asc_get_users_by_role( self::CLIENT_ID, $role_path );
            return is_array( $result ) ? $result : array();
        }
        return array();
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
