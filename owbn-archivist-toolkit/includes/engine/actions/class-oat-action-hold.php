<?php

defined( 'ABSPATH' ) || exit;

class OAT_Action_Hold {

    /**
     * @param object $entry
     * @param int    $user_id
     * @param array  $data Keys: 'note' (required).
     * @return true|WP_Error
     */
    public static function execute( $entry, $user_id, $data = array() ) {
        if ( empty( $data['note'] ) ) {
            return new WP_Error( 'missing_note', 'A note is required.' );
        }

        if ( $entry->status !== OAT_Constants::STATUS_PENDING ) {
            return new WP_Error( 'not_pending', 'Entry must be pending to place on hold.' );
        }

        // Set entry status to held.
        OAT_Entry::update_status( (int) $entry->id, OAT_Constants::STATUS_HELD, $entry->current_step );

        // Pause active timer if present.
        $timer = OAT_Timer::active_for_entry( (int) $entry->id );
        if ( $timer ) {
            OAT_Timer::pause( (int) $timer->id );
        }

        // Get step config for timeline tier.
        $step_config = OAT_Workflow_Engine::get_step_config( $entry );
        $tier = $step_config && isset( $step_config['visibility_tier'] )
            ? $step_config['visibility_tier']
            : OAT_Constants::TIER_STAFF;

        // Log timeline event.
        OAT_Timeline::append( array(
            'entry_id'        => (int) $entry->id,
            'action_type'     => OAT_Constants::ACTION_HOLD,
            'actor_id'        => $user_id,
            'step'            => $entry->current_step,
            'visibility_tier' => $tier,
            'note'            => $data['note'],
        ) );

        return true;
    }
}
