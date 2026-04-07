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

        self::seed_fame_registry();

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

    private static function seed_fame_registry() {
        global $wpdb;

        $f_table  = $wpdb->prefix . 'oat_forms';
        $ff_table = $wpdb->prefix . 'oat_form_fields';
        $df_table = $wpdb->prefix . 'oat_domain_forms';
        $d_table  = $wpdb->prefix . 'oat_domains';
        $now      = time();

        // 1. Ensure cl_fame_registry form exists.
        $form = $wpdb->get_row( "SELECT id FROM {$f_table} WHERE slug = 'cl_fame_registry'" );
        if ( ! $form ) {
            $wpdb->insert( $f_table, [
                'slug' => 'cl_fame_registry', 'label' => 'Fame Registry',
                'active' => 1, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now,
            ] );
            $form_id = $wpdb->insert_id;
        } else {
            $form_id = (int) $form->id;
        }

        // 2. Link to character_lifecycle domain.
        $domain = $wpdb->get_row( "SELECT id FROM {$d_table} WHERE slug = 'character_lifecycle'" );
        if ( $domain && $form_id ) {
            $linked = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$df_table} WHERE domain_id = %d AND form_id = %d", $domain->id, $form_id
            ) );
            if ( ! $linked ) {
                $wpdb->insert( $df_table, [
                    'domain_id' => (int) $domain->id, 'form_id' => $form_id,
                    'sort_order' => 0, 'created_at' => $now,
                ] );
            }
        }

        // 3. Add fame_registry to action_type options if missing.
        $at_field = $wpdb->get_row( "SELECT id, options_json FROM {$ff_table} WHERE form_slug = 'character_lifecycle' AND field_key = 'action_type' AND active = 1" );
        if ( $at_field ) {
            $opts = json_decode( $at_field->options_json, true ) ?: [];
            if ( ! isset( $opts['fame_registry'] ) ) {
                $opts['fame_registry'] = 'Fame Registry';
                $wpdb->update( $ff_table, [ 'options_json' => wp_json_encode( $opts ) ], [ 'id' => $at_field->id ] );
            }
        }

        // 4. Seed form fields (upsert — skip if field already exists).
        $influences = [
            'bureaucracy' => 'Bureaucracy', 'church' => 'Church', 'finance' => 'Finance',
            'health' => 'Health', 'high_society' => 'High Society', 'industry' => 'Industry',
            'legal' => 'Legal', 'media' => 'Media', 'occult' => 'Occult',
            'police' => 'Police', 'political' => 'Political', 'street' => 'Street',
            'transportation' => 'Transportation', 'underworld' => 'Underworld', 'university' => 'University',
        ];

        $fields = [
            [ 'field_key' => 'chronicle_slug', 'field_type' => 'chronicle_picker', 'label' => 'Chronicle', 'sort_order' => 10, 'required' => 1, 'context' => 'submit' ],
            [ 'field_key' => 'submitter_name', 'field_type' => 'auto_prop', 'label' => 'Submitted By', 'sort_order' => 20, 'context' => 'submit' ],
            [ 'field_key' => 'submitter_email', 'field_type' => 'auto_prop', 'label' => 'Submitted By Email', 'sort_order' => 30, 'context' => 'submit' ],
            [ 'field_key' => 'character_name', 'field_type' => 'character_picker', 'label' => 'Character', 'sort_order' => 40, 'required' => 1, 'context' => 'submit' ],
            [ 'field_key' => 'fame_public_id', 'field_type' => 'text', 'label' => 'Public Identity', 'sort_order' => 50, 'required' => 1, 'context' => 'submit' ],
            [ 'field_key' => 'fame_level', 'field_type' => 'select', 'label' => 'Fame Level', 'sort_order' => 60, 'required' => 1, 'context' => 'submit',
              'options_json' => wp_json_encode( [ '1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5' ] ) ],
            [ 'field_key' => 'fame_influence_areas', 'field_type' => 'checkboxes', 'label' => 'Influence Areas', 'sort_order' => 70, 'required' => 1, 'context' => 'submit',
              'options_json' => wp_json_encode( $influences ) ],
            [ 'field_key' => 'fame_description', 'field_type' => 'htmlarea', 'label' => 'Description', 'sort_order' => 80, 'required' => 1, 'context' => 'submit' ],
            [ 'field_key' => 'fame_scope', 'field_type' => 'htmlarea', 'label' => 'Scope', 'sort_order' => 90, 'context' => 'submit' ],
            [ 'field_key' => 'action_type', 'field_type' => 'hidden', 'label' => 'Action Type', 'sort_order' => 100, 'context' => 'submit',
              'default_value' => 'fame_registry' ],
            [ 'field_key' => 'ru_sub_status', 'field_type' => 'hidden', 'label' => 'Status', 'sort_order' => 110, 'context' => 'submit',
              'default_value' => 'active' ],
            // Review fields.
            [ 'field_key' => 'section_review', 'field_type' => 'heading', 'label' => 'Staff Review', 'sort_order' => 10, 'context' => 'review' ],
            [ 'field_key' => 'review_note', 'field_type' => 'textarea', 'label' => 'Review Note', 'sort_order' => 20, 'context' => 'review' ],
            [ 'field_key' => 'section_resolve', 'field_type' => 'heading', 'label' => 'Resolution', 'sort_order' => 10, 'context' => 'resolve' ],
            [ 'field_key' => 'resolution_note', 'field_type' => 'textarea', 'label' => 'Resolution Notes', 'sort_order' => 20, 'context' => 'resolve' ],
            [ 'field_key' => 'resolution_type', 'field_type' => 'select', 'label' => 'Resolution Type', 'sort_order' => 30, 'context' => 'resolve',
              'options_json' => wp_json_encode( [ 'approved' => 'Approved', 'denied' => 'Denied' ] ) ],
        ];

        foreach ( $fields as $f ) {
            $ctx = $f['context'] ?? 'submit';
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$ff_table} WHERE form_slug = 'cl_fame_registry' AND field_key = %s AND context = %s",
                $f['field_key'], $ctx
            ) );
            if ( $exists ) continue;

            $wpdb->insert( $ff_table, [
                'domain_slug'   => 'character_lifecycle',
                'form_slug'     => 'cl_fame_registry',
                'context'       => $ctx,
                'field_key'     => $f['field_key'],
                'field_type'    => $f['field_type'],
                'label'         => $f['label'],
                'sort_order'    => $f['sort_order'],
                'required'      => $f['required'] ?? 0,
                'options_json'  => $f['options_json'] ?? null,
                'default_value' => $f['default_value'] ?? null,
                'active'        => 1,
                'public_registry' => 0,
                'created_at'    => $now,
                'updated_at'    => $now,
            ] );
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
