<?php

defined( 'ABSPATH' ) || exit;

class OAT_Assignee {

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'oat_assignees';
    }

    private static function entries_table() {
        global $wpdb;
        return $wpdb->prefix . 'oat_entries';
    }
    public static function assign( $entry_id, $user_id, $step ) {
        global $wpdb;
        $wpdb->insert( self::table(), array(
            'entry_id'    => $entry_id,
            'user_id'     => $user_id,
            'step'        => $step,
            'status'      => 'pending',
            'assigned_at' => time(),
        ), array( '%d', '%d', '%s', '%s', '%d' ) );
        return (int) $wpdb->insert_id;
    }
    public static function update_status( $id, $status ) {
        $valid = array( 'pending', 'approved', 'denied', 'reassigned', 'delegated' );
        if ( ! in_array( $status, $valid, true ) ) {
            return false;
        }

        global $wpdb;
        $update = array( 'status' => $status );
        $format = array( '%s' );

        if ( $status !== 'pending' ) {
            $update['acted_at'] = time();
            $format[] = '%d';
        }

        return (bool) $wpdb->update( self::table(), $update, array( 'id' => $id ), $format, array( '%d' ) );
    }
    public static function for_entry_step( $entry_id, $step ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE entry_id = %d AND step = %s ORDER BY assigned_at ASC',
            $entry_id,
            $step
        ) );
    }
    public static function for_entry( $entry_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE entry_id = %d ORDER BY assigned_at ASC',
            $entry_id
        ) );
    }
    public static function inbox( $user_id ) {
        global $wpdb;
        $a = self::table();
        $e = self::entries_table();

        $terminal = array(
            OAT_Constants::STATUS_APPROVED,
            OAT_Constants::STATUS_DENIED,
            OAT_Constants::STATUS_CANCELLED,
            OAT_Constants::STATUS_AUTO_APPROVED,
            OAT_Constants::STATUS_AUTO_DENIED,
        );

        $placeholders = implode( ', ', array_fill( 0, count( $terminal ), '%s' ) );

        $sql = "SELECT a.*, e.domain, e.status AS entry_status, e.current_step
                FROM {$a} a
                JOIN {$e} e ON a.entry_id = e.id
                WHERE a.user_id = %d
                  AND a.status = 'pending'
                  AND e.status NOT IN ({$placeholders})
                ORDER BY a.assigned_at ASC";

        $args = array_merge( array( $user_id ), $terminal );
        return $wpdb->get_results( $wpdb->prepare( $sql, $args ) );
    }

    /**
     * Delete all assignees for an entry at a specific step.
     * Used when re-entering a step to prevent stale records from prior visits.
     *
     * @param int    $entry_id
     * @param string $step
     * @return bool
     */
    public static function clear_step( $entry_id, $step ) {
        global $wpdb;
        return (bool) $wpdb->query( $wpdb->prepare(
            'DELETE FROM ' . self::table() . ' WHERE entry_id = %d AND step = %s',
            $entry_id,
            $step
        ) );
    }
    public static function reset_step( $entry_id, $step ) {
        global $wpdb;
        return (bool) $wpdb->query( $wpdb->prepare(
            'UPDATE ' . self::table() . " SET status = 'pending', acted_at = NULL WHERE entry_id = %d AND step = %s",
            $entry_id,
            $step
        ) );
    }
    public static function all_approved( $entry_id, $step ) {
        global $wpdb;
        $table = self::table();

        $total = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE entry_id = %d AND step = %s",
            $entry_id,
            $step
        ) );

        if ( $total === 0 ) {
            return false;
        }

        $approved = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE entry_id = %d AND step = %s AND status = 'approved'",
            $entry_id,
            $step
        ) );

        return $total === $approved;
    }
    public static function any_denied( $entry_id, $step ) {
        global $wpdb;
        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM " . self::table() . " WHERE entry_id = %d AND step = %s AND status = 'denied'",
            $entry_id,
            $step
        ) );
    }
    public static function delete_for_entry( $entry_id ) {
        global $wpdb;
        return (bool) $wpdb->delete( self::table(), array( 'entry_id' => $entry_id ), array( '%d' ) );
    }
}
