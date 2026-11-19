<?php

defined( 'ABSPATH' ) || exit;

/**
 * Approves, in one go, every line of a group that is waiting at the same step as this one and that the person can approve.
 * Each line is approved through the normal approve action (so each keeps its own timeline and its own checks); the
 * per-line emails are muted and one email per person goes out for the group.
 */
class OAT_Action_Approve_All {
    public static function execute( $entry, $user_id, $data = array() ) {
        if ( empty( $data['note'] ) ) {
            return new WP_Error( 'missing_note', 'A note is required.' );
        }
        $ids = OAT_Batch::line_ids( (int) $entry->id );
        if ( count( $ids ) < 2 ) {
            return new WP_Error( 'not_in_group', 'This request is not part of a group.' );
        }

        $step    = $entry->current_step;
        $done    = array();
        $refused = array();
        OAT_Batch::mute();
        foreach ( $ids as $line_id ) {
            $line = OAT_Entry::find( (int) $line_id );
            if ( ! $line || OAT_Constants::is_terminal_status( $line->status ) || $line->current_step !== $step ) {
                continue;
            }
            $result = OAT_Workflow_Engine::process_action( (int) $line_id, OAT_Constants::ACTION_APPROVE, (int) $user_id, array( 'note' => $data['note'] ) );
            if ( is_wp_error( $result ) ) {
                $refused[ (int) $line_id ] = $result->get_error_message();
                continue;
            }
            $done[] = (int) $line_id;
        }
        OAT_Batch::unmute();

        if ( ! $done ) {
            return new WP_Error( 'nothing_to_approve', $refused ? reset( $refused ) : 'No line of this group is waiting for you.' );
        }
        OAT_Batch::announce_progress( $done, (int) $user_id );
        return true;
    }
}
