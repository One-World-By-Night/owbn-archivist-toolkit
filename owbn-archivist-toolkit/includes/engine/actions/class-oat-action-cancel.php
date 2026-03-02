<?php

defined( 'ABSPATH' ) || exit;

class OAT_Action_Cancel {
    public static function execute( $entry, $user_id, $data = array() ) {
        if ( empty( $data['note'] ) ) {
            return new WP_Error( 'missing_note', 'A note is required.' );
        }

        // Only originator can cancel.
        if ( $user_id !== (int) $entry->originator_id ) {
            return new WP_Error( 'not_originator', 'Only the originator can cancel an entry.' );
        }

        // Cancel any active timer.
        $timer = OAT_Timer::active_for_entry( (int) $entry->id );
        if ( $timer ) {
            OAT_Timer::cancel( (int) $timer->id );
        }

        // Set entry status to cancelled — terminal.
        OAT_Entry::update_status( (int) $entry->id, OAT_Constants::STATUS_CANCELLED, $entry->current_step );

        // Log timeline event.
        OAT_Timeline::append( array(
            'entry_id'        => (int) $entry->id,
            'action_type'     => OAT_Constants::ACTION_CANCEL,
            'actor_id'        => $user_id,
            'step'            => $entry->current_step,
            'visibility_tier' => OAT_Constants::TIER_STAFF,
            'note'            => $data['note'],
        ) );

        return true;
    }
}
