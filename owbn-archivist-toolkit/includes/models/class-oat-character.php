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

        $wpdb->insert( self::table(), $insert, $format );
        return (int) $wpdb->insert_id;
    }
    public static function update( $id, $data ) {
        global $wpdb;

        $allowed = array( 'external_uuid', 'character_name', 'wp_user_id', 'player_email', 'player_name', 'chronicle_slug', 'pc_npc' );

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

        $update['updated_at'] = time();
        $format[] = '%d';

        return (bool) $wpdb->update(
            self::table(),
            $update,
            array( 'id' => $id ),
            $format,
            array( '%d' )
        );
    }
    public static function link_user( $id, $wp_user_id ) {
        return self::update( $id, array( 'wp_user_id' => $wp_user_id ) );
    }
}
