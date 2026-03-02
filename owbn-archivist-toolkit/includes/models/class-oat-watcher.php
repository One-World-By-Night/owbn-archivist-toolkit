<?php

defined( 'ABSPATH' ) || exit;

class OAT_Watcher {

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'oat_watchers';
    }
    public static function add( $entry_id, $user_id, $added_by ) {
        global $wpdb;
        $wpdb->insert( self::table(), array(
            'entry_id' => $entry_id,
            'user_id'  => $user_id,
            'added_by' => $added_by,
            'added_at' => time(),
        ), array( '%d', '%d', '%d', '%d' ) );
        return (int) $wpdb->insert_id;
    }
    public static function remove( $entry_id, $user_id ) {
        global $wpdb;
        return (bool) $wpdb->delete( self::table(), array(
            'entry_id' => $entry_id,
            'user_id'  => $user_id,
        ), array( '%d', '%d' ) );
    }
    public static function for_entry( $entry_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE entry_id = %d ORDER BY added_at ASC',
            $entry_id
        ) );
    }
    public static function user_ids_for_entry( $entry_id ) {
        global $wpdb;
        return $wpdb->get_col( $wpdb->prepare(
            'SELECT user_id FROM ' . self::table() . ' WHERE entry_id = %d',
            $entry_id
        ) );
    }

    /**
     * Get all entries a user is watching.
     *
     * @param int $user_id
     * @return array
     */
    public static function for_user( $user_id ) {
        global $wpdb;
        $w = self::table();
        $e = $wpdb->prefix . 'oat_entries';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT w.*, e.domain, e.status AS entry_status, e.current_step
             FROM {$w} w
             JOIN {$e} e ON w.entry_id = e.id
             WHERE w.user_id = %d
             ORDER BY w.added_at DESC",
            $user_id
        ) );
    }
    public static function is_watching( $entry_id, $user_id ) {
        global $wpdb;
        return (bool) $wpdb->get_var( $wpdb->prepare(
            'SELECT COUNT(*) FROM ' . self::table() . ' WHERE entry_id = %d AND user_id = %d',
            $entry_id,
            $user_id
        ) );
    }
    public static function delete_for_entry( $entry_id ) {
        global $wpdb;
        return (bool) $wpdb->delete( self::table(), array( 'entry_id' => $entry_id ), array( '%d' ) );
    }
}
