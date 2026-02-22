<?php

defined( 'ABSPATH' ) || exit;

class OAT_Ajax {

    /**
     * Register AJAX actions.
     *
     * @return void
     */
    public static function register() {
        add_action( 'wp_ajax_oat_search_rules', array( __CLASS__, 'search_rules' ) );
        add_action( 'wp_ajax_oat_process_action', array( __CLASS__, 'process_action' ) );
    }

    /**
     * AJAX: Search regulation rules for autocomplete.
     *
     * @return void
     */
    public static function search_rules() {
        check_ajax_referer( 'oat_nonce', 'nonce' );

        if ( ! current_user_can( OAT_Constants::CAP_SUBMIT ) ) {
            wp_send_json_error( 'Unauthorized.', 403 );
        }

        $term = isset( $_GET['term'] ) ? sanitize_text_field( $_GET['term'] ) : '';
        if ( strlen( $term ) < 2 ) {
            wp_send_json( array() );
        }

        $results = OAT_Regulation_Rule::search( $term );
        $out = array();

        foreach ( $results as $rule ) {
            $label = sprintf(
                '%s — %s — %s',
                $rule->genre,
                $rule->category,
                $rule->condition_name ? $rule->condition_name : $rule->subcategory
            );

            $out[] = array(
                'id'          => (int) $rule->id,
                'label'       => $label,
                'value'       => $label,
                'genre'       => $rule->genre,
                'category'    => $rule->category,
                'subcategory' => $rule->subcategory,
                'condition'   => $rule->condition_name,
                'pc_level'    => $rule->pc_level,
                'npc_level'   => $rule->npc_level,
                'coordinator' => $rule->controlling_coordinator,
                'elevation'   => (int) $rule->elevation,
            );
        }

        wp_send_json( $out );
    }

    /**
     * AJAX: Process workflow action.
     *
     * @return void
     */
    public static function process_action() {
        check_ajax_referer( 'oat_nonce', 'nonce' );

        if ( ! current_user_can( OAT_Constants::CAP_SUBMIT ) ) {
            wp_send_json_error( 'Unauthorized.', 403 );
        }

        $entry_id = isset( $_POST['entry_id'] ) ? absint( $_POST['entry_id'] ) : 0;
        $action   = isset( $_POST['action_type'] ) ? sanitize_text_field( $_POST['action_type'] ) : '';
        $note     = isset( $_POST['note'] ) ? sanitize_textarea_field( $_POST['note'] ) : '';
        $user_id  = get_current_user_id();

        if ( ! $entry_id || ! $action ) {
            wp_send_json_error( 'Missing entry_id or action_type.' );
        }

        // Validate action is available to this user on this entry.
        $entry     = OAT_Entry::find( $entry_id );
        $assignees = $entry ? OAT_Assignee::for_entry( $entry_id ) : array();
        $available = $entry ? OAT_Page_Entry::get_available_actions( $entry, $user_id, $assignees ) : array();
        if ( ! in_array( $action, $available, true ) ) {
            wp_send_json_error( 'This action is not available.' );
        }

        $data = array( 'note' => $note );

        // Additional data for specific actions.
        if ( ! empty( $_POST['vote_reference'] ) ) {
            $data['vote_reference'] = sanitize_text_field( $_POST['vote_reference'] );
        }
        if ( ! empty( $_POST['additional_seconds'] ) ) {
            $data['additional_seconds'] = absint( $_POST['additional_seconds'] );
        }
        if ( ! empty( $_POST['new_user_id'] ) ) {
            $data['target_user_id'] = absint( $_POST['new_user_id'] );
        }
        if ( ! empty( $_POST['delegate_user_id'] ) ) {
            $data['target_user_id'] = absint( $_POST['delegate_user_id'] );
        }

        $result = OAT_Workflow_Engine::process_action( $entry_id, $action, $user_id, $data );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success( array( 'message' => 'Action completed.' ) );
    }
}
