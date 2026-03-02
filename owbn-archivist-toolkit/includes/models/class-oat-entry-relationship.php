<?php

defined( 'ABSPATH' ) || exit;

class OAT_Entry_Relationship {

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'oat_entry_relationships';
    }
    public static function create( $source_id, $target_id, $type ) {
        global $wpdb;
        $wpdb->insert( self::table(), array(
            'source_entry_id'   => $source_id,
            'target_entry_id'   => $target_id,
            'relationship_type' => $type,
            'created_at'        => time(),
        ), array( '%d', '%d', '%s', '%d' ) );
        return (int) $wpdb->insert_id;
    }
    public static function for_source( $entry_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE source_entry_id = %d ORDER BY created_at ASC',
            $entry_id
        ) );
    }
    public static function for_target( $entry_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE target_entry_id = %d ORDER BY created_at ASC',
            $entry_id
        ) );
    }

    /**
     * Delete all relationships where the entry is source or target.
     *
     * @param int $entry_id
     * @return bool
     */
    public static function delete_for_entry( $entry_id ) {
        global $wpdb;
        $table = self::table();
        $wpdb->delete( $table, array( 'source_entry_id' => $entry_id ), array( '%d' ) );
        $wpdb->delete( $table, array( 'target_entry_id' => $entry_id ), array( '%d' ) );
        return true;
    }
}
