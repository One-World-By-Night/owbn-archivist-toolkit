<?php

defined( 'ABSPATH' ) || exit;

class OAT_Action_Submit {
    public static function execute( $entry, $user_id, $data = array() ) {

        $domain = OAT_Domain_Registry::get_php_domain( $entry->domain );

        // Domain-specific validation (PHP class hook).
        if ( $domain ) {
            // Use provided meta, or read from DB (page handler saves meta before calling this).
            if ( isset( $data['meta'] ) && ! empty( $data['meta'] ) ) {
                $meta = $data['meta'];
            } else {
                $db_meta = OAT_Entry_Meta::get_all( (int) $entry->id );
                $meta = array();
                foreach ( $db_meta as $m ) {
                    $meta[ $m->meta_key ] = $m->meta_value;
                }
            }
            $valid = $domain->validate( $entry, $meta );
            if ( is_wp_error( $valid ) ) {
                return $valid;
            }
        }

        // Save meta values.
        if ( ! empty( $data['meta'] ) && is_array( $data['meta'] ) ) {
            foreach ( $data['meta'] as $key => $value ) {
                OAT_Entry_Meta::set( (int) $entry->id, $key, $value );
            }
        }

        // Check for super-user fast-track routing.
        $is_fast_track = false;
        if ( function_exists( 'owc_oat_is_super_user' ) && owc_oat_is_super_user( $user_id ) ) {
            $archivist_config = OAT_Workflow_Engine::get_step_config( $entry, 'archivist' );
            if ( $archivist_config ) {
                $is_fast_track = true;
            }
        }

        // Get step config for timeline tier.
        $step_config = OAT_Workflow_Engine::get_step_config( $entry );
        $tier = $step_config && isset( $step_config['visibility_tier'] )
            ? $step_config['visibility_tier']
            : OAT_Constants::TIER_STAFF;

        // Set entry status to pending.
        OAT_Entry::update_status( (int) $entry->id, OAT_Constants::STATUS_PENDING, $entry->current_step );

        // Build timeline note.
        $note = isset( $data['note'] ) ? $data['note'] : '';
        if ( $is_fast_track ) {
            $user_obj = get_userdata( $user_id );
            $ft_label = 'Fast-tracked by ' . ( $user_obj ? $user_obj->display_name : "User #{$user_id}" );
            $note     = $note ? $ft_label . '. ' . $note : $ft_label;
        }

        // Log timeline event.
        OAT_Timeline::append( array(
            'entry_id'        => (int) $entry->id,
            'action_type'     => OAT_Constants::ACTION_SUBMIT,
            'actor_id'        => $user_id,
            'step'            => $entry->current_step,
            'visibility_tier' => $is_fast_track ? OAT_Constants::TIER_ARCHIVIST : $tier,
            'note'            => $note,
        ) );

        // Route to next step.
        if ( $is_fast_track ) {
            // Fast-track: skip intermediate steps, go directly to archivist.
            OAT_Workflow_Engine::advance_to_step( $entry, 'archivist' );
        } else {
            // Normal routing via workflow template.
            $next_step = OAT_Workflow_Engine::get_next_step( $entry, $entry->current_step, 'approve' );
            if ( $next_step !== null ) {
                OAT_Workflow_Engine::advance_to_step( $entry, $next_step );
            }
        }

        return true;
    }
}
