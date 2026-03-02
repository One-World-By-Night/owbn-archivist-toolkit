<?php

defined( 'ABSPATH' ) || exit;

class OAT_Action_Resume {
    public static function execute( $entry, $user_id, $data = array() ) {
        if ( empty( $data['note'] ) ) {
            return new WP_Error( 'missing_note', 'A note is required.' );
        }

        if ( $entry->status !== OAT_Constants::STATUS_HELD ) {
            return new WP_Error( 'not_held', 'Entry is not on hold.' );
        }

        // Set entry status back to pending.
        OAT_Entry::update_status( (int) $entry->id, OAT_Constants::STATUS_PENDING, $entry->current_step );

        // Resume paused timer at the current step with recalculated expiry.
        $timers = OAT_Timer::for_entry( (int) $entry->id );
        foreach ( $timers as $t ) {
            if ( $t->status === OAT_Constants::TIMER_PAUSED && $t->step === $entry->current_step ) {
                $remaining = isset( $data['remaining_seconds'] ) ? (int) $data['remaining_seconds'] : 0;
                if ( $remaining <= 0 && $t->paused_at && $t->expires_at ) {
                    // Calculate remaining from when it was paused.
                    $remaining = max( 0, (int) $t->expires_at - (int) $t->paused_at );
                }
                if ( $remaining > 0 ) {
                    OAT_Timer::resume( (int) $t->id, $remaining );
                }
                break;
            }
        }

        // Get step config for timeline tier.
        $step_config = OAT_Workflow_Engine::get_step_config( $entry );
        $tier = $step_config && isset( $step_config['visibility_tier'] )
            ? $step_config['visibility_tier']
            : OAT_Constants::TIER_STAFF;

        // Log timeline event.
        OAT_Timeline::append( array(
            'entry_id'        => (int) $entry->id,
            'action_type'     => OAT_Constants::ACTION_RESUME,
            'actor_id'        => $user_id,
            'step'            => $entry->current_step,
            'visibility_tier' => $tier,
            'note'            => $data['note'],
        ) );

        return true;
    }
}
