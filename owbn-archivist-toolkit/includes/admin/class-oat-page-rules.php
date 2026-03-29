<?php

defined( 'ABSPATH' ) || exit;

class OAT_Page_Rules {

    /**
     * Render the regulation rules page.
     *
     * @return void
     */
    /**
     * Handle CSV export before any output.
     * Hooked early via admin_init so headers can be sent.
     */
    public static function maybe_export_csv() {
        if ( ! isset( $_GET['page'] ) || 'oat-rules' !== $_GET['page'] || empty( $_GET['export_csv'] ) ) {
            return;
        }
        if ( ! OAT_Authorization::check( OAT_Constants::CAP_MANAGE_RULES ) ) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'oat_regulation_rules';
        $rows  = $wpdb->get_results( "SELECT * FROM {$table} WHERE active = 1 ORDER BY genre, category, subcategory, condition_name", ARRAY_A );

        $filename = 'oat-regulation-rules-' . gmdate( 'Y-m-d' ) . '.csv';
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $out = fopen( 'php://output', 'w' );
        // Header row.
        fputcsv( $out, array( 'id', 'genre', 'category', 'subcategory', 'condition_name', 'pc_level', 'npc_level', 'controlling_coordinator', 'elevation', 'section_ref' ) );
        foreach ( $rows as $row ) {
            fputcsv( $out, array(
                $row['id'],
                $row['genre'],
                $row['category'],
                $row['subcategory'],
                $row['condition_name'],
                $row['pc_level'],
                $row['npc_level'],
                $row['controlling_coordinator'],
                $row['elevation'],
                $row['section_ref'],
            ) );
        }
        fclose( $out );
        exit;
    }

    public static function render( $embedded = false ) {
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
     * Get the base redirect URL — returns Settings tab URL when embedded, standalone URL otherwise.
     */
    private static function base_url( $extra = '' ) {
        if ( isset( $_GET['page'] ) && 'oat-settings' === $_GET['page'] ) {
            return admin_url( 'admin.php?page=oat-settings&tab=rules' . ( $extra ? '&' . $extra : '' ) );
        }
        return admin_url( 'admin.php?page=oat-rules' . ( $extra ? '&' . $extra : '' ) );
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
        } elseif ( ! empty( $_POST['oat_sync_csv'] ) ) {
            check_admin_referer( 'oat_sync_csv' );
            self::handle_csv_sync();
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
            wp_redirect( self::base_url( 'message=added' ) );
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

    private static function handle_csv_sync() {
        if ( empty( $_FILES['csv_file'] ) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK ) {
            add_settings_error( 'oat_rules', 'upload_error', 'File upload failed.', 'error' );
            return;
        }

        $file = $_FILES['csv_file']['tmp_name'];
        $result = OAT_Regulation_Rule::sync_csv( $file );

        $msg = sprintf(
            'Sync complete: %d unchanged, %d updated, %d renumbered, %d inserted, %d removed, %d skipped.',
            $result['unchanged'], $result['updated'], $result['ref_moved'],
            $result['inserted'], $result['removed'], $result['skipped']
        );
        $type = empty( $result['errors'] ) ? 'success' : 'warning';
        if ( ! empty( $result['errors'] ) ) {
            $msg .= ' Errors: ' . implode( '; ', array_slice( $result['errors'], 0, 5 ) );
        }
        add_settings_error( 'oat_rules', 'sync_result', $msg, $type );
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
            wp_redirect( self::base_url( 'message=deactivated' ) );
            exit;
        }
    }
}
