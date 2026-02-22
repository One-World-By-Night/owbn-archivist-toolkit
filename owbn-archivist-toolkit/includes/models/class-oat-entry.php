<?php

defined( 'ABSPATH' ) || exit;

class OAT_Entry {

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'oat_entries';
    }

    /**
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
     * @param array $args
     * @return array
     */
    public static function all( $args = array() ) {
        global $wpdb;
        $table = self::table();

        $where  = array();
        $values = array();

        $filters = array(
            'domain'           => '%s',
            'status'           => '%s',
            'originator_id'    => '%d',
            'chronicle_slug'   => '%s',
            'character_id'     => '%d',
            'coordinator_genre' => '%s',
        );

        foreach ( $filters as $col => $fmt ) {
            if ( isset( $args[ $col ] ) ) {
                $where[]  = "{$col} = {$fmt}";
                $values[] = $args[ $col ];
            }
        }

        $sql = "SELECT * FROM {$table}";
        if ( $where ) {
            $sql .= ' WHERE ' . implode( ' AND ', $where );
        }

        $allowed_orderby = array( 'created_at', 'updated_at', 'id', 'domain', 'status' );
        $orderby = isset( $args['orderby'] ) && in_array( $args['orderby'], $allowed_orderby, true )
            ? $args['orderby']
            : 'created_at';
        $order = isset( $args['order'] ) && strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY {$orderby} {$order}";

        $per_page = isset( $args['per_page'] ) ? absint( $args['per_page'] ) : 20;
        $offset   = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;
        $sql .= " LIMIT {$per_page} OFFSET {$offset}";

        if ( $values ) {
            return $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
        }
        return $wpdb->get_results( $sql );
    }

    /**
     * @param array $args
     * @return int
     */
    public static function count( $args = array() ) {
        global $wpdb;
        $table = self::table();

        $where  = array();
        $values = array();

        $filters = array(
            'domain'           => '%s',
            'status'           => '%s',
            'originator_id'    => '%d',
            'chronicle_slug'   => '%s',
            'character_id'     => '%d',
            'coordinator_genre' => '%s',
        );

        foreach ( $filters as $col => $fmt ) {
            if ( isset( $args[ $col ] ) ) {
                $where[]  = "{$col} = {$fmt}";
                $values[] = $args[ $col ];
            }
        }

        $sql = "SELECT COUNT(*) FROM {$table}";
        if ( $where ) {
            $sql .= ' WHERE ' . implode( ' AND ', $where );
        }

        if ( $values ) {
            return (int) $wpdb->get_var( $wpdb->prepare( $sql, $values ) );
        }
        return (int) $wpdb->get_var( $sql );
    }

    /**
     * @param array $data
     * @return int Insert ID.
     */
    public static function create( $data ) {
        global $wpdb;

        $allowed = array(
            'domain', 'status', 'current_step', 'originator_id',
            'chronicle_slug', 'character_id', 'coordinator_genre',
        );

        $insert = array();
        $format = array();

        foreach ( $allowed as $col ) {
            if ( isset( $data[ $col ] ) ) {
                $insert[ $col ] = $data[ $col ];
                $format[] = in_array( $col, array( 'originator_id', 'character_id' ), true ) ? '%d' : '%s';
            }
        }

        if ( isset( $insert['status'] ) && ! OAT_Constants::is_valid_status( $insert['status'] ) ) {
            return 0;
        }

        $now = time();
        $insert['created_at'] = $now;
        $insert['updated_at'] = $now;
        $format[] = '%d';
        $format[] = '%d';

        $wpdb->insert( self::table(), $insert, $format );
        return (int) $wpdb->insert_id;
    }

    /**
     * @param int   $id
     * @param array $data
     * @return bool
     */
    public static function update( $id, $data ) {
        global $wpdb;

        $allowed = array( 'status', 'current_step', 'chronicle_slug', 'character_id', 'coordinator_genre' );

        $update = array();
        $format = array();

        foreach ( $allowed as $col ) {
            if ( isset( $data[ $col ] ) ) {
                $update[ $col ] = $data[ $col ];
                $format[] = in_array( $col, array( 'character_id' ), true ) ? '%d' : '%s';
            }
        }

        if ( empty( $update ) ) {
            return false;
        }

        if ( isset( $update['status'] ) && ! OAT_Constants::is_valid_status( $update['status'] ) ) {
            return false;
        }

        $update['updated_at'] = time();
        $format[] = '%d';

        return (bool) $wpdb->update( self::table(), $update, array( 'id' => $id ), $format, array( '%d' ) );
    }

    /**
     * @param int    $id
     * @param string $status
     * @param string $step
     * @param bool   $force Bypass terminal status check (for council_override).
     * @return bool
     */
    public static function update_status( $id, $status, $step, $force = false ) {
        if ( ! OAT_Constants::is_valid_status( $status ) ) {
            return false;
        }

        $current = self::find( $id );
        if ( ! $current ) {
            return false;
        }

        if ( ! $force && OAT_Constants::is_terminal_status( $current->status ) ) {
            return false;
        }

        return self::update( $id, array(
            'status'       => $status,
            'current_step' => $step,
        ) );
    }

    /**
     * @param int $id
     * @return bool
     */
    public static function delete( $id ) {
        global $wpdb;
        return (bool) $wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );
    }

    /**
     * @param int   $user_id
     * @param array $args
     * @return array
     */
    public static function for_originator( $user_id, $args = array() ) {
        $args['originator_id'] = $user_id;
        return self::all( $args );
    }
}
