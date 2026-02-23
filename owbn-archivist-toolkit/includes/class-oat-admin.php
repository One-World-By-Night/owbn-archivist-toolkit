<?php
/**
 * OAT Admin.
 *
 * Registers the admin menu, enqueues assets, and routes to page classes.
 */

defined( 'ABSPATH' ) || exit;

class OAT_Admin {

    /**
     * Register the OAT admin menu and submenu pages.
     *
     * Archivist-only: All Entries + Regulation Rules.
     * User-facing pages (Inbox, Submit, Entry Detail) are in owbn-client OAT module.
     *
     * @return void
     */
    public static function register_menus() {
        add_menu_page(
            'OAT',
            'OAT',
            OAT_Constants::CAP_ARCHIVIST,
            'oat-entries',
            array( 'OAT_Page_Entries', 'render' ),
            'dashicons-clipboard',
            30
        );

        add_submenu_page(
            'oat-entries',
            'All Entries',
            'All Entries',
            OAT_Constants::CAP_ARCHIVIST,
            'oat-entries'
        );

        add_submenu_page(
            'oat-entries',
            'Regulation Rules',
            'Regulation Rules',
            OAT_Constants::CAP_MANAGE_RULES,
            'oat-rules',
            array( 'OAT_Page_Rules', 'render' )
        );

        add_submenu_page(
            'oat-entries',
            'Domains',
            'Domains',
            OAT_Constants::CAP_ADMIN,
            'oat-domains',
            array( 'OAT_Page_Domains', 'render' )
        );

        add_submenu_page(
            'oat-entries',
            'Workflow Steps',
            'Workflow Steps',
            OAT_Constants::CAP_ADMIN,
            'oat-workflow-steps',
            array( 'OAT_Page_Workflow_Steps', 'render' )
        );

        add_submenu_page(
            'oat-entries',
            'Form Fields',
            'Form Fields',
            OAT_Constants::CAP_ADMIN,
            'oat-form-fields',
            array( 'OAT_Page_Form_Fields', 'render' )
        );
    }

    /**
     * Enqueue admin CSS and JS on OAT pages only.
     *
     * @param string $hook The current admin page hook suffix.
     * @return void
     */
    public static function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'oat-' ) === false ) {
            return;
        }

        wp_enqueue_style(
            'oat-admin',
            OAT_PLUGIN_URL . 'assets/css/oat-admin.css',
            array(),
            OAT_VERSION
        );
    }
}
