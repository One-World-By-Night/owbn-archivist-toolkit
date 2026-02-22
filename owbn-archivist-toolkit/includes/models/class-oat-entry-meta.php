<?php

defined( 'ABSPATH' ) || exit;

class OAT_Entry_Meta {

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'oat_entry_meta';
    }

    /**
     * @param int    $entry_id
     * @param string $key
     * @return string|null
     */
    public static function get( $entry_id, $key ) {
        global $wpdb;
        return $wpdb->get_var( $wpdb->prepare(
            'SELECT meta_value FROM ' . self::table() . ' WHERE entry_id = %d AND meta_key = %s',
            $entry_id,
            $key
        ) );
    }

    /**
     * Alias for get_all().
     *
     * @param int $entry_id
     * @return array
     */
    public static function all( $entry_id ) {
        return self::get_all( $entry_id );
    }

    /**
     * @param int $entry_id
     * @return array
     */
    public static function get_all( $entry_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE entry_id = %d ORDER BY created_at ASC',
            $entry_id
        ) );
    }

    /**
     * Upsert: update existing row if found, otherwise insert.
     *
     * @param int    $entry_id
     * @param string $key
     * @param string $value
     * @return int Row ID (insert ID or existing ID).
     */
    public static function set( $entry_id, $key, $value ) {
        global $wpdb;
        $table = self::table();

        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE entry_id = %d AND meta_key = %s LIMIT 1",
            $entry_id,
            $key
        ) );

        if ( $existing ) {
            $wpdb->update(
                $table,
                array( 'meta_value' => $value ),
                array( 'id' => (int) $existing->id ),
                array( '%s' ),
                array( '%d' )
            );
            return (int) $existing->id;
        }

        $wpdb->insert( $table, array(
            'entry_id'   => $entry_id,
            'meta_key'   => $key,
            'meta_value' => $value,
            'created_at' => time(),
        ), array( '%d', '%s', '%s', '%d' ) );
        return (int) $wpdb->insert_id;
    }

    /**
     * @param int    $id
     * @param string $value
     * @return bool
     */
    public static function update( $id, $value ) {
        global $wpdb;
        return (bool) $wpdb->update(
            self::table(),
            array( 'meta_value' => $value ),
            array( 'id' => $id ),
            array( '%s' ),
            array( '%d' )
        );
    }

    /**
     * @param int $entry_id
     * @return bool
     */
    public static function delete_for_entry( $entry_id ) {
        global $wpdb;
        return (bool) $wpdb->delete( self::table(), array( 'entry_id' => $entry_id ), array( '%d' ) );
    }

    /**
     * @param int    $entry_id
     * @param string $key
     * @return array
     */
    public static function get_by_key( $entry_id, $key ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE entry_id = %d AND meta_key = %s ORDER BY created_at ASC',
            $entry_id,
            $key
        ) );
    }
}
