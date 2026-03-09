<?php

defined( 'ABSPATH' ) || exit;

class OAT_Registry_Hooks {

    public static function init() {
        add_action( 'oat_character_created', array( __CLASS__, 'on_character_created' ), 10, 2 );
        add_action( 'oat_entry_approved', array( __CLASS__, 'on_entry_approved' ), 10, 3 );
        add_action( 'oat_character_transferred', array( __CLASS__, 'on_character_transferred' ), 10, 3 );
    }

    /**
     * V.2.1 — Auto-create chronicle grant when a character is created.
     *
     * @param int   $character_id
     * @param array $data Insert data array.
     */
    public static function on_character_created( $character_id, $data ) {
        if ( empty( $data['chronicle_slug'] ) ) {
            return;
        }
        OAT_Registry_Access::ensure_grant( $character_id, 'chronicle', $data['chronicle_slug'] );
    }

    /**
     * V.2.2 — Auto-create coordinator grant when an entry is approved.
     *
     * @param int   $entry_id
     * @param int   $user_id  Acting user.
     * @param array $data     Action data.
     */
    public static function on_entry_approved( $entry_id, $user_id, $data ) {
        $entry = OAT_Entry::find( $entry_id );
        if ( ! $entry ) {
            return;
        }
        if ( empty( $entry->coordinator_genre ) || empty( $entry->character_id ) ) {
            return;
        }
        OAT_Registry_Access::ensure_grant(
            (int) $entry->character_id,
            'coordinator',
            $entry->coordinator_genre,
            $user_id
        );
    }

    /**
     * V.2.3 — Expire old chronicle grant and create new one on transfer.
     *
     * @param int         $character_id
     * @param string|null $old_chronicle Old chronicle slug.
     * @param string|null $new_chronicle New chronicle slug.
     */
    public static function on_character_transferred( $character_id, $old_chronicle, $new_chronicle ) {
        if ( $old_chronicle ) {
            OAT_Registry_Access::expire_grants( $character_id, 'chronicle', $old_chronicle );
        }
        if ( $new_chronicle ) {
            OAT_Registry_Access::ensure_grant( $character_id, 'chronicle', $new_chronicle );
        }
    }
}
