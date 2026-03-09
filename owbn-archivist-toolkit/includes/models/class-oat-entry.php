<?php

defined( 'ABSPATH' ) || exit;

class OAT_Entry {

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'oat_entries';
    }
    public static function find( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE id = %d',
            $id
        ) );
    }
    public static function all( $args = array() ) {
        global $wpdb;
        $table = self::table();

        $where  = array();
        $values = array();

        $filters = array(
            'domain'           => '%s',
            'form_slug'        => '%s',
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

        $allowed_orderby = array( 'created_at', 'updated_at', 'id', 'domain', 'form_slug', 'status' );
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
    public static function count( $args = array() ) {
        global $wpdb;
        $table = self::table();

        $where  = array();
        $values = array();

        $filters = array(
            'domain'           => '%s',
            'form_slug'        => '%s',
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
    public static function create( $data ) {
        global $wpdb;

        $allowed = array(
            'domain', 'form_slug', 'status', 'current_step', 'originator_id',
            'chronicle_slug', 'character_id', 'coordinator_genre',
        );

        $insert = array();
        $format = array();

        foreach ( $allowed as $col ) {
            if ( isset( $data[ $col ] ) ) {
                if ( in_array( $col, array( 'originator_id', 'character_id' ), true ) ) {
                    $insert[ $col ] = (int) $data[ $col ];
                } else {
                    $insert[ $col ] = sanitize_text_field( $data[ $col ] );
                }
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
    public static function update( $id, $data ) {
        global $wpdb;

        $allowed = array( 'status', 'current_step', 'chronicle_slug', 'character_id', 'coordinator_genre' );

        $update = array();
        $format = array();

        foreach ( $allowed as $col ) {
            if ( isset( $data[ $col ] ) ) {
                if ( in_array( $col, array( 'character_id' ), true ) ) {
                    $update[ $col ] = (int) $data[ $col ];
                } else {
                    $update[ $col ] = sanitize_text_field( $data[ $col ] );
                }
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
     * Check whether an entry can be deleted (D-030, 7.9b).
     *
     * Requires: current user is WP administrator AND entry has never
     * reached coordinator or archivist tier (checked via timeline).
     *
     * @param object $entry
     * @return bool
     */
    public static function can_delete( $entry ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'oat_timeline';
        $reached = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE entry_id = %d AND visibility_tier IN (%s, %s)",
            (int) $entry->id,
            OAT_Constants::TIER_COORDINATOR,
            OAT_Constants::TIER_ARCHIVIST
        ) );

        return 0 === $reached;
    }

    /**
     * Delete an entry and all related records (7.9c cascade).
     *
     * @param int $id
     * @return bool
     */
    public static function delete_cascade( $id ) {
        OAT_Entry_Meta::delete_for_entry( $id );
        OAT_Timeline::delete_for_entry( $id );
        OAT_Assignee::delete_for_entry( $id );
        OAT_Watcher::delete_for_entry( $id );
        OAT_Entry_Rule::delete_for_entry( $id );
        OAT_Timer::delete_for_entry( $id );
        OAT_Notification::delete_for_entry( $id );

        if ( class_exists( 'OAT_Entry_Relationship' ) ) {
            OAT_Entry_Relationship::delete_for_entry( $id );
        }

        return self::delete( $id );
    }
    public static function delete( $id ) {
        global $wpdb;
        return (bool) $wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );
    }
    public static function for_originator( $user_id, $args = array() ) {
        $args['originator_id'] = $user_id;
        return self::all( $args );
    }
}
