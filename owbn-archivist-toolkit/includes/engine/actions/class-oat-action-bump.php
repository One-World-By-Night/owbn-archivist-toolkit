<?php

defined( 'ABSPATH' ) || exit;

class OAT_Action_Bump {
    public static function execute( $entry, $user_id, $data = array() ) {
        // Verify active timer exists.
        $timer = OAT_Timer::active_for_entry( (int) $entry->id );
        if ( ! $timer ) {
            return new WP_Error( 'no_timer', 'No active timer to bump.' );
        }

        // Increment bump count (D-045: do NOT reset or extend timer).
        OAT_Timer::increment_bump( (int) $timer->id );

        // Log timeline event.
        OAT_Timeline::append( array(
            'entry_id'        => (int) $entry->id,
            'action_type'     => OAT_Constants::ACTION_BUMP,
            'actor_id'        => $user_id,
            'step'            => $entry->current_step,
            'visibility_tier' => OAT_Constants::TIER_STAFF,
            'note'            => isset( $data['note'] ) ? $data['note'] : 'Bump.',
        ) );

        return true;
    }
}
