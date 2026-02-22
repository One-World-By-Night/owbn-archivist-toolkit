<?php

defined( 'ABSPATH' ) || exit;

class OAT_Page_Entries {

    /**
     * Render the entries list page.
     *
     * @return void
     */
    public static function render() {
        $list_table = new OAT_Entry_List_Table();
        $list_table->prepare_items();

        include OAT_PLUGIN_DIR . 'templates/admin/entry-list.php';
    }
}
