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
            'record_type' => array(
                'label'    => 'Record Type',
                'type'     => 'select',
                'required' => true,
                'options'  => array(
                    'council_vote'   => 'Council Vote',
                    'bylaw_change'   => 'Bylaw Change',
                    'election'       => 'Election Result',
                    'policy_update'  => 'Policy Update',
                    'other'          => 'Other',
                ),
            ),
            'record_title' => array(
                'label'    => 'Record Title',
                'type'     => 'text',
                'required' => true,
            ),
            'record_body' => array(
                'label'    => 'Record Body',
                'type'     => 'textarea',
                'required' => true,
            ),
            'effective_date' => array(
                'label'    => 'Effective Date',
                'type'     => 'text',
                'required' => false,
            ),
        );
    }

    /**
     * @return array
     */
    public function get_form_fields() {
        return array(
            'submit' => array(
                array( 'key' => 'record_type', 'type' => 'select', 'label' => 'Record Type', 'required' => true, 'options' => array(
                    'council_vote'  => 'Council Vote',
                    'bylaw_change'  => 'Bylaw Change',
                    'election'      => 'Election Result',
                    'policy_update' => 'Policy Update',
                    'other'         => 'Other',
                ) ),
                array( 'key' => 'record_title', 'type' => 'text', 'label' => 'Record Title', 'required' => true ),
                array( 'key' => 'record_body', 'type' => 'textarea', 'label' => 'Record Body', 'required' => true ),
                array( 'key' => 'effective_date', 'type' => 'text', 'label' => 'Effective Date', 'required' => false ),
                array( 'key' => 'note', 'type' => 'textarea', 'label' => 'Notes', 'required' => false ),
            ),
            'review' => array(
                array( 'key' => 'summary', 'type' => 'readonly', 'label' => 'Record Summary' ),
                array( 'key' => 'note', 'type' => 'textarea', 'label' => 'Review Note', 'required' => false ),
            ),
        );
    }

    /**
     * @param object $entry
     * @param array  $meta
     * @return true|WP_Error
     */
    public function validate( $entry, $meta ) {
        $valid_types = array( 'council_vote', 'bylaw_change', 'election', 'policy_update', 'other' );
        if ( empty( $meta['record_type'] ) || ! in_array( $meta['record_type'], $valid_types, true ) ) {
            return new WP_Error( 'invalid_record_type', 'Invalid record type.' );
        }

        if ( empty( $meta['record_title'] ) ) {
            return new WP_Error( 'missing_title', 'Record title is required.' );
        }

        if ( empty( $meta['record_body'] ) ) {
            return new WP_Error( 'missing_body', 'Record body is required.' );
        }

        return true;
    }

    /**
     * @return string
     */
    public function get_archivist_mode() {
        return 'auto';
    }
}
