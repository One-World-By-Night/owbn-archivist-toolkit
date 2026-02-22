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
     * Two sub-flows determined by da_scope meta:
     *   Local:  Submit → Archivist (auto). No loop.
     *   Global: Submit → Exec Review → Archivist (auto).
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
                'on_approve'         => 'exec_review',
                'on_deny'            => null,
                'on_request_changes' => null,
                'timer'              => null,
                'condition'          => null,
                'multi_approve'      => false,
            ),
            array(
                'id'                 => 'exec_review',
                'label'              => 'Executive Review',
                'assignee_role'      => 'Exec/Head-Coordinator/Coordinator',
                'visibility_tier'    => OAT_Constants::TIER_COORDINATOR,
                'on_approve'         => 'archivist',
                'on_deny'            => null,
                'on_request_changes' => 'submit',
                'timer'              => null,
                'condition'          => array(
                    'meta_key' => 'da_scope',
                    'operator' => '=',
                    'value'    => 'global',
                ),
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
            'da_scope' => array(
                'label'    => 'Scope',
                'type'     => 'select',
                'required' => true,
                'options'  => array(
                    'local'  => 'Local',
                    'global' => 'Global',
                ),
            ),
            'subject_name' => array(
                'label'    => 'Subject Name',
                'type'     => 'text',
                'required' => true,
            ),
            'action_taken' => array(
                'label'    => 'Action Taken',
                'type'     => 'textarea',
                'required' => true,
            ),
            'reason' => array(
                'label'    => 'Reason',
                'type'     => 'textarea',
                'required' => true,
            ),
            'duration' => array(
                'label'    => 'Duration',
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
                array( 'key' => 'da_scope', 'type' => 'select', 'label' => 'Scope', 'required' => true, 'options' => array(
                    'local'  => 'Local',
                    'global' => 'Global',
                ) ),
                array( 'key' => 'subject_name', 'type' => 'text', 'label' => 'Subject Name', 'required' => true ),
                array( 'key' => 'action_taken', 'type' => 'textarea', 'label' => 'Action Taken', 'required' => true ),
                array( 'key' => 'reason', 'type' => 'textarea', 'label' => 'Reason', 'required' => true ),
                array( 'key' => 'duration', 'type' => 'text', 'label' => 'Duration', 'required' => false ),
                array( 'key' => 'note', 'type' => 'textarea', 'label' => 'Notes', 'required' => false ),
            ),
            'review' => array(
                array( 'key' => 'summary', 'type' => 'readonly', 'label' => 'DA Summary' ),
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
        $valid_scopes = array( 'local', 'global' );
        if ( empty( $meta['da_scope'] ) || ! in_array( $meta['da_scope'], $valid_scopes, true ) ) {
            return new WP_Error( 'invalid_scope', 'Scope must be local or global.' );
        }

        if ( empty( $meta['subject_name'] ) ) {
            return new WP_Error( 'missing_subject', 'Subject name is required.' );
        }

        if ( empty( $meta['action_taken'] ) ) {
            return new WP_Error( 'missing_action', 'Action taken is required.' );
        }

        if ( empty( $meta['reason'] ) ) {
            return new WP_Error( 'missing_reason', 'Reason is required.' );
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
