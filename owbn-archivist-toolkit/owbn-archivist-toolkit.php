<?php
/**
 * Plugin Name: OWbN Archivist Toolkit
 * Plugin URI: https://github.com/One-World-By-Night/owbn-archivist-toolkit
 * Description: Workflow engine for organizational requests and approvals.
 * Version:     1.8.4
 * Author:      OWbN Web Team
 * License:     GPL-2.0-or-later
 * Text Domain: owbn-archivist-toolkit
 * Requires PHP: 7.4
 */

defined( 'ABSPATH' ) || exit;

define( 'OAT_VERSION', '1.8.4' );
define( 'OAT_PLUGIN_FILE', __FILE__ );
define( 'OAT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'OAT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'OAT_DB_VERSION', '1.7.0' );

// Autoloader.
require_once OAT_PLUGIN_DIR . 'includes/class-oat-loader.php';

// Activation / deactivation.
register_activation_hook( __FILE__, array( 'OAT_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'OAT_Uninstall', 'deactivate' ) );

// Admin hooks.
add_action( 'admin_menu', array( 'OAT_Admin', 'register_menus' ) );
add_action( 'admin_menu', array( 'OAT_Admin', 'reorder_submenu' ), 999 );
add_action( 'admin_enqueue_scripts', array( 'OAT_Admin', 'enqueue_assets' ) );
add_action( 'admin_init', array( 'OAT_Install', 'check_version' ) );
add_action( 'admin_init', array( 'OAT_Page_Rules', 'maybe_export_csv' ) );
add_action( 'admin_init', array( 'OAT_Page_Reports', 'maybe_export_csv' ) );
add_action( 'admin_init', array( 'OAT_Page_Taxonomy', 'maybe_handle_actions' ) );
add_action( 'admin_init', array( 'OAT_Page_Settings', 'maybe_save' ) );
OAT_Page_Taxonomy::init_ajax();

// AJAX handlers.
OAT_Ajax::init();

// REST API (cross-site endpoints).
add_action( 'rest_api_init', array( 'OAT_REST', 'register_routes' ) );
add_action( 'rest_api_init', array( 'OAT_REST_Registry', 'register_routes' ) );

// Register with centralized ASC module in owbn-client.
add_action( 'init', function() {
    if ( function_exists( 'owc_asc_register_client' ) ) {
        owc_asc_register_client( 'oat', 'OWbN Archivist Toolkit' );
    }
} );

// Domain registration.
add_filter( 'oat_register_domains', function( $domains ) {
    $domains[] = new OAT_Domain_Character_Lifecycle();
    $domains[] = new OAT_Domain_Custom_Content();
    $domains[] = new OAT_Domain_Chronicle_Actions();
    $domains[] = new OAT_Domain_Binding_Agreements();
    $domains[] = new OAT_Domain_Disciplinary_Actions();
    $domains[] = new OAT_Domain_Governance_Records();
    $domains[] = new OAT_Domain_Player_Actions();
    return $domains;
} );

// Record snapshot on approval (D-062).
OAT_Record_Snapshot::init();

// Registry visibility — auto-grant hooks.
OAT_Registry_Hooks::init();

// Timer processing — WP-Cron hourly (D-051).
add_action( 'oat_process_timers', array( 'OAT_Timer_Engine', 'process_expired_timers' ) );
if ( ! wp_next_scheduled( 'oat_process_timers' ) ) {
    wp_schedule_event( time(), 'hourly', 'oat_process_timers' );
}

// Belt-and-suspenders: also process on OAT admin page load (D-051).
add_action( 'admin_init', function() {
    if ( isset( $_GET['page'] ) && strpos( $_GET['page'], 'oat-' ) === 0 ) {
        OAT_Timer_Engine::process_expired_timers();
    }
} );
