<?php
/**
 * OAT Authorization.
 *
 * Bridge between accessSchema role paths and WordPress capabilities.
 * Primary: accessSchema role check. Fallback: WP capability check.
 */

defined( 'ABSPATH' ) || exit;

class OAT_Authorization {

    /**
     * accessSchema client ID — lowercase, derived from $asc_instance_prefix.
     */
    const CLIENT_ID = 'oat';

    /**
     * Check if the current user has access via accessSchema role path,
     * with WordPress capability as fallback.
     *
     * @param string      $capability WP capability name (fallback).
     * @param string|null $role_path  accessSchema role path (primary).
     * @return bool
     */
    public static function check( $capability, $role_path = null ) {
        $user = wp_get_current_user();
        if ( ! $user || ! $user->exists() ) {
            return false;
        }

        // Primary: accessSchema.
        if ( $role_path && function_exists( 'accessSchema_client_remote_check_access' ) ) {
            $result = accessSchema_client_remote_check_access(
                $user->user_email,
                $role_path,
                self::CLIENT_ID,
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
     * Get all accessSchema roles for the current user.
     *
     * @return array
     */
    public static function get_user_roles() {
        $user = wp_get_current_user();
        if ( ! $user || ! $user->exists() ) {
            return array();
        }

        if ( function_exists( 'accessSchema_client_remote_get_roles_by_email' ) ) {
            $roles = accessSchema_client_remote_get_roles_by_email(
                $user->user_email,
                self::CLIENT_ID
            );
            return is_array( $roles ) ? $roles : array();
        }

        return array();
    }

    /**
     * Get all user IDs that hold a given accessSchema role path.
     *
     * @param string $role_path accessSchema role path.
     * @return array User IDs.
     */
    public static function get_users_with_role( $role_path ) {
        if ( function_exists( 'accessSchema_client_remote_get_users_by_role' ) ) {
            $users = accessSchema_client_remote_get_users_by_role( $role_path, self::CLIENT_ID );
            return is_array( $users ) ? $users : array();
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
