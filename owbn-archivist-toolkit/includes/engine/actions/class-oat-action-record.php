<?php

defined( 'ABSPATH' ) || exit;

class OAT_Action_Record {

    /**
     * Terminal action — records the entry as approved.
     *
     * @param object $entry
     * @param int    $user_id
     * @param array  $data Keys: 'note' (required).
     * @return true|WP_Error
     */
    public static function execute( $entry, $user_id, $data = array() ) {
        if ( empty( $data['note'] ) ) {
            return new WP_Error( 'missing_note', 'A note is required.' );
        }

        // Cancel any active timer before terminal status.
        $timer = OAT_Timer::active_for_entry( (int) $entry->id );
        if ( $timer ) {
            OAT_Timer::cancel( (int) $timer->id );
        }

        // Set entry status to approved — terminal.
        OAT_Entry::update_status( (int) $entry->id, OAT_Constants::STATUS_APPROVED, $entry->current_step );

        // Log timeline event at archivist tier.
        OAT_Timeline::append( array(
            'entry_id'        => (int) $entry->id,
            'action_type'     => OAT_Constants::ACTION_RECORD,
            'actor_id'        => $user_id,
            'step'            => $entry->current_step,
            'visibility_tier' => OAT_Constants::TIER_ARCHIVIST,
            'note'            => $data['note'],
        ) );

        return true;
    }
}
