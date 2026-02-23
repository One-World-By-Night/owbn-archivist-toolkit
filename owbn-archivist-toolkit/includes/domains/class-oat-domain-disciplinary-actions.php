<?php

defined( 'ABSPATH' ) || exit;

class OAT_Domain_Disciplinary_Actions implements OAT_Domain_Interface {

    /**
     * @return string
     */
    public function get_slug() {
        return 'disciplinary_actions';
    }

    /**
     * @return string
     */
    public function get_label() {
        return 'Disciplinary Actions';
    }

    /**
     * Two conditional sub-flows determined by da_type meta (D-060):
     *   Chronicle: Submit → Staff Review → Coordinator Review → Archivist (auto).
     *   Global:    Submit → Exec Review → Council Vote → Archivist (auto).
     *
     * DAs are data records — no approval cycles.
     *
     * @return array
     */
    public function get_workflow_template() {
        return array(
            array(
                'id'                 => 'submit',
                'label'              => 'Submission',
                'assignee_role'      => '',
                'visibility_tier'    => OAT_Constants::TIER_STAFF,
                'on_approve'         => 'staff_review',
                'on_deny'            => null,
                'on_request_changes' => null,
                'timer'              => null,
                'condition'          => null,
                'multi_approve'      => false,
            ),
            // ── Chronicle DA path ──────────────────────────────────────
            array(
                'id'                 => 'staff_review',
                'label'              => 'Staff Review',
                'assignee_role'      => 'Staff',
                'visibility_tier'    => OAT_Constants::TIER_STAFF,
                'on_approve'         => 'coord_review',
                'on_deny'            => null,
                'on_request_changes' => 'submit',
                'timer'              => null,
                'condition'          => array(
                    'meta_key' => 'da_type',
                    'operator' => '=',
                    'value'    => 'chronicle',
                ),
                'multi_approve'      => false,
            ),
            array(
                'id'                 => 'coord_review',
                'label'              => 'Coordinator Review',
                'assignee_role'      => 'Coordinator',
                'visibility_tier'    => OAT_Constants::TIER_COORDINATOR,
                'on_approve'         => 'archivist',
                'on_deny'            => null,
                'on_request_changes' => 'submit',
                'timer'              => null,
                'condition'          => array(
                    'meta_key' => 'da_type',
                    'operator' => '=',
                    'value'    => 'chronicle',
                ),
                'multi_approve'      => false,
            ),
            // ── Global DA path ─────────────────────────────────────────
            array(
                'id'                 => 'exec_review',
                'label'              => 'Executive Review',
                'assignee_role'      => 'Exec/Head-Coordinator/Coordinator',
                'visibility_tier'    => OAT_Constants::TIER_COORDINATOR,
                'on_approve'         => 'council_vote',
                'on_deny'            => null,
                'on_request_changes' => 'submit',
                'timer'              => null,
                'condition'          => array(
                    'meta_key' => 'da_type',
                    'operator' => '=',
                    'value'    => 'global',
                ),
                'multi_approve'      => false,
            ),
            array(
                'id'                 => 'council_vote',
                'label'              => 'Council Vote',
                'assignee_role'      => 'Exec/Head-Coordinator',
                'visibility_tier'    => OAT_Constants::TIER_COORDINATOR,
                'on_approve'         => 'archivist',
                'on_deny'            => null,
                'on_request_changes' => null,
                'timer'              => null,
                'condition'          => array(
                    'meta_key' => 'da_type',
                    'operator' => '=',
                    'value'    => 'global',
                ),
                'multi_approve'      => false,
            ),
            // ── Shared terminal step ───────────────────────────────────
            array(
                'id'                 => 'archivist',
                'label'              => 'Archivist Review',
                'assignee_role'      => 'Exec/Archivist/Coordinator',
                'visibility_tier'    => OAT_Constants::TIER_ARCHIVIST,
                'on_approve'         => null,
                'on_deny'            => null,
                'on_request_changes' => null,
                'timer'              => null,
                'condition'          => null,
                'multi_approve'      => false,
            ),
        );
    }

    /**
     * @return array
     */
    public function get_meta_keys() {
        return array(
            'da_type' => array(
                'label'    => 'Type of Disciplinary Action',
                'type'     => 'select',
                'required' => true,
                'options'  => array(
                    'chronicle' => 'Chronicle / Local',
                    'global'    => 'Global',
                ),
            ),
            'player_name' => array(
                'label'    => 'Player receiving DA',
                'type'     => 'text',
                'required' => true,
            ),
            'chronicle_slug' => array(
                'label'    => 'Chronicle issuing DA',
                'type'     => 'chronicle_picker',
                'required' => true,
            ),
            'reporter_name' => array(
                'label'    => 'Person reporting / filing DA',
                'type'     => 'text',
                'required' => true,
            ),
            'da_details' => array(
                'label'    => 'Details of the disciplinary action',
                'type'     => 'htmlarea',
                'required' => true,
            ),
            'da_action' => array(
                'label'    => 'Action taken',
                'type'     => 'textarea',
                'required' => true,
            ),
            'vote_link' => array(
                'label'    => 'Link to Council vote',
                'type'     => 'url',
                'required' => false,
            ),
        );
    }

    /**
     * @param object $entry
     * @param array  $meta
     * @return true|WP_Error
     */
    public function validate( $entry, $meta ) {
        $valid_types = array( 'chronicle', 'global' );
        if ( empty( $meta['da_type'] ) || ! in_array( $meta['da_type'], $valid_types, true ) ) {
            return new WP_Error( 'invalid_da_type', 'DA type must be chronicle or global.' );
        }

        if ( empty( $meta['player_name'] ) ) {
            return new WP_Error( 'missing_player', 'Player name is required.' );
        }

        if ( empty( $meta['reporter_name'] ) ) {
            return new WP_Error( 'missing_reporter', 'Reporter name is required.' );
        }

        if ( empty( $meta['da_details'] ) ) {
            return new WP_Error( 'missing_details', 'DA details are required.' );
        }

        if ( empty( $meta['da_action'] ) ) {
            return new WP_Error( 'missing_action', 'Action taken is required.' );
        }

        return true;
    }

