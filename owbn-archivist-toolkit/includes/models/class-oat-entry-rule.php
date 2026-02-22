<?php

defined( 'ABSPATH' ) || exit;

class OAT_Entry_Rule {

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'oat_entry_rules';
    }

    private static function rules_table() {
        global $wpdb;
        return $wpdb->prefix . 'oat_regulation_rules';
    }

    /**
     * @param int $entry_id
     * @param int $rule_id
     * @return int Insert ID.
     */
    public static function create( $entry_id, $rule_id ) {
        global $wpdb;
        $wpdb->insert( self::table(), array(
            'entry_id'   => $entry_id,
            'rule_id'    => $rule_id,
            'created_at' => time(),
        ), array( '%d', '%d', '%d' ) );
        return (int) $wpdb->insert_id;
    }

    /**
     * Alias for rules_for_entry().
     *
     * @param int $entry_id
     * @return array
     */
    public static function for_entry( $entry_id ) {
        return self::rules_for_entry( $entry_id );
    }

    /**
     * @param int $entry_id
     * @return array Full rule objects for this entry.
     */
    public static function rules_for_entry( $entry_id ) {
        global $wpdb;
        $er = self::table();
        $r  = self::rules_table();

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT r.* FROM {$er} er JOIN {$r} r ON er.rule_id = r.id WHERE er.entry_id = %d",
            $entry_id
        ) );
    }

    /**
     * @param int $rule_id
     * @return array Entry IDs linked to this rule.
     */
    public static function entries_for_rule( $rule_id ) {
        global $wpdb;
        return $wpdb->get_col( $wpdb->prepare(
            'SELECT entry_id FROM ' . self::table() . ' WHERE rule_id = %d',
            $rule_id
        ) );
    }

    /**
     * @param int $entry_id
     * @return bool True if any linked rule has elevation = 1.
     */
    public static function has_elevation( $entry_id ) {
        global $wpdb;
        $er = self::table();
        $r  = self::rules_table();

        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$er} er JOIN {$r} r ON er.rule_id = r.id WHERE er.entry_id = %d AND r.elevation = 1",
            $entry_id
        ) );
    }

    /**
     * @param int $entry_id
     * @return bool
     */
    public static function delete_for_entry( $entry_id ) {
        global $wpdb;
        return (bool) $wpdb->delete( self::table(), array( 'entry_id' => $entry_id ), array( '%d' ) );
    }
}
