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
     * @return void
     */
    public static function register_menus() {
        add_menu_page(
            'OAT Dashboard',
            'OAT',
            OAT_Constants::CAP_SUBMIT,
            'oat-dashboard',
            array( __CLASS__, 'render_dashboard' ),
            'dashicons-clipboard',
            30
        );

        add_submenu_page(
            'oat-dashboard',
            'Dashboard',
            'Dashboard',
            OAT_Constants::CAP_SUBMIT,
            'oat-dashboard'
        );

        add_submenu_page(
            'oat-dashboard',
            'Inbox',
            'Inbox',
            OAT_Constants::CAP_SUBMIT,
            'oat-inbox',
            array( 'OAT_Page_Inbox', 'render' )
        );

        add_submenu_page(
            'oat-dashboard',
            'New Submission',
            'New Submission',
            OAT_Constants::CAP_SUBMIT,
            'oat-submit',
            array( 'OAT_Page_Submit', 'render' )
        );

        add_submenu_page(
            'oat-dashboard',
            'All Entries',
            'All Entries',
            OAT_Constants::CAP_SUBMIT,
            'oat-entries',
            array( 'OAT_Page_Entries', 'render' )
        );

        add_submenu_page(
            'oat-dashboard',
            'Regulation Rules',
            'Regulation Rules',
            OAT_Constants::CAP_MANAGE_RULES,
            'oat-rules',
            array( 'OAT_Page_Rules', 'render' )
        );

        // Hidden page: entry detail (no menu item, accessed via link).
        add_submenu_page(
            null,
            'Entry Detail',
            'Entry Detail',
            OAT_Constants::CAP_SUBMIT,
            'oat-entry',
            array( 'OAT_Page_Entry', 'render' )
        );
    }

    /**
     * Render the dashboard page.
     *
     * @return void
     */
    public static function render_dashboard() {
        $user_id = get_current_user_id();
        $inbox_count = count( OAT_Assignee::inbox( $user_id ) );
        $domains = OAT_Domain_Registry::get_all();

        echo '<div class="wrap">';
        echo '<h1>OWbN Archivist Toolkit</h1>';
        echo '<p>Version ' . esc_html( OAT_VERSION ) . '</p>';

        echo '<div class="oat-dashboard-cards">';
        echo '<div class="oat-card"><h3>Inbox</h3><p class="oat-count">' . esc_html( $inbox_count ) . '</p>';
        echo '<a href="' . esc_url( admin_url( 'admin.php?page=oat-inbox' ) ) . '" class="button">View Inbox</a></div>';

        echo '<div class="oat-card"><h3>New Submission</h3>';
        echo '<a href="' . esc_url( admin_url( 'admin.php?page=oat-submit' ) ) . '" class="button button-primary">Submit</a></div>';

        echo '<div class="oat-card"><h3>Domains</h3><p class="oat-count">' . count( $domains ) . '</p>';
        echo '<a href="' . esc_url( admin_url( 'admin.php?page=oat-entries' ) ) . '" class="button">Browse Entries</a></div>';
        echo '</div>';

        echo '</div>';
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

        wp_enqueue_script(
            'oat-admin',
            OAT_PLUGIN_URL . 'assets/js/oat-admin.js',
            array( 'jquery' ),
            OAT_VERSION,
            true
        );

        wp_localize_script( 'oat-admin', 'oat_ajax', array(
            'url'   => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'oat_nonce' ),
        ) );

        // Regulation picker on submit page.
        if ( strpos( $hook, 'oat-submit' ) !== false ) {
            wp_enqueue_script( 'jquery-ui-autocomplete' );
            wp_enqueue_script(
                'oat-regulation-picker',
                OAT_PLUGIN_URL . 'assets/js/oat-regulation-picker.js',
                array( 'jquery', 'jquery-ui-autocomplete' ),
                OAT_VERSION,
                true
            );
        }
    }
}
