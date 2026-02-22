<?php
/**
 * OAT Deactivation.
 *
 * Cleanup on plugin deactivation. Tables are preserved — data is never
 * dropped on deactivation (D-030).
 */

defined( 'ABSPATH' ) || exit;

class OAT_Uninstall {

    /**
     * Run on plugin deactivation.
     *
     * @return void
     */
    public static function deactivate() {
        // Clear any scheduled cron events.
        $timestamp = wp_next_scheduled( 'oat_process_timers' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'oat_process_timers' );
        }
    }
}
