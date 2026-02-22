<?php

defined( 'ABSPATH' ) || exit;

class OAT_Timeline {

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'oat_timeline';
    }

    /**
     * @param array $data
     * @return int Insert ID.
     */
    public static function append( $data ) {
        global $wpdb;

        if ( empty( $data['entry_id'] ) || empty( $data['action_type'] ) || empty( $data['visibility_tier'] ) ) {
            return 0;
        }

        if ( ! OAT_Constants::is_valid_action( $data['action_type'] ) ) {
            return 0;
        }

        if ( ! OAT_Constants::is_valid_tier( $data['visibility_tier'] ) ) {
            return 0;
        }

        $user_id = isset( $data['user_id'] ) ? $data['user_id'] : ( isset( $data['actor_id'] ) ? $data['actor_id'] : null );

        $insert = array(
            'entry_id'        => $data['entry_id'],
            'action_type'     => $data['action_type'],
            'visibility_tier' => $data['visibility_tier'],
            'step'            => isset( $data['step'] ) ? $data['step'] : '',
            'note'            => isset( $data['note'] ) ? $data['note'] : '',
            'created_at'      => time(),
        );
        $format = array( '%d', '%s', '%s', '%s', '%s', '%d' );

        if ( $user_id !== null ) {
            $insert['user_id'] = $user_id;
            $format[]          = '%d';
        }

        $wpdb->insert( self::table(), $insert, $format );
        $id = (int) $wpdb->insert_id;

        // Set user_id to NULL explicitly if not provided.
        if ( $user_id === null && $id ) {
            $wpdb->query( $wpdb->prepare(
                'UPDATE ' . self::table() . ' SET user_id = NULL WHERE id = %d',
                $id
            ) );
        }

        return $id;
    }

    /**
     * @param int         $entry_id
     * @param string|null $max_tier
     * @return array
     */
    public static function for_entry( $entry_id, $max_tier = null ) {
        global $wpdb;
        $table = self::table();
        $sql   = "SELECT * FROM {$table} WHERE entry_id = %d";
        $args  = array( $entry_id );

        if ( $max_tier !== null && OAT_Constants::is_valid_tier( $max_tier ) ) {
            switch ( $max_tier ) {
                case OAT_Constants::TIER_STAFF:
                    $sql   .= ' AND visibility_tier = %s';
                    $args[] = OAT_Constants::TIER_STAFF;
                    break;
                case OAT_Constants::TIER_COORDINATOR:
                    $sql   .= ' AND visibility_tier IN (%s, %s)';
                    $args[] = OAT_Constants::TIER_STAFF;
                    $args[] = OAT_Constants::TIER_COORDINATOR;
                    break;
                // TIER_ARCHIVIST — no filter, sees all.
            }
        }

        $sql .= ' ORDER BY created_at ASC';
        return $wpdb->get_results( $wpdb->prepare( $sql, $args ) );
    }

    /**
     * @param int $entry_id
     * @return int
     */
    public static function count_for_entry( $entry_id ) {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            'SELECT COUNT(*) FROM ' . self::table() . ' WHERE entry_id = %d',
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
