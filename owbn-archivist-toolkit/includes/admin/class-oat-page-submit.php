<?php

defined( 'ABSPATH' ) || exit;

class OAT_Page_Submit {

    /**
     * Render the submission form.
     *
     * @return void
     */
    public static function render() {
        // Handle POST.
        if ( $_SERVER['REQUEST_METHOD'] === 'POST' && ! empty( $_POST['oat_domain'] ) ) {
            $result = self::handle_submit();
            if ( is_int( $result ) && $result > 0 ) {
                wp_redirect( admin_url( 'admin.php?page=oat-entry&entry_id=' . $result . '&created=1' ) );
                exit;
            }
        }

        $domains = OAT_Domain_Registry::get_all();
        $selected_domain = isset( $_GET['domain'] ) ? sanitize_text_field( $_GET['domain'] ) : '';

        include OAT_PLUGIN_DIR . 'templates/admin/submit-form.php';
    }

    /**
     * Handle submission POST.
     *
     * @return int|WP_Error Entry ID on success, WP_Error on failure.
     */
    private static function handle_submit() {
        check_admin_referer( 'oat_submit' );

        $user_id = get_current_user_id();
        $domain_slug = sanitize_text_field( $_POST['oat_domain'] );

        $domain = OAT_Domain_Registry::get( $domain_slug );
        if ( ! $domain ) {
            add_settings_error( 'oat_submit', 'invalid_domain', 'Invalid domain.', 'error' );
            return new WP_Error( 'invalid_domain', 'Invalid domain.' );
        }

        // Collect meta from POST.
        $meta_keys = $domain->get_meta_keys();
        $meta = array();
        foreach ( $meta_keys as $key => $config ) {
            if ( isset( $_POST[ 'oat_meta_' . $key ] ) ) {
                $meta[ $key ] = sanitize_textarea_field( $_POST[ 'oat_meta_' . $key ] );
            }
        }

        // Validate.
        $mock_entry = (object) array( 'domain' => $domain_slug );
        $valid = $domain->validate( $mock_entry, $meta );
        if ( is_wp_error( $valid ) ) {
            add_settings_error( 'oat_submit', 'validation', $valid->get_error_message(), 'error' );
            return $valid;
        }

        // Create entry.
        $entry_data = array(
            'domain'        => $domain_slug,
            'current_step'  => 'submit',
            'originator_id' => $user_id,
        );
        if ( ! empty( $_POST['oat_chronicle_slug'] ) ) {
            $entry_data['chronicle_slug'] = sanitize_text_field( $_POST['oat_chronicle_slug'] );
        }
        if ( ! empty( $_POST['oat_coordinator_genre'] ) ) {
            $entry_data['coordinator_genre'] = sanitize_text_field( $_POST['oat_coordinator_genre'] );
        }

        $entry_id = OAT_Entry::create( $entry_data );
        if ( ! $entry_id ) {
            add_settings_error( 'oat_submit', 'create_failed', 'Failed to create entry.', 'error' );
            return new WP_Error( 'create_failed', 'Failed to create entry.' );
        }

        // Save meta.
        foreach ( $meta as $key => $value ) {
            OAT_Entry_Meta::set( $entry_id, $key, $value );
        }

        // Link regulation rules.
        if ( ! empty( $_POST['oat_rule_ids'] ) ) {
            $rule_ids = array_map( 'absint', (array) $_POST['oat_rule_ids'] );
            foreach ( $rule_ids as $rule_id ) {
                if ( $rule_id > 0 ) {
                    OAT_Entry_Rule::create( $entry_id, $rule_id );
                }
            }

            // Set requires_coord and regulation_level from highest rule.
            self::set_regulation_meta( $entry_id, $rule_ids );
        }

        // Process submit action.
        $note = isset( $_POST['oat_note'] ) ? sanitize_textarea_field( $_POST['oat_note'] ) : '';
        $result = OAT_Workflow_Engine::process_action( $entry_id, 'submit', $user_id, array( 'note' => $note ) );

        if ( is_wp_error( $result ) ) {
            add_settings_error( 'oat_submit', 'submit_failed', $result->get_error_message(), 'error' );
            return $result;
        }

        return $entry_id;
    }

    /**
     * Determine regulation meta from linked rules.
     *
     * @param int   $entry_id
     * @param array $rule_ids
     * @return void
     */
    private static function set_regulation_meta( $entry_id, $rule_ids ) {
        $requires_coord = '0';
        $regulation_level = '';

        // Priority: council_vote > disallowed > coordinator_approval > coordinator_notify.
        $priority = array(
            'council_vote'         => 4,
            'disallowed'           => 3,
            'coordinator_approval' => 2,
            'coordinator_notify'   => 1,
        );
        $highest = 0;

        foreach ( $rule_ids as $rule_id ) {
            $rule = OAT_Regulation_Rule::find( $rule_id );
            if ( ! $rule ) {
                continue;
            }

            $level = $rule->pc_level;
            if ( $level && isset( $priority[ $level ] ) && $priority[ $level ] > $highest ) {
                $highest = $priority[ $level ];
                $regulation_level = $level;
            }
        }

        if ( $highest >= 2 ) {
            $requires_coord = '1';
        }

        OAT_Entry_Meta::set( $entry_id, 'requires_coord', $requires_coord );
        if ( $regulation_level ) {
            OAT_Entry_Meta::set( $entry_id, 'regulation_level', $regulation_level );
        }
    }
}
