<?php

defined( 'ABSPATH' ) || exit;

class OAT_Action_Submit {

    /**
     * @param object $entry
     * @param int    $user_id
     * @param array  $data Keys: 'note' (optional), 'meta' (optional array of key => value).
     * @return true|WP_Error
     */
    public static function execute( $entry, $user_id, $data = array() ) {

        $domain = OAT_Domain_Registry::get( $entry->domain );

        // Domain-specific validation.
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

        // Get step config for timeline tier.
        $step_config = OAT_Workflow_Engine::get_step_config( $entry );
        $tier = $step_config && isset( $step_config['visibility_tier'] )
            ? $step_config['visibility_tier']
            : OAT_Constants::TIER_STAFF;

        // Set entry status to pending.
        OAT_Entry::update_status( (int) $entry->id, OAT_Constants::STATUS_PENDING, $entry->current_step );

        // Log timeline event.
        OAT_Timeline::append( array(
            'entry_id'        => (int) $entry->id,
            'action_type'     => OAT_Constants::ACTION_SUBMIT,
            'actor_id'        => $user_id,
            'step'            => $entry->current_step,
            'visibility_tier' => $tier,
            'note'            => isset( $data['note'] ) ? $data['note'] : '',
        ) );

        // Determine next step and advance.
        $next_step = OAT_Workflow_Engine::get_next_step( $entry, $entry->current_step, 'approve' );
        if ( $next_step !== null ) {
            OAT_Workflow_Engine::advance_to_step( $entry, $next_step );
        }

        return true;
    }
}
