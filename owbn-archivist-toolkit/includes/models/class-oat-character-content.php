<?php

defined( 'ABSPATH' ) || exit;

class OAT_Character_Content {

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'oat_character_content';
    }

    public static function create( $data ) {
        global $wpdb;
        $wpdb->insert( self::table(), array(
            'character_id'     => (int) $data['character_id'],
            'entry_id'         => isset( $data['entry_id'] ) ? (int) $data['entry_id'] : null,
            'content_type'     => isset( $data['content_type'] ) ? $data['content_type'] : '',
            'content_name'     => isset( $data['content_name'] ) ? $data['content_name'] : '',
            'coordinator_genre' => isset( $data['coordinator_genre'] ) ? $data['coordinator_genre'] : null,
            'created_at'       => time(),
        ) );
        return $wpdb->insert_id ? (int) $wpdb->insert_id : 0;
    }

    public static function for_character( $character_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE character_id = %d ORDER BY content_type ASC, content_name ASC',
            $character_id
        ) );
    }

    public static function find_by_entry( $entry_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE entry_id = %d LIMIT 1',
            $entry_id
        ) );
    }

    public static function delete( $id ) {
        global $wpdb;
        return (bool) $wpdb->delete( self::table(), array( 'id' => (int) $id ), array( '%d' ) );
    }

    /**
     * Format a content record for display.
     *
     * @param object $record DB row.
     * @return string e.g. "Combination Discipline / Technique: Monsterfucker Allure - Giovanni Coordinator"
     */
    public static function format_label( $record ) {
        $label = 'Custom ' . $record->content_type . ': ' . $record->content_name;
        if ( $record->coordinator_genre ) {
            $title = function_exists( 'owc_entity_get_title' )
                ? owc_entity_get_title( 'coordinator', $record->coordinator_genre )
                : ucfirst( $record->coordinator_genre );
            if ( $title ) {
                $label .= ' - ' . $title;
            }
        }
        return $label;
    }
}
