<?php

defined( 'ABSPATH' ) || exit;

class OAT_Install {

    public static function activate() {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $installed = get_option( 'oat_db_version', '0' );

        $sql = OAT_Schema::get_tables();
        dbDelta( $sql );

        self::register_capabilities();

        if ( version_compare( $installed, '1.4.0', '<' ) ) {
            self::migrate_form_slugs();
        }

        if ( class_exists( 'OAT_Seeder' ) ) {
            OAT_Seeder::run();
        }

        update_option( 'oat_db_version', OAT_DB_VERSION );
    }

    public static function check_version() {
        $installed = get_option( 'oat_db_version', '0' );
        if ( version_compare( $installed, OAT_DB_VERSION, '<' ) ) {
            self::activate();
        }
    }

    private static function migrate_form_slugs() {
        global $wpdb;

        $ff_table = $wpdb->prefix . 'oat_form_fields';
        $f_table  = $wpdb->prefix . 'oat_forms';
        $df_table = $wpdb->prefix . 'oat_domain_forms';
        $d_table  = $wpdb->prefix . 'oat_domains';
        $now      = time();

        $wpdb->query(
            "UPDATE {$ff_table} SET form_slug = domain_slug WHERE form_slug IS NULL OR form_slug = ''"
        );

        $slugs = $wpdb->get_col( "SELECT DISTINCT form_slug FROM {$ff_table} WHERE form_slug IS NOT NULL AND form_slug != ''" );

        foreach ( $slugs as $slug ) {
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$f_table} WHERE slug = %s",
                $slug
            ) );

            if ( ! $exists ) {
                $domain_label = $wpdb->get_var( $wpdb->prepare(
                    "SELECT label FROM {$d_table} WHERE slug = %s",
                    $slug
                ) );

                $wpdb->insert( $f_table, [
                    'slug'       => $slug,
                    'label'      => $domain_label ? $domain_label : ucwords( str_replace( '_', ' ', $slug ) ),
                    'active'     => 1,
                    'sort_order' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ] );
            }
        }

        $domains = $wpdb->get_results( "SELECT id, slug FROM {$d_table}" );
        foreach ( $domains as $domain ) {
            $form_id = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$f_table} WHERE slug = %s",
                $domain->slug
            ) );

            if ( ! $form_id ) {
                continue;
            }

            $junction_exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$df_table} WHERE domain_id = %d AND form_id = %d",
                $domain->id,
                $form_id
            ) );

            if ( ! $junction_exists ) {
                $wpdb->insert( $df_table, [
                    'domain_id'  => (int) $domain->id,
                    'form_id'    => (int) $form_id,
                    'sort_order' => 0,
                    'created_at' => $now,
                ] );
            }
        }
    }

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
