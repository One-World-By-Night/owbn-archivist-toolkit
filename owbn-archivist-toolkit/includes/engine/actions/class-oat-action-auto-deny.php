<?php

defined( 'ABSPATH' ) || exit;

class OAT_Action_Auto_Deny {

    /**
     * Timer-fired auto-deny. Called by cron/discovery, not by a user.
     *
     * @param object $entry
     * @param int    $user_id Not used (system action), but kept for interface consistency.
     * @param array  $data Keys: 'note' (optional).
     * @return true|WP_Error
     */
    public static function execute( $entry, $user_id, $data = array() ) {
        // Timer is fired by process_expired_timers() after dispatching this action.
        // No need to fire here — doing so causes a double-fire.

        // Set entry status to auto_denied — terminal.
        OAT_Entry::update_status( (int) $entry->id, OAT_Constants::STATUS_AUTO_DENIED, $entry->current_step );

        // Log timeline with user_id = NULL (system).
        $note = ! empty( $data['note'] ) ? $data['note'] : 'Auto-denied: timer expired without action.';
        OAT_Timeline::append( array(
            'entry_id'        => (int) $entry->id,
            'action_type'     => OAT_Constants::ACTION_AUTO_DENY,
            'actor_id'        => null,
            'step'            => $entry->current_step,
            'visibility_tier' => OAT_Constants::TIER_STAFF,
            'note'            => $note,
        ) );

        return true;
    }
}
