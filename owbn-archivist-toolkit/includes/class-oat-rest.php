<?php
/**
 * OAT REST API.
 *
 * Cross-site endpoints for inter-plugin communication
 * (e.g., owbn-user-bridge → OAT reassociation).
 *
 * Registered under the owbn/v1 namespace so consumer sites can reach
 * these endpoints using owbn-client's existing gateway config
 * (owc_get_remote_base + owc_get_remote_key).
 */

defined( 'ABSPATH' ) || exit;

class OAT_REST {

    const API_NAMESPACE = 'owbn/v1';

    /**
     * Register REST routes.
     */
    public static function register_routes() {
        register_rest_route( self::API_NAMESPACE, '/oat/reassociate-user', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'reassociate_user' ),
            'permission_callback' => array( __CLASS__, 'check_permission' ),
            'args'                => array(
                'old_email' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_email',
                ),
                'new_wp_user_id' => array(
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'new_email' => array(
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_email',
                    'default'           => '',
                ),
            ),
        ) );
    }

    /**
     * Use gateway auth if available, otherwise fall back to OAT admin cap.
     *
     * @param WP_REST_Request $request
     * @return bool|WP_Error
     */
    public static function check_permission( $request ) {
        // Primary: owbn-client gateway auth (x-api-key against owbn_gateway_api_key).
        if ( function_exists( 'owbn_gateway_authenticate' ) ) {
            return owbn_gateway_authenticate( $request );
        }

        // Fallback: local admin with OAT cap.
        if ( current_user_can( OAT_Constants::CAP_ADMIN ) ) {
            return true;
        }

        return new WP_Error( 'oat_unauthorized', 'Authentication required.', array( 'status' => 401 ) );
    }

    /**
     * POST /owbn/v1/oat/reassociate-user
     *
     * Accepts { old_email, new_wp_user_id, new_email (optional) }.
     * Updates oat_characters: sets wp_user_id and optionally player_email.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function reassociate_user( $request ) {
        $old_email      = $request->get_param( 'old_email' );
        $new_wp_user_id = $request->get_param( 'new_wp_user_id' );
        $new_email      = $request->get_param( 'new_email' );

        if ( ! is_email( $old_email ) ) {
            return new WP_REST_Response( array( 'error' => 'Invalid old_email.' ), 400 );
        }

        if ( ! $new_wp_user_id ) {
            return new WP_REST_Response( array( 'error' => 'Invalid new_wp_user_id.' ), 400 );
        }

        if ( $new_email && ! is_email( $new_email ) ) {
            return new WP_REST_Response( array( 'error' => 'Invalid new_email.' ), 400 );
        }

        $updated = OAT_Character::reassociate( $old_email, $new_wp_user_id, $new_email );

        return new WP_REST_Response( array(
            'success'            => true,
            'characters_updated' => $updated,
            'old_email'          => $old_email,
            'new_wp_user_id'     => $new_wp_user_id,
            'new_email'          => $new_email ?: $old_email,
        ), 200 );
    }
}
