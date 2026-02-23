<?php

defined( 'ABSPATH' ) || exit;

class OAT_Page_Entries {

    /**
     * Render the entries list page.
     *
     * @return void
     */
    public static function render() {
        // Handle delete action (D-030, 7.9b).
        if ( isset( $_GET['action'] ) && 'delete' === $_GET['action'] ) {
            self::handle_delete();
        }

        $list_table = new OAT_Entry_List_Table();
        $list_table->prepare_items();

        include OAT_PLUGIN_DIR . 'templates/admin/entry-list.php';
    }

    /**
     * Handle entry deletion with guard checks.
     *
     * @return void
     */
    private static function handle_delete() {
        $entry_id = isset( $_GET['entry_id'] ) ? absint( $_GET['entry_id'] ) : 0;
        if ( ! $entry_id ) {
            return;
        }

        check_admin_referer( 'oat_delete_entry_' . $entry_id );

        $entry = OAT_Entry::find( $entry_id );
        if ( ! $entry ) {
            wp_redirect( admin_url( 'admin.php?page=oat-entries&message=not_found' ) );
            exit;
        }

        if ( ! OAT_Entry::can_delete( $entry ) ) {
            wp_redirect( admin_url( 'admin.php?page=oat-entries&message=cannot_delete' ) );
            exit;
        }

        OAT_Entry::delete_cascade( $entry_id );

        wp_redirect( admin_url( 'admin.php?page=oat-entries&message=deleted' ) );
        exit;
    }
}
