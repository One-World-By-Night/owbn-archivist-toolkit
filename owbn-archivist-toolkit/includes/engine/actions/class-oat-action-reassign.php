<?php

defined( 'ABSPATH' ) || exit;

class OAT_Action_Reassign {
    public static function execute( $entry, $user_id, $data = array() ) {
        if ( empty( $data['note'] ) ) {
            return new WP_Error( 'missing_note', 'A note is required.' );
        }
        if ( empty( $data['target_user_id'] ) ) {
            return new WP_Error( 'missing_target', 'Target user ID is required.' );
        }

        $step = $entry->current_step;
        $target_uid = (int) $data['target_user_id'];

        // Mark current user's assignment as reassigned.
        $assignees = OAT_Assignee::for_entry_step( (int) $entry->id, $step );
        foreach ( $assignees as $a ) {
            if ( (int) $a->user_id === $user_id && $a->status === 'pending' ) {
                OAT_Assignee::update_status( (int) $a->id, 'reassigned' );
                break;
            }
        }

        // Assign the new user.
        OAT_Assignee::assign( (int) $entry->id, $target_uid, $step );

        // Get step config for timeline tier.
        $step_config = OAT_Workflow_Engine::get_step_config( $entry );
        $tier = $step_config && isset( $step_config['visibility_tier'] )
            ? $step_config['visibility_tier']
            : OAT_Constants::TIER_STAFF;

        // Log timeline event.
        OAT_Timeline::append( array(
            'entry_id'        => (int) $entry->id,
            'action_type'     => OAT_Constants::ACTION_REASSIGN,
            'actor_id'        => $user_id,
            'step'            => $step,
            'visibility_tier' => $tier,
            'note'            => $data['note'],
        ) );

        return true;
    }
}
