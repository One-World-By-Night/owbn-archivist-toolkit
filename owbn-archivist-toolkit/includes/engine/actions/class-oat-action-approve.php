<?php

defined( 'ABSPATH' ) || exit;

class OAT_Action_Approve {

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

        $step = $entry->current_step;

        // Update this user's assignee status to approved.
        $assignees = OAT_Assignee::for_entry_step( (int) $entry->id, $step );
        $found = false;
        foreach ( $assignees as $a ) {
            if ( (int) $a->user_id === $user_id && $a->status === 'pending' ) {
                OAT_Assignee::update_status( (int) $a->id, 'approved' );
                $found = true;
                break;
            }
        }

        if ( ! $found ) {
            return new WP_Error( 'not_assigned', 'User is not a pending assignee at this step.' );
        }

        // Get step config for timeline tier.
        $step_config = OAT_Workflow_Engine::get_step_config( $entry );
        $tier = $step_config && isset( $step_config['visibility_tier'] )
            ? $step_config['visibility_tier']
            : OAT_Constants::TIER_STAFF;

        // Log timeline event.
        OAT_Timeline::append( array(
            'entry_id'        => (int) $entry->id,
            'action_type'     => OAT_Constants::ACTION_APPROVE,
            'actor_id'        => $user_id,
            'step'            => $step,
            'visibility_tier' => $tier,
            'note'            => $data['note'],
        ) );

        // Check if all assignees have approved.
        if ( ! OAT_Assignee::all_approved( (int) $entry->id, $step ) ) {
            return true; // Wait for others.
        }

        // All approved — cancel any active timer.
        $timer = OAT_Timer::active_for_entry( (int) $entry->id );
        if ( $timer ) {
            OAT_Timer::cancel( (int) $timer->id );
        }

        // Determine next step.
        $next_step = OAT_Workflow_Engine::get_next_step( $entry, $step, 'approve' );

        if ( $next_step === null ) {
            // Terminal — entry is approved.
            OAT_Entry::update_status( (int) $entry->id, OAT_Constants::STATUS_APPROVED, $step );

            // If archivist mode is auto, log record event.
            $domain = OAT_Domain_Registry::get( $entry->domain );
            if ( $domain && $domain->get_archivist_mode() === 'auto' ) {
                OAT_Timeline::append( array(
                    'entry_id'        => (int) $entry->id,
                    'action_type'     => OAT_Constants::ACTION_RECORD,
                    'actor_id'        => null,
                    'step'            => $step,
                    'visibility_tier' => OAT_Constants::TIER_ARCHIVIST,
                    'note'            => 'Auto-recorded.',
                ) );
            }

            return true;
        }

        // Advance to next step.
        OAT_Workflow_Engine::advance_to_step( $entry, $next_step );

        return true;
    }
}
