<?php

defined( 'ABSPATH' ) || exit;

class OAT_Domain_Binding_Agreements implements OAT_Domain_Interface {

    /**
     * @return string
     */
    public function get_slug() {
        return 'binding_agreements';
    }

    /**
     * @return string
     */
    public function get_label() {
        return 'Binding Agreements';
    }

    /**
     * Workflow: Player/Staff → Staff Review → Coordinator Review → Archivist (auto).
     *
     * Exec team notified on ALL BAs as canned notification.
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
            array(
                'id'                 => 'staff_review',
                'label'              => 'Staff Review',
                'assignee_role'      => 'Chronicle/{chronicle_slug}/HST',
                'visibility_tier'    => OAT_Constants::TIER_STAFF,
                'on_approve'         => 'coord_review',
                'on_deny'            => null,
                'on_request_changes' => 'submit',
                'timer'              => null,
                'condition'          => null,
                'multi_approve'      => false,
            ),
            array(
                'id'                 => 'coord_review',
                'label'              => 'Coordinator Review',
                'assignee_role'      => 'Coordinator/{coordinator_genre}/Coordinator',
                'visibility_tier'    => OAT_Constants::TIER_COORDINATOR,
                'on_approve'         => 'archivist',
                'on_deny'            => null,
                'on_request_changes' => 'staff_review',
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
            'agreement_terms' => array(
                'label'    => 'Agreement Terms',
                'type'     => 'textarea',
                'required' => true,
            ),
            'parties' => array(
                'label'    => 'Parties Involved',
                'type'     => 'textarea',
                'required' => true,
            ),
            'selected_coordinator' => array(
                'label'    => 'Selected Coordinator',
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
                array( 'key' => 'agreement_terms', 'type' => 'textarea', 'label' => 'Agreement Terms', 'required' => true ),
                array( 'key' => 'parties', 'type' => 'textarea', 'label' => 'Parties Involved', 'required' => true ),
                array( 'key' => 'selected_coordinator', 'type' => 'text', 'label' => 'Selected Coordinator', 'required' => false ),
                array( 'key' => 'note', 'type' => 'textarea', 'label' => 'Notes', 'required' => false ),
            ),
            'review' => array(
                array( 'key' => 'summary', 'type' => 'readonly', 'label' => 'Agreement Summary' ),
                array( 'key' => 'note', 'type' => 'textarea', 'label' => 'Review Note', 'required' => true ),
            ),
        );
    }

    /**
     * @param object $entry
     * @param array  $meta
     * @return true|WP_Error
     */
    public function validate( $entry, $meta ) {
        if ( empty( $meta['agreement_terms'] ) ) {
            return new WP_Error( 'missing_terms', 'Agreement terms are required.' );
        }

        if ( empty( $meta['parties'] ) ) {
            return new WP_Error( 'missing_parties', 'Parties involved is required.' );
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
