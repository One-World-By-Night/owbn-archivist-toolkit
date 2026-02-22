<?php
/**
 * OAT Database Schema.
 *
 * All 11 table DDL statements formatted for WordPress dbDelta().
 * Source of truth: SCHEMA-DESIGN.md.
 *
 * dbDelta requirements:
 *  - Each field on its own line.
 *  - Two spaces between PRIMARY KEY and the column definition.
 *  - KEY (not INDEX) for secondary indexes.
 *  - Must use $wpdb->get_charset_collate().
 */

defined( 'ABSPATH' ) || exit;

class OAT_Schema {

    /**
     * Return the complete SQL for all OAT tables.
     *
     * @return string
     */
    public static function get_tables() {
        global $wpdb;

        $prefix  = $wpdb->prefix;
        $charset = $wpdb->get_charset_collate();

        $sql = '';

        // ── oat_entries ─────────────────────────────────────────────
        $sql .= "CREATE TABLE {$prefix}oat_entries (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            domain varchar(255) NOT NULL,
            status varchar(50) NOT NULL DEFAULT 'pending',
            current_step varchar(255) NOT NULL,
            originator_id bigint(20) unsigned NOT NULL,
            chronicle_slug varchar(100) DEFAULT NULL,
            character_id bigint(20) unsigned DEFAULT NULL,
            coordinator_genre varchar(255) DEFAULT NULL,
            created_at bigint(20) unsigned NOT NULL,
            updated_at bigint(20) unsigned NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_domain_status (domain,status),
            KEY idx_status (status),
            KEY idx_originator_id (originator_id),
            KEY idx_chronicle_slug (chronicle_slug),
            KEY idx_character_id (character_id),
            KEY idx_coordinator_genre (coordinator_genre)
        ) {$charset};\n\n";

        // ── oat_entry_meta ──────────────────────────────────────────
        $sql .= "CREATE TABLE {$prefix}oat_entry_meta (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            entry_id bigint(20) unsigned NOT NULL,
            meta_key varchar(255) NOT NULL,
            meta_value longtext DEFAULT NULL,
            created_at bigint(20) unsigned NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_entry_id (entry_id),
            KEY idx_entry_key (entry_id,meta_key)
        ) {$charset};\n\n";

        // ── oat_timeline ────────────────────────────────────────────
        $sql .= "CREATE TABLE {$prefix}oat_timeline (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            entry_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            action_type varchar(50) NOT NULL,
            step varchar(255) DEFAULT NULL,
            visibility_tier varchar(50) NOT NULL,
            note longtext DEFAULT NULL,
            created_at bigint(20) unsigned NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_entry_id (entry_id),
            KEY idx_entry_tier (entry_id,visibility_tier)
        ) {$charset};\n\n";

        // ── oat_assignees ───────────────────────────────────────────
        $sql .= "CREATE TABLE {$prefix}oat_assignees (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            entry_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            step varchar(255) NOT NULL,
            status varchar(50) NOT NULL DEFAULT 'pending',
            assigned_at bigint(20) unsigned NOT NULL,
            acted_at bigint(20) unsigned DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY idx_user_status (user_id,status),
            KEY idx_entry_id (entry_id),
            KEY idx_entry_step (entry_id,step)
        ) {$charset};\n\n";

        // ── oat_watchers ────────────────────────────────────────────
        $sql .= "CREATE TABLE {$prefix}oat_watchers (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            entry_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            added_by bigint(20) unsigned NOT NULL,
            added_at bigint(20) unsigned NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_entry_id (entry_id),
            KEY idx_user_id (user_id)
        ) {$charset};\n\n";

        // ── oat_notifications ───────────────────────────────────────
        $sql .= "CREATE TABLE {$prefix}oat_notifications (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            entry_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            channel varchar(50) NOT NULL,
            event_type varchar(50) NOT NULL,
            status varchar(50) NOT NULL DEFAULT 'pending',
            created_at bigint(20) unsigned NOT NULL,
            sent_at bigint(20) unsigned DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY idx_entry_id (entry_id),
            KEY idx_user_id (user_id)
        ) {$charset};\n\n";

        // ── oat_regulation_rules ────────────────────────────────────
        $sql .= "CREATE TABLE {$prefix}oat_regulation_rules (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            genre varchar(255) NOT NULL,
            category varchar(255) NOT NULL,
            subcategory varchar(255) DEFAULT NULL,
            condition_name varchar(255) DEFAULT NULL,
            pc_level varchar(50) DEFAULT NULL,
            npc_level varchar(50) DEFAULT NULL,
            controlling_coordinator varchar(255) NOT NULL,
            elevation tinyint(1) NOT NULL DEFAULT 0,
            active tinyint(1) NOT NULL DEFAULT 1,
            created_at bigint(20) unsigned NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_genre (genre),
            KEY idx_active (active)
        ) {$charset};\n\n";

        // ── oat_timers ──────────────────────────────────────────────
        $sql .= "CREATE TABLE {$prefix}oat_timers (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            entry_id bigint(20) unsigned NOT NULL,
            step varchar(255) NOT NULL,
            status varchar(50) NOT NULL DEFAULT 'active',
            auto_action varchar(50) NOT NULL,
            bump_required tinyint unsigned NOT NULL DEFAULT 0,
            bump_count tinyint unsigned NOT NULL DEFAULT 0,
            started_at bigint(20) unsigned NOT NULL,
            expires_at bigint(20) unsigned NOT NULL,
            paused_at bigint(20) unsigned DEFAULT NULL,
            fired_at bigint(20) unsigned DEFAULT NULL,
            created_at bigint(20) unsigned NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_status_expires (status,expires_at),
            KEY idx_entry_id (entry_id)
        ) {$charset};\n\n";

        // ── oat_entry_relationships ─────────────────────────────────
        $sql .= "CREATE TABLE {$prefix}oat_entry_relationships (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            source_entry_id bigint(20) unsigned NOT NULL,
            target_entry_id bigint(20) unsigned NOT NULL,
            relationship_type varchar(255) NOT NULL,
            created_at bigint(20) unsigned NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_source (source_entry_id),
            KEY idx_target (target_entry_id)
        ) {$charset};\n\n";

        // ── oat_entry_rules ─────────────────────────────────────────
        $sql .= "CREATE TABLE {$prefix}oat_entry_rules (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            entry_id bigint(20) unsigned NOT NULL,
            rule_id bigint(20) unsigned NOT NULL,
            created_at bigint(20) unsigned NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_entry_id (entry_id),
            KEY idx_rule_id (rule_id)
        ) {$charset};\n\n";

        // ── oat_characters ──────────────────────────────────────────
        $sql .= "CREATE TABLE {$prefix}oat_characters (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            uuid char(36) NOT NULL,
            external_uuid char(36) DEFAULT NULL,
            character_name varchar(255) DEFAULT NULL,
            wp_user_id bigint(20) unsigned DEFAULT NULL,
            player_email varchar(255) NOT NULL,
            player_name varchar(255) NOT NULL,
            chronicle_slug varchar(100) DEFAULT NULL,
            created_at bigint(20) unsigned NOT NULL,
            updated_at bigint(20) unsigned NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY idx_uuid (uuid),
            UNIQUE KEY idx_external_uuid (external_uuid),
            KEY idx_wp_user_id (wp_user_id)
        ) {$charset};\n\n";

        return $sql;
    }
}
