<?php
/**
 * OAT Installation / Activation.
 *
 * Creates database tables via dbDelta(), registers capabilities,
 * and tracks schema version for future migrations.
 */

defined( 'ABSPATH' ) || exit;

class OAT_Install {

    /**
     * Run on plugin activation.
     *
     * @return void
     */
    public static function activate() {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = OAT_Schema::get_tables();
        dbDelta( $sql );

        self::register_capabilities();

        update_option( 'oat_db_version', OAT_DB_VERSION );
    }

    /**
     * Check if DB schema needs updating (admin_init hook).
     *
     * @return void
     */
    public static function check_version() {
        $installed = get_option( 'oat_db_version', '0' );
        if ( version_compare( $installed, OAT_DB_VERSION, '<' ) ) {
            self::activate();
        }
    }

    /**
     * Add OAT capabilities to the administrator role.
     *
     * @return void
     */
    private static function register_capabilities() {
        $admin = get_role( 'administrator' );
        if ( ! $admin ) {
            return;
        }
        foreach ( OAT_Constants::get_capabilities() as $cap ) {
            $admin->add_cap( $cap );
        }
    }
}
