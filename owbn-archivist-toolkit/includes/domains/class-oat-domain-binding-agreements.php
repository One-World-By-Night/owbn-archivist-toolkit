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
            'agreement_template' => array(
                'label'    => 'Agreement Text',
                'type'     => 'htmlarea',
                'required' => true,
            ),
            'agreement_area' => array(
                'label'    => 'Specific terms or scope of this agreement',
                'type'     => 'textarea',
                'required' => true,
            ),
            'sig_player' => array(
                'label'    => 'Player Signature',
                'type'     => 'signature',
                'required' => true,
            ),
            'sig_chronicle' => array(
                'label'    => 'Chronicle Representative Signature',
                'type'     => 'signature',
                'required' => true,
            ),
            'sig_coordinator' => array(
                'label'    => 'Coordinator Signature (if applicable)',
                'type'     => 'signature',
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
        if ( empty( $meta['agreement_template'] ) ) {
            return new WP_Error( 'missing_template', 'Agreement text is required.' );
        }

        if ( empty( $meta['agreement_area'] ) ) {
            return new WP_Error( 'missing_area', 'Agreement scope/terms are required.' );
        }

        // Validate player signature is agreed.
        if ( ! empty( $meta['sig_player'] ) ) {
            $sig = is_string( $meta['sig_player'] ) ? json_decode( $meta['sig_player'], true ) : $meta['sig_player'];
            if ( ! is_array( $sig ) || empty( $sig['agreed'] ) ) {
                return new WP_Error( 'missing_player_sig', 'Player signature is required.' );
            }
        } else {
            return new WP_Error( 'missing_player_sig', 'Player signature is required.' );
        }

        // Validate chronicle signature is agreed.
        if ( ! empty( $meta['sig_chronicle'] ) ) {
            $sig = is_string( $meta['sig_chronicle'] ) ? json_decode( $meta['sig_chronicle'], true ) : $meta['sig_chronicle'];
            if ( ! is_array( $sig ) || empty( $sig['agreed'] ) ) {
                return new WP_Error( 'missing_chronicle_sig', 'Chronicle representative signature is required.' );
            }
        } else {
            return new WP_Error( 'missing_chronicle_sig', 'Chronicle representative signature is required.' );
        }

        return true;
    }

    /**
     * Seed DB-driven form fields for binding_agreements.
     *
     * @return int Number of rows inserted.
     */
    public function seed_form_fields() {
        if ( ! class_exists( 'OAT_Form_Field' ) ) {
            return 0;
        }

        return OAT_Form_Field::seed( 'binding_agreements', array(
            // ── Submit context ──────────────────────────────────────────────
            array(
                'context'    => 'submit',
                'field_key'  => 'agreement_template',
                'field_type' => 'htmlarea',
                'label'      => 'Agreement Text',
                'required'   => 1,
                'sort_order' => 10,
                'help_text'  => 'Pre-canned template, editable by submitter.',
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'agreement_area',
                'field_type'      => 'textarea',
                'label'           => 'Specific terms or scope of this agreement',
                'required'        => 1,
                'sort_order'      => 20,
                'attributes_json' => wp_json_encode( array( 'rows' => 6 ) ),
            ),
            array(
                'context'    => 'submit',
                'field_key'  => 'section_signatures',
                'field_type' => 'heading',
                'label'      => 'Signatures',
                'sort_order' => 30,
            ),
            array(
                'context'    => 'submit',
                'field_key'  => 'sig_player',
                'field_type' => 'signature',
                'label'      => 'Player Signature',
                'required'   => 1,
                'sort_order' => 40,
            ),
            array(
                'context'    => 'submit',
                'field_key'  => 'sig_chronicle',
                'field_type' => 'signature',
                'label'      => 'Chronicle Representative Signature',
                'required'   => 1,
                'sort_order' => 50,
            ),
            array(
                'context'    => 'submit',
                'field_key'  => 'sig_coordinator',
                'field_type' => 'signature',
                'label'      => 'Coordinator Signature (if applicable)',
                'required'   => 0,
                'sort_order' => 60,
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'submitter_name',
                'field_type'      => 'auto_prop',
                'label'           => 'Submitter Name',
                'required'        => 1,
                'sort_order'      => 70,
                'attributes_json' => wp_json_encode( array( 'source' => 'user_name' ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'submitter_email',
                'field_type'      => 'auto_prop',
                'label'           => 'Submitter Email',
                'required'        => 1,
                'sort_order'      => 80,
                'attributes_json' => wp_json_encode( array( 'source' => 'user_email' ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'submitter_pid',
                'field_type'      => 'auto_prop',
                'label'           => 'Player ID',
                'required'        => 1,
                'sort_order'      => 90,
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
