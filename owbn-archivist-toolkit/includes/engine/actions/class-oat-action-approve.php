<?php

defined( 'ABSPATH' ) || exit;

class OAT_Action_Approve {
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

        // Role-path-based: user holds the ASC role matching the step's assignee_role.
        if ( ! $found && function_exists( 'owc_oat_user_can_act_on_step' ) && owc_oat_user_can_act_on_step( $entry, $user_id ) ) {
            OAT_Assignee::assign( (int) $entry->id, $user_id, $step );
            $new_assignees = OAT_Assignee::for_entry_step( (int) $entry->id, $step );
            foreach ( $new_assignees as $a ) {
                if ( (int) $a->user_id === $user_id && $a->status === 'pending' ) {
                    OAT_Assignee::update_status( (int) $a->id, 'approved' );
                    $found = true;
                    break;
                }
            }
        }

        // Self-approve: originator at archivist step with self-approve privilege (WP Admin or exec/archivist).
        if ( ! $found && $step === 'archivist' && (int) $entry->originator_id === $user_id
            && function_exists( 'owc_oat_can_self_approve' ) && owc_oat_can_self_approve( $user_id ) ) {
            OAT_Assignee::assign( (int) $entry->id, $user_id, $step );
            $new_assignees = OAT_Assignee::for_entry_step( (int) $entry->id, $step );
            foreach ( $new_assignees as $a ) {
                if ( (int) $a->user_id === $user_id && $a->status === 'pending' ) {
                    OAT_Assignee::update_status( (int) $a->id, 'approved' );
                    $found = true;
                    break;
                }
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

        // Check if step requires all assignees or just one.
        $step_config = OAT_Workflow_Engine::get_step_config( $entry );
        $multi = $step_config && ! empty( $step_config['multi_approve'] );
        if ( $multi && ! OAT_Assignee::all_approved( (int) $entry->id, $step ) ) {
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
            if ( OAT_Domain_Registry::get_archivist_mode( $entry->domain ) === 'auto' ) {
                OAT_Timeline::append( array(
                    'entry_id'        => (int) $entry->id,
                    'action_type'     => OAT_Constants::ACTION_RECORD,
                    'actor_id'        => null,
                    'step'            => $step,
                    'visibility_tier' => OAT_Constants::TIER_ARCHIVIST,
                    'note'            => 'Auto-logged.',
                ) );
            }

            do_action( 'oat_entry_approved', (int) $entry->id, $user_id, $data );

            // DA-001: Process me-too child entries for disciplinary_actions.
            if ( $entry->domain === 'disciplinary_actions' && class_exists( 'OAT_Entry_Meta' ) ) {
                $me_too = OAT_Entry_Meta::get( (int) $entry->id, 'me_too_chronicles' );
                if ( $me_too ) {
                    $chronicles = is_string( $me_too ) ? json_decode( $me_too, true ) : (array) $me_too;
                    if ( is_array( $chronicles ) ) {
                        // Wrap single slug in array.
                        if ( ! empty( $chronicles ) && ! is_array( reset( $chronicles ) ) ) {
                            $slugs = array_filter( array_values( $chronicles ) );
                        } else {
                            $slugs = $chronicles;
                        }
                        // If it's a single string slug, wrap it.
                        if ( is_string( $me_too ) && strpos( $me_too, '[' ) !== 0 ) {
                            $slugs = array( $me_too );
                        }
                        foreach ( $slugs as $chron_slug ) {
                            if ( empty( $chron_slug ) || $chron_slug === $entry->chronicle_slug ) {
                                continue; // Skip the source chronicle.
                            }
                            // Create child entry.
                            $child_id = OAT_Entry::create( array(
                                'domain'         => 'disciplinary_actions',
                                'chronicle_slug' => $chron_slug,
                                'status'          => OAT_Constants::STATUS_APPROVED,
                                'current_step'    => 'archivist',
                                'submitted_by'    => $user_id,
                            ) );
                            if ( $child_id ) {
                                // Copy meta from parent.
                                $copy_keys = array( 'player_name', 'player_user_id', 'da_details', 'da_action', 'da_type', 'reporter_name' );
                                foreach ( $copy_keys as $mk ) {
                                    $mv = OAT_Entry_Meta::get( (int) $entry->id, $mk );
                                    if ( $mv !== null && $mv !== '' ) {
                                        OAT_Entry_Meta::set( $child_id, $mk, $mv );
                                    }
                                }
                                // Create relationship.
                                OAT_Entry_Relationship::create( (int) $entry->id, $child_id, 'me_too' );
                                // Timeline.
                                OAT_Timeline::append( array(
                                    'entry_id'        => $child_id,
                                    'action_type'     => OAT_Constants::ACTION_SUBMIT,
                                    'actor_id'        => null,
                                    'step'            => 'archivist',
                                    'visibility_tier' => OAT_Constants::TIER_ARCHIVIST,
                                    'note'            => sprintf( 'Me-too entry from #%d.', (int) $entry->id ),
                                ) );
                            }
                        }
                    }
                }
            }

            return true;
        }

        // Advance to next step.
        OAT_Workflow_Engine::advance_to_step( $entry, $next_step );

        return true;
    }
}
