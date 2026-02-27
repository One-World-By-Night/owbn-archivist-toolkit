<?php

defined( 'ABSPATH' ) || exit;

class OAT_Action_Auto_Approve {

    /**
     * BBP auto-approve. Submitter-initiated (not cron).
     *
     * Conditions (D-045): timer expired + bump_count >= bump_required + no elevation.
     *
     * @param object $entry
     * @param int    $user_id The submitter who invokes auto-approve.
     * @param array  $data Keys: 'note' (optional).
     * @return true|WP_Error
     */
    public static function execute( $entry, $user_id, $data = array() ) {
        // Verify timer conditions.
        $timer = OAT_Timer::active_for_entry( (int) $entry->id );
        if ( ! $timer ) {
            return new WP_Error( 'no_timer', 'No active timer found.' );
        }

        // Timer must be expired.
        if ( (int) $timer->expires_at > time() ) {
            return new WP_Error( 'timer_active', 'Timer has not expired yet.' );
        }

        // Bump count must meet requirement.
        if ( (int) $timer->bump_count < (int) $timer->bump_required ) {
            return new WP_Error( 'insufficient_bumps', 'Not enough bumps for auto-approve.' );
        }

        // No elevation on linked rules (D-046).
        if ( OAT_Entry_Rule::has_elevation( (int) $entry->id ) ) {
            return new WP_Error( 'elevation_block', 'Entry has elevated rules; cannot auto-approve.' );
        }

        // Fire the timer.
        OAT_Timer::fire( (int) $timer->id );

        // Determine if this is the final step.
        $next_step = OAT_Workflow_Engine::get_next_step( $entry, $entry->current_step, 'approve' );

        if ( $next_step === null ) {
            // Terminal — set to auto_approved.
            OAT_Entry::update_status( (int) $entry->id, OAT_Constants::STATUS_AUTO_APPROVED, $entry->current_step );
            do_action( 'oat_entry_approved', (int) $entry->id, $user_id, $data );
        } else {
            // Advance to next step.
            OAT_Workflow_Engine::advance_to_step( $entry, $next_step );
        }

        // Log timeline with submitter as actor.
        $note = ! empty( $data['note'] ) ? $data['note'] : 'Auto-approved via BBP.';
        OAT_Timeline::append( array(
            'entry_id'        => (int) $entry->id,
            'action_type'     => OAT_Constants::ACTION_AUTO_APPROVE,
            'actor_id'        => $user_id,
            'step'            => $entry->current_step,
            'visibility_tier' => OAT_Constants::TIER_STAFF,
            'note'            => $note,
        ) );

        return true;
    }
}
