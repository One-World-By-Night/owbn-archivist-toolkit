<?php

defined( 'ABSPATH' ) || exit;

class OAT_Registry {

    /**
     * Characters owned by a player (via wp_user_id).
     *
     * @param int $user_id WP user ID.
     * @return array Character rows.
     */
    public static function get_characters_for_player( $user_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'oat_characters';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE wp_user_id = %d ORDER BY character_name ASC",
            $user_id
        ) );
    }

    /**
     * Characters with an active chronicle grant.
     *
     * @param string $chronicle_slug
     * @return array Character rows.
     */
    public static function get_characters_for_chronicle( $chronicle_slug ) {
        global $wpdb;
        $table   = $wpdb->prefix . 'oat_characters';
        $grants  = $wpdb->prefix . 'oat_registry_access';
        $now     = time();

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT c.* FROM {$table} c
             INNER JOIN {$grants} g ON c.id = g.character_id
             WHERE g.grant_type = 'chronicle'
               AND g.grant_value = %s
               AND (g.starts_at IS NULL OR g.starts_at <= %d)
               AND (g.expires_at IS NULL OR g.expires_at >= %d)
             GROUP BY c.id
             ORDER BY c.character_name ASC",
            $chronicle_slug,
            $now,
            $now
        ) );
    }

    /**
     * Characters with an active coordinator grant for a genre.
     *
     * @param string $genre Genre slug.
     * @return array Character rows.
     */
    public static function get_characters_for_coordinator( $genre ) {
        global $wpdb;
        $table   = $wpdb->prefix . 'oat_characters';
        $grants  = $wpdb->prefix . 'oat_registry_access';
        $now     = time();

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT c.* FROM {$table} c
             INNER JOIN {$grants} g ON c.id = g.character_id
             WHERE g.grant_type = 'coordinator'
               AND g.grant_value = %s
               AND (g.starts_at IS NULL OR g.starts_at <= %d)
               AND (g.expires_at IS NULL OR g.expires_at >= %d)
             GROUP BY c.id
             ORDER BY c.character_name ASC",
            $genre,
            $now,
            $now
        ) );
    }

    /**
     * Approved entries linked to a character.
     *
     * @param int $character_id
     * @return array Entry rows.
     */
    public static function get_registry_entries( $character_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'oat_entries';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE character_id = %d AND status = %s ORDER BY created_at DESC",
            $character_id,
            OAT_Constants::STATUS_APPROVED
        ) );
    }

    /**
     * Approved entry count per domain for a character.
     *
     * @param int $character_id
     * @return array Associative: domain => count.
     */
    public static function get_entry_counts_by_domain( $character_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'oat_entries';
        $rows  = $wpdb->get_results( $wpdb->prepare(
            "SELECT domain, COUNT(*) AS cnt FROM {$table} WHERE character_id = %d AND status = %s GROUP BY domain",
            $character_id,
            OAT_Constants::STATUS_APPROVED
        ) );
        $counts = array();
        foreach ( $rows as $row ) {
            $counts[ $row->domain ] = (int) $row->cnt;
        }
        return $counts;
    }

    /**
     * Public registry data for a character.
     *
     * Returns only meta from fields flagged public_registry = 1.
     * No grant check — visible to anyone who can see the character.
     *
     * @param int $character_id
     * @return array Associative: field_key => meta_value.
     */
    public static function get_public_registry( $character_id ) {
        $public_keys = OAT_Form_Field::get_public_registry_keys();
        if ( empty( $public_keys ) ) {
            return array();
        }

        // Flatten all public field keys.
        $all_keys = array();
        foreach ( $public_keys as $keys ) {
            $all_keys = array_merge( $all_keys, $keys );
        }
        $all_keys = array_unique( $all_keys );

        // Get approved entries for this character.
        $entries = self::get_registry_entries( $character_id );
        if ( empty( $entries ) ) {
            return array();
        }

        $result = array();
        foreach ( $entries as $entry ) {
            $meta = OAT_Entry_Meta::get_all( (int) $entry->id );
            foreach ( $meta as $m ) {
                if ( in_array( $m->meta_key, $all_keys, true ) ) {
                    $result[] = array(
                        'entry_id'  => (int) $entry->id,
                        'domain'    => $entry->domain,
                        'meta_key'  => $m->meta_key,
                        'meta_value' => $m->meta_value,
                    );
                }
            }
        }

        return $result;
    }

    /**
     * Scoped registry view for a user.
     *
     * Returns characters the viewer can see based on their role,
     * plus entry counts per domain for each character.
     *
     * @param int $user_id WP user ID.
     * @return array Array of character objects with ->entry_counts added.
     */
    public static function get_scoped_registry( $user_id ) {
        $search_roles = OAT_Authorization::get_character_search_roles();
        $top_role     = $search_roles[0]; // Most permissive.

        $characters = array();

        if ( $top_role === 'archivist' ) {
            $characters = self::get_all_characters();
        } else {
            // Merge all applicable scopes — a coordinator who is also staff
            // should see both coord-genre characters and chronicle characters.
            if ( in_array( 'coordinator', $search_roles, true ) ) {
                $genres = self::get_user_coordinator_genres();
                foreach ( $genres as $genre ) {
                    $chars = self::get_characters_for_coordinator( $genre );
                    foreach ( $chars as $c ) {
                        $characters[ $c->id ] = $c;
                    }
                }
            }
            if ( in_array( 'staff', $search_roles, true ) ) {
                $slugs = self::get_user_chronicle_slugs();
                foreach ( $slugs as $slug ) {
                    $chars = self::get_characters_for_chronicle( $slug );
                    foreach ( $chars as $c ) {
                        $characters[ $c->id ] = $c;
                    }
                }
            }
            // Always include own characters.
            $own = self::get_characters_for_player( $user_id );
            foreach ( $own as $c ) {
                $characters[ $c->id ] = $c;
            }
            $characters = array_values( $characters );
        }

        // Attach entry counts.
        foreach ( $characters as $char ) {
            $char->entry_counts = self::get_entry_counts_by_domain( (int) $char->id );
        }

        return $characters;
    }

    /**
     * Filter timeline events for a registry viewer.
     *
     * @param array  $timeline_events Timeline rows.
     * @param string $viewer_role     player|staff|coordinator|archivist.
     * @param object $entry           The entry being viewed.
     * @param array  $viewer_genres   Coordinator genres the viewer holds (for coord tier filtering).
     * @return array Filtered timeline events.
     */
    public static function filter_timeline_for_viewer( $timeline_events, $viewer_role, $entry, $viewer_genres = array() ) {
        $tier_order = array(
            OAT_Constants::TIER_STAFF       => 1,
            OAT_Constants::TIER_COORDINATOR => 2,
            OAT_Constants::TIER_ARCHIVIST   => 3,
        );

        if ( $viewer_role === 'archivist' ) {
            return $timeline_events;
        }

        $max_tier = 1; // staff
        if ( $viewer_role === 'coordinator' ) {
            // Coordinator sees up to TIER_COORDINATOR but only on entries routed through their genre.
            if ( ! empty( $entry->coordinator_genre ) && in_array( $entry->coordinator_genre, $viewer_genres, true ) ) {
                $max_tier = 2;
            }
        }
        // Staff and player both see up to TIER_STAFF (max_tier = 1).

        $filtered = array();
        foreach ( $timeline_events as $event ) {
            $event_tier = isset( $tier_order[ $event->visibility_tier ] ) ? $tier_order[ $event->visibility_tier ] : 1;
            if ( $event_tier <= $max_tier ) {
                $filtered[] = $event;
            }
        }
        return $filtered;
    }

    /**
     * All characters (archivist view).
     *
     * @return array
     */
    private static function get_all_characters() {
        global $wpdb;
        $table = $wpdb->prefix . 'oat_characters';
        return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY character_name ASC" );
    }

    /**
     * Extract chronicle slugs from the current user's ASC roles.
     *
     * @return array
     */
    private static function get_user_chronicle_slugs() {
        $roles = OAT_Authorization::get_user_roles();
        $slugs = array();
        foreach ( $roles as $role ) {
            if ( preg_match( '#^chronicle/([^/]+)/(hst|staff|cm|ast)#i', $role, $m ) ) {
                $slugs[] = $m[1];
            }
        }
        return array_unique( $slugs );
    }

    /**
     * Extract coordinator genre slugs from the current user's ASC roles.
     *
     * @return array
     */
    private static function get_user_coordinator_genres() {
        $roles = OAT_Authorization::get_user_roles();
        $genres = array();
        foreach ( $roles as $role ) {
            if ( preg_match( '#^coordinator/([^/]+)/(coordinator|sub-coordinator)$#i', $role, $m ) ) {
                $genres[] = $m[1];
            }
        }
        return array_unique( $genres );
    }
}
