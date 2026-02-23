<?php

defined( 'ABSPATH' ) || exit;

class OAT_Action_Council_Override {

    /**
     * ONLY action that operates on terminal (denied) entries.
     * Reopens a denied entry and advances to archivist step.
     *
     * @param object $entry
     * @param int    $user_id
     * @param array  $data Keys: 'note' (required), 'vote_reference', 'vote_type', 'closing_date'.
     * @return true|WP_Error
     */
    public static function execute( $entry, $user_id, $data = array() ) {
        if ( empty( $data['note'] ) ) {
            return new WP_Error( 'missing_note', 'A note is required.' );
        }

        // Must be operating on a denied entry.
        if ( $entry->status !== OAT_Constants::STATUS_DENIED ) {
            return new WP_Error( 'not_denied', 'Council override can only reopen denied entries.' );
        }

        // Require vote metadata.
        if ( empty( $data['vote_reference'] ) ) {
            return new WP_Error( 'missing_vote_ref', 'Vote reference is required.' );
        }

        // Store vote metadata.
        OAT_Entry_Meta::set( (int) $entry->id, 'council_vote_reference', $data['vote_reference'] );
        if ( ! empty( $data['vote_type'] ) ) {
            OAT_Entry_Meta::set( (int) $entry->id, 'council_vote_type', $data['vote_type'] );
        }
        if ( ! empty( $data['closing_date'] ) ) {
            OAT_Entry_Meta::set( (int) $entry->id, 'council_closing_date', $data['closing_date'] );
        }

        // Reopen — set status back to pending (force bypass terminal check).
        OAT_Entry::update_status( (int) $entry->id, OAT_Constants::STATUS_PENDING, $entry->current_step, true );

        // Log timeline preserving original denial + override.
        OAT_Timeline::append( array(
            'entry_id'        => (int) $entry->id,
            'action_type'     => OAT_Constants::ACTION_COUNCIL_OVERRIDE,
            'actor_id'        => $user_id,
            'step'            => $entry->current_step,
            'visibility_tier' => OAT_Constants::TIER_ARCHIVIST,
            'note'            => $data['note'],
        ) );

        // Find the archivist step in the workflow template and advance to it.
        $template = self::get_workflow_template( $entry->domain );
        $archivist_step = null;
        foreach ( $template as $step ) {
            if ( isset( $step['visibility_tier'] ) && $step['visibility_tier'] === OAT_Constants::TIER_ARCHIVIST ) {
                $archivist_step = $step['id'];
                break;
            }
        }
        if ( $archivist_step !== null ) {
            OAT_Workflow_Engine::advance_to_step( $entry, $archivist_step );
        }

        return true;
    }

    /**
     * Get workflow template for a domain (DB first, PHP fallback).
     *
     * @param string $domain_slug Domain slug.
     * @return array Array of step config arrays.
     */
    private static function get_workflow_template( $domain_slug ) {
        // DB-driven steps (D-055).
        if ( class_exists( 'OAT_Workflow_Step' ) ) {
            $steps = OAT_Workflow_Step::get_workflow_template( $domain_slug );
            if ( ! empty( $steps ) ) {
                return $steps;
            }
        }

        // PHP domain class fallback.
        $domain = OAT_Domain_Registry::get_php_domain( $domain_slug );
        if ( $domain ) {
            return $domain->get_workflow_template();
        }

        return array();
    }
}
