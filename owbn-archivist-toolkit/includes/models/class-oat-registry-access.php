<?php

defined( 'ABSPATH' ) || exit;

class OAT_Registry_Access {

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'oat_registry_access';
    }

    /**
     * Active grant condition fragment.
     *
     * @return string SQL WHERE fragment.
     */
    private static function active_condition() {
        $now = time();
        return "(starts_at IS NULL OR starts_at <= {$now}) AND (expires_at IS NULL OR expires_at >= {$now})";
    }

    /**
     * Create a grant row.
     *
     * @param int         $character_id
     * @param string      $grant_type   'chronicle' or 'coordinator'.
     * @param string      $grant_value  Chronicle slug or genre slug.
     * @param int|null    $granted_by   User ID who created the grant.
     * @param int|null    $starts_at    Unix timestamp or null for immediate.
     * @param int|null    $expires_at   Unix timestamp or null for permanent.
     * @return int        Inserted row ID.
     */
    public static function create( $character_id, $grant_type, $grant_value, $granted_by = null, $starts_at = null, $expires_at = null ) {
        global $wpdb;
        $wpdb->insert( self::table(), array(
            'character_id' => $character_id,
            'grant_type'   => $grant_type,
            'grant_value'  => $grant_value,
            'granted_by'   => $granted_by,
            'starts_at'    => $starts_at,
            'expires_at'   => $expires_at,
            'created_at'   => time(),
        ), array( '%d', '%s', '%s', '%d', '%d', '%d', '%d' ) );
        return (int) $wpdb->insert_id;
    }

    /**
     * Find a single grant by ID.
     *
     * @param int $id
     * @return object|null
     */
    public static function find( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE id = %d',
            $id
        ) );
    }

    /**
     * All active grants for a character.
     *
     * @param int $character_id
     * @return array
     */
    public static function find_by_character( $character_id ) {
        global $wpdb;
        $active = self::active_condition();
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE character_id = %d AND {$active} ORDER BY created_at ASC",
            $character_id
        ) );
    }

    /**
     * All characters accessible via a specific grant.
     *
     * @param string $grant_type
     * @param string $grant_value
     * @return array
     */
    public static function find_by_grant( $grant_type, $grant_value ) {
        global $wpdb;
        $active = self::active_condition();
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE grant_type = %s AND grant_value = %s AND {$active} ORDER BY created_at ASC",
            $grant_type,
            $grant_value
        ) );
    }

    /**
     * Character IDs accessible via a specific grant.
     *
     * @param string $grant_type
     * @param string $grant_value
     * @return array Array of integer character IDs.
     */
    public static function character_ids_by_grant( $grant_type, $grant_value ) {
        global $wpdb;
        $active = self::active_condition();
        return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT character_id FROM " . self::table() . " WHERE grant_type = %s AND grant_value = %s AND {$active}",
            $grant_type,
            $grant_value
        ) ) );
    }

    /**
     * Check whether an active grant exists.
     *
     * @param int    $character_id
     * @param string $grant_type
     * @param string $grant_value
     * @return bool
     */
    public static function has_access( $character_id, $grant_type, $grant_value ) {
        global $wpdb;
        $active = self::active_condition();
        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM " . self::table() . " WHERE character_id = %d AND grant_type = %s AND grant_value = %s AND {$active}",
            $character_id,
            $grant_type,
            $grant_value
        ) );
    }

    /**
     * Expire a grant (set expires_at to now).
     *
     * @param int $id
     * @return bool
     */
    public static function expire( $id ) {
        global $wpdb;
        return (bool) $wpdb->update(
            self::table(),
            array( 'expires_at' => time() ),
            array( 'id' => $id ),
            array( '%d' ),
            array( '%d' )
        );
    }

    /**
     * Hard-delete a grant row.
     *
     * @param int $id
     * @return bool
     */
    public static function delete( $id ) {
        global $wpdb;
        return (bool) $wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );
    }

    /**
     * Insert a grant if one does not already exist (idempotent).
     *
     * @param int      $character_id
     * @param string   $grant_type
     * @param string   $grant_value
     * @param int|null $granted_by
     * @return int     Existing or new row ID.
     */
    public static function ensure_grant( $character_id, $grant_type, $grant_value, $granted_by = null ) {
        global $wpdb;
        $active = self::active_condition();
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM " . self::table() . " WHERE character_id = %d AND grant_type = %s AND grant_value = %s AND {$active} LIMIT 1",
            $character_id,
            $grant_type,
            $grant_value
        ) );
        if ( $existing ) {
            return (int) $existing;
        }
        return self::create( $character_id, $grant_type, $grant_value, $granted_by );
    }

    /**
     * Expire all active grants of a specific type/value for a character.
     *
     * @param int    $character_id
     * @param string $grant_type
     * @param string $grant_value
     * @return int   Number of rows expired.
     */
    public static function expire_grants( $character_id, $grant_type, $grant_value ) {
        global $wpdb;
        $active = self::active_condition();
        return (int) $wpdb->query( $wpdb->prepare(
            "UPDATE " . self::table() . " SET expires_at = %d WHERE character_id = %d AND grant_type = %s AND grant_value = %s AND {$active}",
            time(),
            $character_id,
            $grant_type,
            $grant_value
        ) );
    }

    /**
     * All grants for a character (including expired), for admin/history views.
     *
     * @param int $character_id
     * @return array
     */
    public static function all_for_character( $character_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE character_id = %d ORDER BY created_at ASC',
            $character_id
        ) );
    }
}
