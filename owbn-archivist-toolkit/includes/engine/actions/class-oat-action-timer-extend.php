<?php

defined( 'ABSPATH' ) || exit;

class OAT_Action_Timer_Extend {

    /**
     * Extend an active timer. Requires exec oversight permission.
     *
     * @param object $entry
     * @param int    $user_id
     * @param array  $data Keys: 'note' (required), 'additional_seconds' (required).
     * @return true|WP_Error
     */
    public static function execute( $entry, $user_id, $data = array() ) {
        if ( empty( $data['note'] ) ) {
            return new WP_Error( 'missing_note', 'A note is required.' );
        }
        if ( empty( $data['additional_seconds'] ) || (int) $data['additional_seconds'] <= 0 ) {
            return new WP_Error( 'missing_duration', 'Additional seconds must be positive.' );
        }

        // Verify active timer exists.
        $timer = OAT_Timer::active_for_entry( (int) $entry->id );
        if ( ! $timer ) {
            return new WP_Error( 'no_timer', 'No active timer to extend.' );
        }

        // Extend timer.
        OAT_Timer::extend( (int) $timer->id, (int) $data['additional_seconds'] );

        // Log timeline event.
        OAT_Timeline::append( array(
            'entry_id'        => (int) $entry->id,
            'action_type'     => OAT_Constants::ACTION_TIMER_EXTEND,
            'actor_id'        => $user_id,
            'step'            => $entry->current_step,
            'visibility_tier' => OAT_Constants::TIER_COORDINATOR,
            'note'            => $data['note'],
        ) );

        return true;
    }
}
