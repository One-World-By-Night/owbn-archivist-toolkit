<?php
/**
 * OAT Entry Coordinators.
 *
 * Join table mapping an entry to the one-or-more controlling coordinator
 * authorities that OWN it. Source of truth for multi-authority ownership;
 * the legacy oat_entries.coordinator_genre column is kept as a denormalized
 * "primary" for back-compat. Served to clients as a JSON array.
 *
 * Slugs share the coordinator namespace used by oat_registry_access.grant_value
 * and ASC coordinator/<slug>/... roles. Stored normalized (lowercased/trimmed).
 */

defined( 'ABSPATH' ) || exit;

class OAT_Entry_Coordinator {

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'oat_entry_coordinators';
    }

    /**
     * Normalize a coordinator slug for storage/comparison.
     *
     * @param string $slug
     * @return string
     */
    public static function normalize( $slug ) {
        return strtolower( trim( (string) $slug ) );
    }

    /**
     * Idempotently attach a single coordinator authority to an entry.
     *
     * @param int    $entry_id
     * @param string $slug
     * @param string $source  Provenance tag (e.g. 'rule','subtype','manual','submit').
     * @return int   Existing or new row ID, 0 on empty slug.
     */
    public static function add( $entry_id, $slug, $source = '' ) {
        global $wpdb;
        $entry_id = (int) $entry_id;
        $slug     = self::normalize( $slug );
        if ( ! $entry_id || '' === $slug ) {
            return 0;
        }

        $existing = $wpdb->get_var( $wpdb->prepare(
            'SELECT id FROM ' . self::table() . ' WHERE entry_id = %d AND coordinator_slug = %s LIMIT 1',
            $entry_id,
            $slug
        ) );
        if ( $existing ) {
            return (int) $existing;
        }

        $wpdb->insert( self::table(), array(
            'entry_id'         => $entry_id,
            'coordinator_slug' => $slug,
            'source'           => (string) $source,
            'created_at'       => time(),
        ), array( '%d', '%s', '%s', '%d' ) );

        return (int) $wpdb->insert_id;
    }

    /**
     * Replace an entry's coordinator set with the given slugs.
     *
     * @param int    $entry_id
     * @param array  $slugs   Coordinator slugs (will be normalized + de-duped).
     * @param string $source  Provenance tag applied to inserted rows.
     * @return int    Number of distinct authorities now attached.
     */
    public static function set_for_entry( $entry_id, $slugs, $source = '' ) {
        $entry_id = (int) $entry_id;
        if ( ! $entry_id ) {
            return 0;
        }

        $clean = array();
        foreach ( (array) $slugs as $s ) {
            $s = self::normalize( $s );
            if ( '' !== $s ) {
                $clean[ $s ] = true;
            }
        }

        self::delete_for_entry( $entry_id );
        foreach ( array_keys( $clean ) as $s ) {
            self::add( $entry_id, $s, $source );
        }
        return count( $clean );
    }

    /**
     * All coordinator slugs attached to an entry.
     *
     * @param int $entry_id
     * @return array Normalized slug strings.
     */
    public static function slugs_for_entry( $entry_id ) {
        global $wpdb;
        return $wpdb->get_col( $wpdb->prepare(
            'SELECT coordinator_slug FROM ' . self::table() . ' WHERE entry_id = %d ORDER BY coordinator_slug ASC',
            (int) $entry_id
        ) );
    }

    /**
     * Full rows (slug + source) for an entry.
     *
     * @param int $entry_id
     * @return array
     */
    public static function for_entry( $entry_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE entry_id = %d ORDER BY coordinator_slug ASC',
            (int) $entry_id
        ) );
    }

    /**
     * Whether an entry carries a given authority.
     *
     * @param int    $entry_id
     * @param string $slug
     * @return bool
     */
    public static function has( $entry_id, $slug ) {
        global $wpdb;
        return (bool) $wpdb->get_var( $wpdb->prepare(
            'SELECT COUNT(*) FROM ' . self::table() . ' WHERE entry_id = %d AND coordinator_slug = %s',
            (int) $entry_id,
            self::normalize( $slug )
        ) );
    }

    /**
     * Remove all authorities for an entry.
     *
     * @param int $entry_id
     * @return int Rows deleted.
     */
    public static function delete_for_entry( $entry_id ) {
        global $wpdb;
        return (int) $wpdb->delete( self::table(), array( 'entry_id' => (int) $entry_id ), array( '%d' ) );
    }
}
