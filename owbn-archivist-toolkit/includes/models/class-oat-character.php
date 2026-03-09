<?php

defined( 'ABSPATH' ) || exit;

class OAT_Character {

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'oat_characters';
    }

    /**
     * Generate a UUIDv7 (RFC 9562) — PHP 7.4 compatible.
     *
     * @return string
     */
    private static function generate_uuid_v7() {
        $ms   = intval( microtime( true ) * 1000 );
        $ts   = str_pad( dechex( $ms ), 12, '0', STR_PAD_LEFT );
        $rand = random_bytes( 10 );
        $hex  = bin2hex( $rand );

        return sprintf(
            '%s-%s-7%s-%02x%s-%s',
            substr( $ts, 0, 8 ),
            substr( $ts, 8, 4 ),
            substr( $hex, 0, 3 ),
            ( ord( $rand[2] ) & 0x3F ) | 0x80,
            substr( $hex, 6, 2 ),
            substr( $hex, 8, 12 )
        );
    }
    public static function find( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE id = %d',
            $id
        ) );
    }
    public static function find_by_uuid( $uuid ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE uuid = %s',
            $uuid
        ) );
    }
    public static function find_by_external_uuid( $external_uuid ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE external_uuid = %s',
            $external_uuid
        ) );
    }
    public static function find_by_email( $email ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE player_email = %s ORDER BY created_at ASC',
            $email
        ) );
    }
    public static function for_chronicle( $chronicle_slug ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE chronicle_slug = %s ORDER BY character_name ASC',
            $chronicle_slug
        ) );
    }
    public static function create( $data ) {
        global $wpdb;

        if ( empty( $data['player_email'] ) || empty( $data['player_name'] ) ) {
            return 0;
        }

        $uuid = isset( $data['uuid'] ) && $data['uuid'] !== '' ? $data['uuid'] : self::generate_uuid_v7();

        $now = time();

        $insert = array(
            'uuid'         => $uuid,
            'player_email' => $data['player_email'],
            'player_name'  => $data['player_name'],
            'created_at'   => $now,
            'updated_at'   => $now,
        );
        $format = array( '%s', '%s', '%s', '%d', '%d' );

        if ( isset( $data['external_uuid'] ) && $data['external_uuid'] !== '' ) {
            $insert['external_uuid'] = $data['external_uuid'];
            $format[] = '%s';
        }
        if ( isset( $data['character_name'] ) && $data['character_name'] !== '' ) {
            $insert['character_name'] = $data['character_name'];
            $format[] = '%s';
        }
        if ( isset( $data['wp_user_id'] ) ) {
            $insert['wp_user_id'] = $data['wp_user_id'];
            $format[] = '%d';
        }
        if ( isset( $data['chronicle_slug'] ) && $data['chronicle_slug'] !== '' ) {
            $insert['chronicle_slug'] = $data['chronicle_slug'];
            $format[] = '%s';
        }
        if ( isset( $data['pc_npc'] ) && in_array( $data['pc_npc'], array( 'pc', 'npc' ), true ) ) {
            $insert['pc_npc'] = $data['pc_npc'];
            $format[] = '%s';
        }
        foreach ( array( 'creature_type', 'creature_sub_type', 'status', 'npc_coordinator', 'npc_type' ) as $col ) {
            if ( isset( $data[ $col ] ) && $data[ $col ] !== '' ) {
                $insert[ $col ] = $data[ $col ];
                $format[] = '%s';
            }
        }
        foreach ( array( 'fame_json', 'connections_json' ) as $col ) {
            if ( isset( $data[ $col ] ) && $data[ $col ] !== '' ) {
                $insert[ $col ] = $data[ $col ];
                $format[] = '%s';
            }
        }

        $wpdb->insert( self::table(), $insert, $format );
        $id = (int) $wpdb->insert_id;

        if ( $id ) {
            do_action( 'oat_character_created', $id, $insert );
        }

        return $id;
    }
    public static function update( $id, $data ) {
        global $wpdb;

        $allowed = array( 'external_uuid', 'character_name', 'wp_user_id', 'player_email', 'player_name', 'chronicle_slug', 'pc_npc', 'creature_type', 'creature_sub_type', 'status', 'npc_coordinator', 'npc_type', 'fame_json', 'connections_json' );

        $update = array();
        $format = array();

        foreach ( $allowed as $col ) {
            if ( array_key_exists( $col, $data ) ) {
                $update[ $col ] = $data[ $col ];
                $format[] = $col === 'wp_user_id' ? '%d' : '%s';
            }
        }

        if ( empty( $update ) ) {
            return false;
        }

        // Snapshot old chronicle_slug before update for transfer detection.
        $old_chronicle = null;
        if ( array_key_exists( 'chronicle_slug', $update ) ) {
            $old = self::find( $id );
            $old_chronicle = $old ? $old->chronicle_slug : null;
        }

        $update['updated_at'] = time();
        $format[] = '%d';

        $result = (bool) $wpdb->update(
            self::table(),
            $update,
            array( 'id' => $id ),
            $format,
            array( '%d' )
        );

        // Fire transfer action if chronicle_slug actually changed.
        if ( $result && $old_chronicle !== null ) {
            $new_chronicle = $update['chronicle_slug'];
            if ( $new_chronicle !== $old_chronicle ) {
                do_action( 'oat_character_transferred', $id, $old_chronicle, $new_chronicle );
            }
        }

        return $result;
    }
    public static function link_user( $id, $wp_user_id ) {
        return self::update( $id, array( 'wp_user_id' => $wp_user_id ) );
    }

    /**
     * Search characters by name with optional LIMIT.
     *
     * @param string $term  Search term (matched with LIKE %term%).
     * @param int    $limit Max results.
     * @return array
     */
    public static function search( $term, $limit = 20 ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE character_name LIKE %s ORDER BY character_name ASC LIMIT %d',
            '%' . $wpdb->esc_like( $term ) . '%',
            $limit
        ) );
    }

    /**
     * Search characters scoped by the submitter role context.
     *
     * Scoping rules:
     *   player      → own characters only (by email)
     *   staff       → characters in the user's chronicle(s)
     *   coordinator → all characters
     *   archivist   → all characters
     *
     * @param string $term           Search term.
     * @param string $submitter_role One of: player, staff, coordinator, archivist.
     * @param int    $limit          Max results.
     * @return array
     */
    public static function for_user_scoped( $term, $submitter_role = 'player', $limit = 20 ) {
        global $wpdb;

        $like  = '%' . $wpdb->esc_like( $term ) . '%';
        $table = self::table();

        // Coordinator and archivist see everything.
        if ( in_array( $submitter_role, array( 'coordinator', 'archivist' ), true ) ) {
            return $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$table} WHERE character_name LIKE %s ORDER BY character_name ASC LIMIT %d",
                $like,
                $limit
            ) );
        }

        // Staff see characters in their chronicle(s).
        if ( $submitter_role === 'staff' ) {
            $chronicle_slugs = self::get_user_chronicle_slugs();
            if ( empty( $chronicle_slugs ) ) {
                return self::for_user_scoped( $term, 'player', $limit );
            }

            $placeholders = implode( ',', array_fill( 0, count( $chronicle_slugs ), '%s' ) );
            $args         = $chronicle_slugs;
            $args[]       = $like;
            $args[]       = $limit;

            return $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$table} WHERE chronicle_slug IN ({$placeholders}) AND character_name LIKE %s ORDER BY character_name ASC LIMIT %d",
                $args
            ) );
        }

        // Player sees own characters only.
        $user = wp_get_current_user();
        if ( ! $user || ! $user->exists() ) {
            return array();
        }

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE player_email = %s AND character_name LIKE %s ORDER BY character_name ASC LIMIT %d",
            $user->user_email,
            $like,
            $limit
        ) );
    }

    /**
     * Reassociate characters from an old email to a new WP user.
     *
     * Sets wp_user_id on all characters matching old_email.
     * Optionally updates player_email if a new email is provided.
     *
     * @param string   $old_email      The Drupal-era email to match.
     * @param int      $new_wp_user_id The WP user ID to associate.
     * @param string   $new_email      Optional new email to replace old_email.
     * @return int Number of characters updated.
     */
    public static function reassociate( $old_email, $new_wp_user_id, $new_email = '' ) {
        global $wpdb;
        $table = self::table();
        $now   = time();

        if ( $new_email && $new_email !== $old_email ) {
            return (int) $wpdb->query( $wpdb->prepare(
                "UPDATE {$table} SET wp_user_id = %d, player_email = %s, updated_at = %d WHERE player_email = %s",
                $new_wp_user_id,
                $new_email,
                $now,
                $old_email
            ) );
        }

        return (int) $wpdb->query( $wpdb->prepare(
            "UPDATE {$table} SET wp_user_id = %d, updated_at = %d WHERE player_email = %s",
            $new_wp_user_id,
            $now,
            $old_email
        ) );
    }

    /**
     * Get chronicle slugs the current user has staff access to.
     *
     * @return array Chronicle slug strings.
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
}
