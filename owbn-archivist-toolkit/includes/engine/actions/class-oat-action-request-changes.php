<?php

defined( 'ABSPATH' ) || exit;

class OAT_Action_Request_Changes {

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

        $current_step = $entry->current_step;

        // Cancel any active timer.
        $timer = OAT_Timer::active_for_entry( (int) $entry->id );
        if ( $timer ) {
            OAT_Timer::cancel( (int) $timer->id );
        }

        // Determine previous step from on_request_changes.
        $prev_step = OAT_Workflow_Engine::get_next_step( $entry, $current_step, 'request_changes' );
        if ( $prev_step === null ) {
            return new WP_Error( 'no_prev_step', 'No previous step configured for request changes.' );
        }

        // Reset all assignee approvals at the current step (D-019).
        OAT_Assignee::reset_step( (int) $entry->id, $current_step );

        // Get step config for timeline tier.
        $step_config = OAT_Workflow_Engine::get_step_config( $entry );
        $tier = $step_config && isset( $step_config['visibility_tier'] )
            ? $step_config['visibility_tier']
            : OAT_Constants::TIER_STAFF;

        // Log timeline event.
        OAT_Timeline::append( array(
            'entry_id'        => (int) $entry->id,
            'action_type'     => OAT_Constants::ACTION_REQUEST_CHANGES,
            'actor_id'        => $user_id,
            'step'            => $current_step,
            'visibility_tier' => $tier,
            'note'            => $data['note'],
        ) );

        // Update entry current_step and re-assign at previous step.
        OAT_Entry::update( (int) $entry->id, array( 'current_step' => $prev_step ) );

        // Clear stale assignees at the previous step before re-assigning.
        OAT_Assignee::clear_step( (int) $entry->id, $prev_step );

        // Re-assign at the previous step.
        $prev_config = OAT_Workflow_Engine::get_step_config( $entry, $prev_step );
        if ( $prev_config && ! empty( $prev_config['assignee_role'] ) ) {
            $user_ids = OAT_Workflow_Engine::resolve_assignees( $entry, $prev_config['assignee_role'] );
            foreach ( $user_ids as $uid ) {
                OAT_Assignee::assign( (int) $entry->id, (int) $uid, $prev_step );
            }
        }

        return true;
    }
}
