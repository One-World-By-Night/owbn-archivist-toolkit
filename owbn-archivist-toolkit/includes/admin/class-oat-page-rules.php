<?php

defined( 'ABSPATH' ) || exit;

class OAT_Page_Rules {

    /**
     * Render the regulation rules page.
     *
     * @return void
     */
    public static function render() {
        // Permission check.
        if ( ! OAT_Authorization::check( OAT_Constants::CAP_MANAGE_RULES ) ) {
            wp_die( 'You do not have permission to manage regulation rules.' );
        }

        // Handle actions.
        if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
            self::handle_post();
        }

        $list_table = new OAT_Rules_List_Table();
        $list_table->prepare_items();

        $message = isset( $_GET['message'] ) ? sanitize_text_field( $_GET['message'] ) : '';

        include OAT_PLUGIN_DIR . 'templates/admin/regulation-rules.php';
    }

    /**
     * Handle POST actions (add, edit, deactivate, CSV import).
     *
     * @return void
     */
    private static function handle_post() {
        if ( ! empty( $_POST['oat_add_rule'] ) ) {
            check_admin_referer( 'oat_add_rule' );
            self::handle_add_rule();
        } elseif ( ! empty( $_POST['oat_import_csv'] ) ) {
            check_admin_referer( 'oat_import_csv' );
            self::handle_csv_import();
        } elseif ( ! empty( $_POST['oat_deactivate_rule'] ) ) {
            check_admin_referer( 'oat_deactivate_rule' );
            self::handle_deactivate();
        }
    }

    /**
     * Add a new regulation rule.
     *
     * @return void
     */
    private static function handle_add_rule() {
        $data = array(
            'genre'                   => sanitize_text_field( $_POST['genre'] ),
            'category'                => sanitize_text_field( $_POST['category'] ),
            'subcategory'             => sanitize_text_field( $_POST['subcategory'] ),
            'condition_name'          => sanitize_text_field( $_POST['condition_name'] ),
            'pc_level'                => sanitize_text_field( $_POST['pc_level'] ),
            'npc_level'               => sanitize_text_field( $_POST['npc_level'] ),
            'controlling_coordinator' => sanitize_text_field( $_POST['controlling_coordinator'] ),
            'elevation'               => ! empty( $_POST['elevation'] ) ? 1 : 0,
        );

        $id = OAT_Regulation_Rule::create( $data );
        if ( $id > 0 ) {
            wp_redirect( admin_url( 'admin.php?page=oat-rules&message=added' ) );
            exit;
        }

        add_settings_error( 'oat_rules', 'add_failed', 'Failed to add rule. Check required fields.', 'error' );
    }

    /**
     * Handle CSV import.
     *
     * @return void
     */
    private static function handle_csv_import() {
        if ( empty( $_FILES['csv_file'] ) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK ) {
            add_settings_error( 'oat_rules', 'upload_error', 'File upload failed.', 'error' );
            return;
        }

        $file = $_FILES['csv_file']['tmp_name'];
        $result = OAT_Regulation_Rule::import_csv( $file );

        if ( is_wp_error( $result ) ) {
            add_settings_error( 'oat_rules', 'import_error', $result->get_error_message(), 'error' );
        } else {
            $msg = sprintf( 'Import complete: %d inserted, %d skipped.', $result['inserted'], $result['skipped'] );
            add_settings_error( 'oat_rules', 'import_success', $msg, 'success' );
        }
    }

    /**
     * Deactivate a rule (not delete — D-043).
     *
     * @return void
     */
    private static function handle_deactivate() {
        $rule_id = absint( $_POST['rule_id'] );
        if ( $rule_id > 0 ) {
            OAT_Regulation_Rule::deactivate( $rule_id );
            wp_redirect( admin_url( 'admin.php?page=oat-rules&message=deactivated' ) );
            exit;
        }
    }
}
