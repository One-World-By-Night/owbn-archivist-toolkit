<?php

defined( 'ABSPATH' ) || exit;

class OAT_Notification {

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'oat_notifications';
    }
    public static function create( $data ) {
        global $wpdb;

        if ( empty( $data['entry_id'] ) || empty( $data['user_id'] ) || empty( $data['channel'] ) || empty( $data['event_type'] ) ) {
            return 0;
        }

        $valid_channels = array(
            OAT_Constants::CHANNEL_EMAIL,
            OAT_Constants::CHANNEL_DASHBOARD,
            OAT_Constants::CHANNEL_API,
        );
        if ( ! in_array( $data['channel'], $valid_channels, true ) ) {
            return 0;
        }

        $wpdb->insert( self::table(), array(
            'entry_id'   => $data['entry_id'],
            'user_id'    => $data['user_id'],
            'channel'    => $data['channel'],
            'event_type' => $data['event_type'],
            'status'     => OAT_Constants::NOTIFICATION_PENDING,
            'created_at' => time(),
        ), array( '%d', '%d', '%s', '%s', '%s', '%d' ) );
        return (int) $wpdb->insert_id;
    }
    public static function mark_sent( $id ) {
        global $wpdb;
        return (bool) $wpdb->update(
            self::table(),
            array( 'status' => OAT_Constants::NOTIFICATION_SENT, 'sent_at' => time() ),
            array( 'id' => $id ),
            array( '%s', '%d' ),
            array( '%d' )
        );
    }
    public static function mark_failed( $id ) {
        global $wpdb;
        return (bool) $wpdb->update(
            self::table(),
            array( 'status' => OAT_Constants::NOTIFICATION_FAILED ),
            array( 'id' => $id ),
            array( '%s' ),
            array( '%d' )
        );
    }
    public static function for_entry( $entry_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE entry_id = %d ORDER BY created_at ASC',
            $entry_id
        ) );
    }
    public static function for_user( $user_id, $args = array() ) {
        global $wpdb;
        $table = self::table();

        $where  = array( 'user_id = %d' );
        $values = array( $user_id );

        if ( isset( $args['status'] ) ) {
            $where[]  = 'status = %s';
            $values[] = $args['status'];
        }
        if ( isset( $args['channel'] ) ) {
            $where[]  = 'channel = %s';
            $values[] = $args['channel'];
        }

        $sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY created_at DESC';

        $per_page = isset( $args['per_page'] ) ? absint( $args['per_page'] ) : 20;
        $offset   = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;
        $sql .= " LIMIT {$per_page} OFFSET {$offset}";

        return $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
    }

    /**
     * @return array
     */
    public static function pending() {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE status = %s ORDER BY created_at ASC',
            OAT_Constants::NOTIFICATION_PENDING
        ) );
    }
    public static function delete_for_entry( $entry_id ) {
        global $wpdb;
        return (bool) $wpdb->delete( self::table(), array( 'entry_id' => $entry_id ), array( '%d' ) );
    }
}
