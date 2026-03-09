<?php
/**
 * OAT AJAX Handlers.
 *
 * Provides wp_ajax_ endpoints for front-end form field widgets
 * (character picker, etc.).
 */

defined( 'ABSPATH' ) || exit;

class OAT_Ajax {

    /**
     * Register AJAX hooks.
     */
    public static function init() {
        add_action( 'wp_ajax_oat_character_search', array( __CLASS__, 'character_search' ) );
        add_action( 'wp_ajax_oat_save_domain_order', array( __CLASS__, 'save_domain_order' ) );
        add_action( 'wp_ajax_oat_get_domain_forms', array( __CLASS__, 'get_domain_forms' ) );
    }

    /**
     * AJAX: Search characters scoped by submitter role.
     *
     * Expected POST params:
     *   term           - Search string (min 2 chars).
     *   submitter_role - player|staff|coordinator|archivist.
     *   _ajax_nonce    - Nonce for oat_character_search.
     *
     * Returns JSON array of { id, text, meta } objects for Select2.
     */
    public static function character_search() {
        check_ajax_referer( 'oat_character_search', '_ajax_nonce' );

        $term = isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '';
        $role = isset( $_POST['submitter_role'] ) ? sanitize_text_field( wp_unslash( $_POST['submitter_role'] ) ) : 'player';

        if ( strlen( $term ) < 2 ) {
            wp_send_json_success( array() );
        }

        // Enforce: cap the requested role to the user's actual permissions.
        $role = OAT_Authorization::enforce_character_search_role( $role );

        $results = OAT_Character::for_user_scoped( $term, $role );

        $out = array();
        foreach ( $results as $row ) {
            $out[] = array(
                'id'   => $row->id,
                'text' => $row->character_name ?: '(unnamed)',
                'meta' => array(
                    'uuid'           => $row->uuid,
                    'player_name'    => $row->player_name,
                    'player_email'   => $row->player_email,
                    'chronicle_slug' => $row->chronicle_slug,
                    'creature_type'  => $row->creature_type ?? '',
                    'status'         => $row->status ?? 'active',
                ),
            );
        }

        wp_send_json_success( $out );
    }

    /**
     * AJAX: Get forms available for a domain.
     *
     * Expected POST params:
     *   domain_slug  - Domain slug.
     *   _ajax_nonce  - Nonce for oat_nonce.
     *
     * Returns JSON array of { id, slug, label } objects.
     */
    public static function get_domain_forms() {
        check_ajax_referer( 'oat_nonce', '_ajax_nonce' );

        $domain_slug = isset( $_POST['domain_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['domain_slug'] ) ) : '';

        if ( '' === $domain_slug ) {
            wp_send_json_error( 'No domain specified.' );
        }

        $forms = OAT_Domain_Registry::get_forms( $domain_slug );

        $out = array();
        foreach ( $forms as $form ) {
            $out[] = array(
                'id'    => (int) $form->id,
                'slug'  => $form->slug,
                'label' => $form->label,
            );
        }

        wp_send_json_success( $out );
    }

    /**
     * AJAX: Save domain sort order after drag-and-drop reorder.
     *
     * Expected POST params:
     *   order[]      - Array of domain IDs in desired order.
     *   _ajax_nonce  - Nonce for oat_domain_order.
     */
    public static function save_domain_order() {
        check_ajax_referer( 'oat_domain_order', '_ajax_nonce' );

        if ( ! OAT_Authorization::check( OAT_Constants::CAP_ADMIN ) ) {
            wp_send_json_error( 'Permission denied.' );
        }

        $order = isset( $_POST['order'] ) ? array_map( 'absint', $_POST['order'] ) : array();

        if ( empty( $order ) ) {
            wp_send_json_error( 'No order provided.' );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'oat_domains';
        $now   = time();

        foreach ( $order as $position => $id ) {
            $wpdb->update(
                $table,
                array( 'sort_order' => $position, 'updated_at' => $now ),
                array( 'id' => $id ),
                array( '%d', '%d' ),
                array( '%d' )
            );
        }

        wp_send_json_success();
    }
}
