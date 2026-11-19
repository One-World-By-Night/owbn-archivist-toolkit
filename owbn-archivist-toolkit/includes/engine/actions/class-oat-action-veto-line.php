<?php

defined( 'ABSPATH' ) || exit;

/**
 * Rejects one line of a group while the others carry on. It is the normal denial of that line, except the reason is
 * optional: when there is one it is kept with the line (veto_reason) so the filer sees it beside the line, and the
 * filer gets one email about the veto.
 */
class OAT_Action_Veto_Line {
    public static function execute( $entry, $user_id, $data = array() ) {
        if ( count( OAT_Batch::line_ids( (int) $entry->id ) ) < 2 ) {
            return new WP_Error( 'not_in_group', 'This request is not part of a group.' );
        }
        $reason = isset( $data['note'] ) ? trim( wp_strip_all_tags( (string) $data['note'] ) ) : '';

        OAT_Batch::mute();
        $result = OAT_Workflow_Engine::process_action(
            (int) $entry->id,
            OAT_Constants::ACTION_DENY,
            (int) $user_id,
            array( 'note' => '' !== $reason ? 'Vetoed: ' . $reason : 'Vetoed (no reason given).' )
        );
        OAT_Batch::unmute();
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        OAT_Entry_Meta::set( (int) $entry->id, 'veto_reason', $reason );
        OAT_Entry_Meta::set( (int) $entry->id, 'vetoed_by', (string) (int) $user_id );
        OAT_Workflow_Notify::group_vetoed( OAT_Entry::find( (int) $entry->id ), $reason );
        return true;
    }
}
