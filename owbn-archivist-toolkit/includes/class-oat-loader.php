<?php
/**
 * OAT Autoloader.
 *
 * Maps OAT_* class names to file paths following the class-oat-{name}.php convention.
 */

defined( 'ABSPATH' ) || exit;

spl_autoload_register( function ( $class ) {
    // Only autoload OAT_ prefixed classes.
    if ( strpos( $class, 'OAT_' ) !== 0 ) {
        return;
    }

    // OAT_Entry        → class-oat-entry.php
    // OAT_Page_Inbox   → class-oat-page-inbox.php
    // OAT_Schema       → class-oat-schema.php
    $file = strtolower( str_replace( '_', '-', $class ) );
    $file = 'class-' . $file . '.php';

    $paths = array(
        OAT_PLUGIN_DIR . 'includes/' . $file,
        OAT_PLUGIN_DIR . 'includes/models/' . $file,
        OAT_PLUGIN_DIR . 'includes/engine/' . $file,
        OAT_PLUGIN_DIR . 'includes/engine/actions/' . $file,
        OAT_PLUGIN_DIR . 'includes/notifications/' . $file,
        OAT_PLUGIN_DIR . 'includes/domains/' . $file,
        OAT_PLUGIN_DIR . 'includes/admin/' . $file,
        OAT_PLUGIN_DIR . 'includes/db/' . $file,
    );

    foreach ( $paths as $path ) {
        if ( file_exists( $path ) ) {
            require_once $path;
            return;
        }
    }
} );