    /**
     * Seed DB-driven form fields for disciplinary_actions.
     *
     * @return int Number of rows inserted.
     */
    public function seed_form_fields() {
        if ( ! class_exists( 'OAT_Form_Field' ) ) {
            return 0;
        }

        return OAT_Form_Field::seed( 'disciplinary_actions', array(
            // ── Submit context ──────────────────────────────────────────────
            array(
                'context'      => 'submit',
                'field_key'    => 'da_type',
                'field_type'   => 'select',
                'label'        => 'Type of Disciplinary Action',
                'required'     => 1,
                'sort_order'   => 10,
                'options_json' => wp_json_encode( array(
                    'chronicle' => 'Chronicle / Local',
                    'global'    => 'Global',
                ) ),
            ),
            array(
                'context'    => 'submit',
                'field_key'  => 'player_name',
                'field_type' => 'text',
                'label'      => 'Player receiving DA',
                'required'   => 1,
                'sort_order' => 20,
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'chronicle_slug',
                'field_type'      => 'chronicle_picker',
                'label'           => 'Chronicle issuing DA',
                'required'        => 1,
                'sort_order'      => 30,
                'condition_json'  => wp_json_encode( array(
                    'field_key' => 'da_type',
                    'value'     => 'chronicle',
                ) ),
            ),
            array(
                'context'    => 'submit',
                'field_key'  => 'reporter_name',
                'field_type' => 'text',
                'label'      => 'Person reporting / filing DA',
                'required'   => 1,
                'sort_order' => 40,
            ),
            array(
                'context'    => 'submit',
                'field_key'  => 'da_details',
                'field_type' => 'htmlarea',
                'label'      => 'Details of the disciplinary action',
                'required'   => 1,
                'sort_order' => 50,
            ),
            array(
                'context'    => 'submit',
                'field_key'  => 'da_action',
                'field_type' => 'textarea',
                'label'      => 'Action taken',
                'required'   => 1,
                'sort_order' => 60,
            ),
            array(
                'context'        => 'submit',
                'field_key'      => 'vote_link',
                'field_type'     => 'url',
                'label'          => 'Link to Council vote (if applicable)',
                'required'       => 0,
                'sort_order'     => 70,
                'condition_json' => wp_json_encode( array(
                    'field_key' => 'da_type',
                    'value'     => 'global',
                ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'submitter_name',
                'field_type'      => 'auto_prop',
                'label'           => 'Submitter Name',
                'required'        => 1,
                'sort_order'      => 80,
                'attributes_json' => wp_json_encode( array( 'source' => 'user_name' ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'submitter_email',
                'field_type'      => 'auto_prop',
                'label'           => 'Submitter Email',
                'required'        => 1,
                'sort_order'      => 90,
                'attributes_json' => wp_json_encode( array( 'source' => 'user_email' ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'submitter_pid',
                'field_type'      => 'auto_prop',
                'label'           => 'Player ID',
                'required'        => 1,
                'sort_order'      => 100,
                'attributes_json' => wp_json_encode( array( 'source' => 'player_id' ) ),
            ),

            // ── Review context ──────────────────────────────────────────────
            array(
                'context'    => 'review',
                'field_key'  => 'section_review',
                'field_type' => 'heading',
                'label'      => 'Review',
                'sort_order' => 10,
            ),
            array(
                'context'         => 'review',
                'field_key'       => 'review_note',
                'field_type'      => 'textarea',
                'label'           => 'Review Comments',
                'required'        => 0,
                'sort_order'      => 20,
                'placeholder'     => 'Review comments...',
                'attributes_json' => wp_json_encode( array( 'rows' => 4 ) ),
            ),

            // ── Escalate context ────────────────────────────────────────────
            array(
                'context'    => 'escalate',
                'field_key'  => 'section_escalate',
                'field_type' => 'heading',
                'label'      => 'Escalation',
                'sort_order' => 10,
            ),
            array(
                'context'         => 'escalate',
                'field_key'       => 'escalation_reason',
                'field_type'      => 'textarea',
                'label'           => 'Escalation Reason',
                'required'        => 1,
                'sort_order'      => 20,
                'placeholder'     => 'Reason for escalation...',
                'attributes_json' => wp_json_encode( array( 'rows' => 4 ) ),
            ),

            // ── Resolve context ─────────────────────────────────────────────
            array(
                'context'    => 'resolve',
                'field_key'  => 'section_resolve',
                'field_type' => 'heading',
                'label'      => 'Resolution',
                'sort_order' => 10,
            ),
            array(
                'context'         => 'resolve',
                'field_key'       => 'resolution_note',
                'field_type'      => 'textarea',
                'label'           => 'Resolution Notes',
                'required'        => 0,
                'sort_order'      => 20,
                'attributes_json' => wp_json_encode( array( 'rows' => 4 ) ),
            ),
            array(
                'context'      => 'resolve',
                'field_key'    => 'resolution_type',
                'field_type'   => 'select',
                'label'        => 'Resolution Type',
                'required'     => 1,
                'sort_order'   => 30,
                'options_json' => wp_json_encode( array(
                    'approved' => 'Approved',
                    'denied'   => 'Denied',
                    'deferred' => 'Deferred',
                    'returned' => 'Returned',
                ) ),
            ),
        ) );
    }

    /**
     * @return string
     */
    public function get_archivist_mode() {
        return 'auto';
    }
}
