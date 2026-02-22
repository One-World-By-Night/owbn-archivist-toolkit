<?php

defined( 'ABSPATH' ) || exit;

class OAT_Timer {

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'oat_timers';
    }

    /**
     * @param array $data
     * @return int Insert ID.
     */
    public static function create( $data ) {
        global $wpdb;

        $required = array( 'entry_id', 'step', 'auto_action', 'bump_required', 'started_at', 'expires_at' );
        foreach ( $required as $field ) {
            if ( ! isset( $data[ $field ] ) ) {
                return 0;
            }
        }

        $wpdb->insert( self::table(), array(
            'entry_id'      => $data['entry_id'],
            'step'          => $data['step'],
            'status'        => OAT_Constants::TIMER_ACTIVE,
            'auto_action'   => $data['auto_action'],
            'bump_required' => $data['bump_required'],
            'bump_count'    => 0,
            'started_at'    => $data['started_at'],
            'expires_at'    => $data['expires_at'],
            'created_at'    => time(),
        ), array( '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d' ) );
        return (int) $wpdb->insert_id;
    }

    /**
     * @param int $entry_id
     * @return object|null
     */
    public static function active_for_entry( $entry_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE entry_id = %d AND status = %s',
            $entry_id,
            OAT_Constants::TIMER_ACTIVE
        ) );
    }

    /**
     * @param int    $id
     * @param string $status
     * @return bool
     */
    public static function update_status( $id, $status ) {
        $valid = array(
            OAT_Constants::TIMER_ACTIVE,
            OAT_Constants::TIMER_PAUSED,
            OAT_Constants::TIMER_FIRED,
            OAT_Constants::TIMER_CANCELLED,
        );
        if ( ! in_array( $status, $valid, true ) ) {
            return false;
        }

        global $wpdb;
        return (bool) $wpdb->update(
            self::table(),
            array( 'status' => $status ),
            array( 'id' => $id ),
            array( '%s' ),
            array( '%d' )
        );
    }

    /**
     * @param int $id
     * @return bool
     */
    public static function pause( $id ) {
        global $wpdb;
        return (bool) $wpdb->update(
            self::table(),
            array( 'status' => OAT_Constants::TIMER_PAUSED, 'paused_at' => time() ),
            array( 'id' => $id ),
            array( '%s', '%d' ),
            array( '%d' )
        );
    }

    /**
     * @param int $id
     * @param int $remaining_seconds
     * @return bool
     */
    public static function resume( $id, $remaining_seconds ) {
        global $wpdb;
        return (bool) $wpdb->query( $wpdb->prepare(
            'UPDATE ' . self::table() . ' SET status = %s, expires_at = %d, paused_at = NULL WHERE id = %d',
            OAT_Constants::TIMER_ACTIVE,
            time() + $remaining_seconds,
            $id
        ) );
    }

    /**
     * @param int $id
     * @return bool
     */
    public static function fire( $id ) {
        global $wpdb;
        return (bool) $wpdb->update(
            self::table(),
            array( 'status' => OAT_Constants::TIMER_FIRED, 'fired_at' => time() ),
            array( 'id' => $id ),
            array( '%s', '%d' ),
            array( '%d' )
        );
    }

    /**
     * @param int $id
     * @return bool
     */
    public static function cancel( $id ) {
        global $wpdb;
        return (bool) $wpdb->update(
            self::table(),
            array( 'status' => OAT_Constants::TIMER_CANCELLED ),
            array( 'id' => $id ),
            array( '%s' ),
            array( '%d' )
        );
    }

    /**
     * @param int $id
     * @return int New bump count.
     */
    public static function increment_bump( $id ) {
        global $wpdb;
        $wpdb->query( $wpdb->prepare(
            'UPDATE ' . self::table() . ' SET bump_count = bump_count + 1 WHERE id = %d',
            $id
        ) );
        return (int) $wpdb->get_var( $wpdb->prepare(
            'SELECT bump_count FROM ' . self::table() . ' WHERE id = %d',
            $id
        ) );
    }

    /**
     * @param int $id
     * @param int $additional_seconds
     * @return bool
     */
    public static function extend( $id, $additional_seconds ) {
        global $wpdb;
        return (bool) $wpdb->query( $wpdb->prepare(
            'UPDATE ' . self::table() . ' SET expires_at = expires_at + %d WHERE id = %d',
            $additional_seconds,
            $id
        ) );
    }

    /**
     * @return array Active timers that have passed their expires_at.
     */
    public static function expired() {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE status = %s AND expires_at <= %d',
            OAT_Constants::TIMER_ACTIVE,
            time()
        ) );
    }

    /**
     * @param int $entry_id
     * @return array All timer rows (historical + active).
     */
    public static function for_entry( $entry_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE entry_id = %d ORDER BY created_at ASC',
            $entry_id
        ) );
    }

    /**
     * @param int $entry_id
     * @return bool
     */
    public static function delete_for_entry( $entry_id ) {
        global $wpdb;
        return (bool) $wpdb->delete( self::table(), array( 'entry_id' => $entry_id ), array( '%d' ) );
    }
}
