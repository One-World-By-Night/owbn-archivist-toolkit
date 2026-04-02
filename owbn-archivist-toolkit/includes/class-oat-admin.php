<?php

defined( 'ABSPATH' ) || exit;

class OAT_Admin {

    public static function register_menus() {
        add_menu_page(
            'Archivist Dashboard',
            'Archivist',
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

        // Regulation Rules: hidden page (accessed via Settings > Regulation Rules tab).
        add_submenu_page( null, 'Regulation Rules', 'Regulation Rules', OAT_Constants::CAP_MANAGE_RULES, 'oat-rules', array( 'OAT_Page_Rules', 'render' ) );

        // Creature Taxonomy: hidden page (accessed via Settings > Creature Taxonomy tab).
        add_submenu_page( null, 'Creature Taxonomy', 'Creature Taxonomy', OAT_Constants::CAP_ARCHIVIST, 'oat-taxonomy', array( 'OAT_Page_Taxonomy', 'render' ) );

        // Settings: General config (character creation roles, etc.).
        add_submenu_page(
            'oat-entries',
            'Settings',
            'Settings',
            OAT_Constants::CAP_ARCHIVIST,
            'oat-settings',
            array( 'OAT_Page_Settings', 'render' )
        );

        // Toolbox: Domains, Forms, Workflow Steps, Form Fields — consolidated as tabs.
        add_submenu_page(
            'oat-entries',
            'Toolbox',
            'Toolbox',
            OAT_Constants::CAP_ADMIN,
            'oat-toolbox',
            array( 'OAT_Page_Toolbox', 'render' )
        );

        // Hidden pages for direct access (edit screens link here).
        add_submenu_page( null, 'Domains', 'Domains', OAT_Constants::CAP_ADMIN, 'oat-domains', array( 'OAT_Page_Domains', 'render' ) );
        add_submenu_page( null, 'Forms', 'Forms', OAT_Constants::CAP_ADMIN, 'oat-forms', array( 'OAT_Page_Forms', 'render' ) );
        add_submenu_page( null, 'Workflow Steps', 'Workflow Steps', OAT_Constants::CAP_ADMIN, 'oat-workflow-steps', array( 'OAT_Page_Workflow_Steps', 'render' ) );
        add_submenu_page( null, 'Form Fields', 'Form Fields', OAT_Constants::CAP_ADMIN, 'oat-form-fields', array( 'OAT_Page_Form_Fields', 'render' ) );

        add_submenu_page(
            'oat-entries',
            'Details',
            'Details',
            'read',
            'oat-reports',
            array( 'OAT_Page_Reports', 'render' )
        );
    }

    /**
     * Reorder the OAT submenu so user-facing items come first.
     * Runs at priority 999 to ensure all plugins have registered.
     */
    public static function reorder_submenu() {
        global $submenu;

        if ( ! isset( $submenu['oat-entries'] ) ) {
            return;
        }

        $items = $submenu['oat-entries'];

        // Define the desired order by slug.
        $order = array(
            'owc-oat-workspace',
            'oat-entries',
            'owc-oat-reports',
            'oat-reports',
            'oat-settings',
            'oat-toolbox',
        );

        $sorted   = array();
        $leftover = array();

        // Place known items in order.
        foreach ( $order as $slug ) {
            foreach ( $items as $item ) {
                if ( $item[2] === $slug ) {
                    $sorted[] = $item;
                    break;
                }
            }
        }

        // Append anything not in the order list.
        foreach ( $items as $item ) {
            if ( ! in_array( $item[2], $order, true ) ) {
                $leftover[] = $item;
            }
        }

        $submenu['oat-entries'] = array_merge( $sorted, $leftover );
    }

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

        wp_enqueue_script( 'select2' );
        wp_enqueue_style( 'select2' );
        wp_enqueue_script( 'jquery-ui-autocomplete' );

        wp_enqueue_script(
            'oat-character-picker',
            OAT_PLUGIN_URL . 'assets/js/oat-character-picker.js',
            array( 'jquery', 'select2' ),
            OAT_VERSION,
            true
        );

        wp_localize_script( 'oat-character-picker', 'oatCharacterPicker', array(
            'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
            'nonce'        => wp_create_nonce( 'oat_character_search' ),
            'allowedRoles' => OAT_Authorization::get_character_search_roles(),
            'i18n'         => array(
                'searchPlaceholder' => __( 'Type to search characters...', 'owbn-archivist-toolkit' ),
            ),
        ) );

        wp_enqueue_script(
            'oat-creature-picker',
            OAT_PLUGIN_URL . 'assets/js/oat-creature-picker.js',
            array( 'jquery' ),
            OAT_VERSION,
            true
        );

        wp_localize_script( 'oat-creature-picker', 'oatCreaturePicker', array(
            'nonce' => wp_create_nonce( 'oat_creature_picker' ),
        ) );

        // Domain drag-and-drop reordering (domains list page only).
        if ( isset( $_GET['page'] ) && 'oat-domains' === $_GET['page'] && empty( $_GET['action'] ) ) {
            wp_enqueue_script( 'jquery-ui-sortable' );
            wp_enqueue_script(
                'oat-domain-sort',
                OAT_PLUGIN_URL . 'assets/js/oat-domain-sort.js',
                array( 'jquery', 'jquery-ui-sortable' ),
                OAT_VERSION,
                true
            );
            wp_localize_script( 'oat-domain-sort', 'oatDomainSort', array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'oat_domain_order' ),
            ) );
        }
    }
}
