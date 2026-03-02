<?php

defined( 'ABSPATH' ) || exit;

class OAT_Domain_Governance_Records implements OAT_Domain_Interface {

    /**
     * @return string
     */
    public function get_slug() {
        return 'governance_records';
    }

    /**
     * @return string
     */
    public function get_label() {
        return 'Governance Records';
    }

    /**
     * Workflow: Admin → Archivist (auto). Single pass-through, no loop.
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
                'on_approve'         => 'archivist',
                'on_deny'            => null,
                'on_request_changes' => null,
                'timer'              => null,
                'condition'          => null,
                'multi_approve'      => false,
            ),
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
            'reporter_name',
            'record_details',
            'associated_user',
            'associated_vote',
            'record_type',
        );
    }
    public function validate( $entry, $meta ) {
        if ( empty( $meta['reporter_name'] ) ) {
            return new WP_Error( 'missing_reporter', 'Reporter name is required.' );
        }

        if ( empty( $meta['record_details'] ) ) {
            return new WP_Error( 'missing_details', 'Record details are required.' );
        }

        $valid_types = array( 'interim_appointment', 'election', 'policy_change', 'organizational', 'other' );
        if ( empty( $meta['record_type'] ) || ! in_array( $meta['record_type'], $valid_types, true ) ) {
            return new WP_Error( 'invalid_record_type', 'Invalid record type.' );
        }

        return true;
    }

    /**
     * Seed DB-driven form fields for governance_records.
     *
     * @return int Number of rows inserted.
     */
    public function seed_form_fields() {
        if ( ! class_exists( 'OAT_Form_Field' ) ) {
            return 0;
        }

        return OAT_Form_Field::seed( 'governance_records', array(
            // ── Submit context ──────────────────────────────────────────────
            array(
                'context'    => 'submit',
                'field_key'  => 'reporter_name',
                'field_type' => 'text',
                'label'      => 'Reporter',
                'required'   => 1,
                'sort_order' => 10,
            ),
            array(
                'context'    => 'submit',
                'field_key'  => 'record_details',
                'field_type' => 'htmlarea',
                'label'      => 'Details of governance record',
                'required'   => 1,
                'sort_order' => 20,
            ),
            array(
                'context'    => 'submit',
                'field_key'  => 'associated_user',
                'field_type' => 'text',
                'label'      => 'Associated User',
                'required'   => 0,
                'sort_order' => 30,
                'help_text'  => 'e.g., interim coordinator appointee',
            ),
            array(
                'context'    => 'submit',
                'field_key'  => 'associated_vote',
                'field_type' => 'url',
                'label'      => 'Associated Vote Link',
                'required'   => 0,
                'sort_order' => 40,
                'help_text'  => 'e.g., election result',
            ),
            array(
                'context'      => 'submit',
                'field_key'    => 'record_type',
                'field_type'   => 'select',
                'label'        => 'Record Type',
                'required'     => 1,
                'sort_order'   => 50,
                'options_json' => wp_json_encode( array(
                    'interim_appointment' => 'Interim Appointment',
                    'election'            => 'Election Result',
                    'policy_change'       => 'Policy Change',
                    'organizational'      => 'Organizational Record',
                    'other'               => 'Other',
                ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'submitter_name',
                'field_type'      => 'auto_prop',
                'label'           => 'Submitter Name',
                'required'        => 1,
                'sort_order'      => 60,
                'attributes_json' => wp_json_encode( array( 'source' => 'user_name' ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'submitter_email',
                'field_type'      => 'auto_prop',
                'label'           => 'Submitter Email',
                'required'        => 1,
                'sort_order'      => 70,
                'attributes_json' => wp_json_encode( array( 'source' => 'user_email' ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'submitter_pid',
                'field_type'      => 'auto_prop',
                'label'           => 'Player ID',
                'required'        => 1,
                'sort_order'      => 80,
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
