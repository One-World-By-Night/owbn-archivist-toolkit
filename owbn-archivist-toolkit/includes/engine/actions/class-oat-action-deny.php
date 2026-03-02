<?php

defined( 'ABSPATH' ) || exit;

class OAT_Action_Deny {
    public static function execute( $entry, $user_id, $data = array() ) {
        if ( empty( $data['note'] ) ) {
            return new WP_Error( 'missing_note', 'A note is required.' );
        }

        $step = $entry->current_step;

        // Update this user's assignee status to denied.
        $assignees = OAT_Assignee::for_entry_step( (int) $entry->id, $step );
        $found = false;
        foreach ( $assignees as $a ) {
            if ( (int) $a->user_id === $user_id && $a->status === 'pending' ) {
                OAT_Assignee::update_status( (int) $a->id, 'denied' );
                $found = true;
                break;
            }
        }

        // Role-path-based: user holds the ASC role matching the step's assignee_role.
        if ( ! $found && function_exists( 'owc_oat_user_can_act_on_step' ) && owc_oat_user_can_act_on_step( $entry, $user_id ) ) {
            OAT_Assignee::assign( (int) $entry->id, $user_id, $step );
            $new_assignees = OAT_Assignee::for_entry_step( (int) $entry->id, $step );
            foreach ( $new_assignees as $a ) {
                if ( (int) $a->user_id === $user_id && $a->status === 'pending' ) {
                    OAT_Assignee::update_status( (int) $a->id, 'denied' );
                    $found = true;
                    break;
                }
            }
        }

        if ( ! $found ) {
            return new WP_Error( 'not_assigned', 'User is not a pending assignee at this step.' );
        }

        // Cancel any active timer.
        $timer = OAT_Timer::active_for_entry( (int) $entry->id );
        if ( $timer ) {
            OAT_Timer::cancel( (int) $timer->id );
        }

        // Set entry status to denied — terminal.
        OAT_Entry::update_status( (int) $entry->id, OAT_Constants::STATUS_DENIED, $step );

        // Get step config for timeline tier.
        $step_config = OAT_Workflow_Engine::get_step_config( $entry );
        $tier = $step_config && isset( $step_config['visibility_tier'] )
            ? $step_config['visibility_tier']
            : OAT_Constants::TIER_STAFF;

        // Log timeline event.
        OAT_Timeline::append( array(
            'entry_id'        => (int) $entry->id,
            'action_type'     => OAT_Constants::ACTION_DENY,
            'actor_id'        => $user_id,
            'step'            => $step,
            'visibility_tier' => $tier,
            'note'            => $data['note'],
        ) );

        return true;
    }
}
