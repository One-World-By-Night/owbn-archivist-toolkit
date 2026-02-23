<?php
/**
 * Plugin Name: OWbN Archivist Toolkit
 * Description: OWbN Archivist Toolkit — workflow engine for organizational requests and approvals.
 * Version:     0.1.0
 * Author:      OWbN Web Team
 * Text Domain: owbn-archivist-toolkit
 * Requires PHP: 7.4
 */

defined( 'ABSPATH' ) || exit;

define( 'OAT_VERSION', '0.1.0' );
define( 'OAT_PLUGIN_FILE', __FILE__ );
define( 'OAT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'OAT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'OAT_DB_VERSION', '1.2.0' );

// Autoloader.
require_once OAT_PLUGIN_DIR . 'includes/class-oat-loader.php';

// Activation / deactivation.
register_activation_hook( __FILE__, array( 'OAT_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'OAT_Uninstall', 'deactivate' ) );

// Admin hooks.
add_action( 'admin_menu', array( 'OAT_Admin', 'register_menus' ) );
add_action( 'admin_enqueue_scripts', array( 'OAT_Admin', 'enqueue_assets' ) );
add_action( 'admin_init', array( 'OAT_Install', 'check_version' ) );

// Domain registration.
add_filter( 'oat_register_domains', function( $domains ) {
    $domains[] = new OAT_Domain_Character_Lifecycle();
    $domains[] = new OAT_Domain_Custom_Content();
    $domains[] = new OAT_Domain_Chronicle_Reporting();
    $domains[] = new OAT_Domain_Binding_Agreements();
    $domains[] = new OAT_Domain_Disciplinary_Actions();
    $domains[] = new OAT_Domain_Governance_Records();
    return $domains;
} );

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
