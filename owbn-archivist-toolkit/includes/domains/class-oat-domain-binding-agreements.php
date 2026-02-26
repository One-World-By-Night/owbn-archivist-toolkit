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
     * BA-003: Down-then-up signature collection workflow.
     *
     * Flow depends on submitter_role meta:
     *   Player:      submit(sig_player) → staff_review(sig_chronicle) → coord_review(sig_coordinator) → archivist
     *   Staff:       submit(sig_chronicle) → player_comment(sig_player) → staff_review(confirm) → coord_review(sig_coordinator) → archivist
     *   Coordinator: submit(sig_coordinator) → staff_comment(sig_chronicle) → player_comment(sig_player) → staff_review(confirm) → coord_review(confirm) → archivist
     *
     * Conditional steps are skipped via evaluate_condition() in advance_to_step().
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
                'on_approve'         => 'staff_comment',
                'on_deny'            => null,
                'on_request_changes' => null,
                'timer'              => null,
                'condition'          => null,
                'multi_approve'      => false,
            ),
            // Only runs when coordinator submits — HST signs sig_chronicle.
            array(
                'id'                 => 'staff_comment',
                'label'              => 'Staff Comment & Signature',
                'assignee_role'      => 'Chronicle/{chronicle_slug}/HST',
                'visibility_tier'    => OAT_Constants::TIER_STAFF,
                'on_approve'         => 'player_comment',
                'on_deny'            => null,
                'on_request_changes' => 'submit',
                'timer'              => null,
                'condition'          => array(
                    'meta_key' => 'submitter_role',
                    'operator' => '=',
                    'value'    => 'coordinator',
                ),
                'multi_approve'      => false,
            ),
            // Only runs when staff or coordinator submits — player signs sig_player.
            array(
                'id'                 => 'player_comment',
                'label'              => 'Player Comment & Signature',
                'assignee_role'      => 'User/{player_user_id}',
                'visibility_tier'    => OAT_Constants::TIER_STAFF,
                'on_approve'         => 'staff_review',
                'on_deny'            => null,
                'on_request_changes' => 'submit',
                'timer'              => null,
                'condition'          => array(
                    'meta_key' => 'submitter_role',
                    'operator' => 'in',
                    'value'    => array( 'staff', 'coordinator' ),
                ),
                'multi_approve'      => false,
            ),
            // Always runs — HST confirms or signs sig_chronicle (player path).
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
            // Always runs — coordinator confirms or signs sig_coordinator.
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
            'submitter_role' => array(
                'label'    => 'Submitter Role',
                'type'     => 'select',
                'required' => true,
                'options'  => array(
                    'player'      => 'Player',
                    'staff'       => 'Staff (HST / CM)',
                    'coordinator' => 'Coordinator',
                ),
            ),
            'player_user' => array(
                'label'    => 'Player this agreement is for',
                'type'     => 'user_picker',
                'required' => false,
            ),
            'player_user_id' => array(
                'label'    => 'Player User ID',
                'type'     => 'hidden',
                'required' => false,
            ),
            'chronicle_slug' => array(
                'label'    => 'Chronicle',
                'type'     => 'chronicle_picker',
                'required' => true,
            ),
            'coordinator_genre' => array(
                'label'    => 'Coordinator Genre',
                'type'     => 'coordinator_picker',
                'required' => true,
            ),
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
        // BA-002: submitter_role determines which signature is required at submission.
        $valid_roles = array( 'player', 'staff', 'coordinator' );
        if ( empty( $meta['submitter_role'] ) || ! in_array( $meta['submitter_role'], $valid_roles, true ) ) {
            return new WP_Error( 'invalid_role', 'Submitter role must be player, staff, or coordinator.' );
        }

        if ( empty( $meta['agreement_template'] ) ) {
            return new WP_Error( 'missing_template', 'Agreement text is required.' );
        }

        if ( empty( $meta['agreement_area'] ) ) {
            return new WP_Error( 'missing_area', 'Agreement scope/terms are required.' );
        }

        // When staff/coordinator submits, player identification is required.
        $role = $meta['submitter_role'];
        if ( in_array( $role, array( 'staff', 'coordinator' ), true ) ) {
            if ( empty( $meta['player_user'] ) && empty( $meta['player_user_id'] ) ) {
                return new WP_Error( 'missing_player', 'Player identification is required when submitting on behalf of a player.' );
            }
        }

        // Only the signature matching submitter_role is required at submission.
        // Other signatures are collected at later workflow steps.
        $sig_map = array(
            'player'      => 'sig_player',
            'staff'       => 'sig_chronicle',
            'coordinator' => 'sig_coordinator',
        );
        $required_sig = isset( $sig_map[ $role ] ) ? $sig_map[ $role ] : '';
        if ( $required_sig ) {
            if ( ! empty( $meta[ $required_sig ] ) ) {
                $sig = is_string( $meta[ $required_sig ] ) ? json_decode( $meta[ $required_sig ], true ) : $meta[ $required_sig ];
                if ( ! is_array( $sig ) || empty( $sig['agreed'] ) ) {
                    return new WP_Error( 'missing_sig', sprintf( 'Your signature (%s) is required.', $required_sig ) );
                }
            } else {
                return new WP_Error( 'missing_sig', sprintf( 'Your signature (%s) is required.', $required_sig ) );
            }
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
            // BA-002: submitter_role determines workflow path and which sig is active.
            array(
                'context'         => 'submit',
                'field_key'       => 'submitter_role',
                'field_type'      => 'select',
                'label'           => 'Your Role',
                'required'        => 1,
                'sort_order'      => 5,
                'help_text'       => 'How are you submitting this agreement?',
                'options_json'    => wp_json_encode( array(
                    'player'      => 'Player',
                    'staff'       => 'Staff (HST / CM)',
                    'coordinator' => 'Coordinator',
                ) ),
                'attributes_json' => wp_json_encode( array(
                    'role_filter' => array(
                        'staff'       => array( 'Chronicle/*/HST', 'Chronicle/*/Staff', 'Chronicle/*/CM' ),
                        'coordinator' => array( 'Coordinator/*/Coordinator' ),
                    ),
                ) ),
            ),
            // BA-002: player_user — shown when staff/coordinator submits on behalf of a player.
            array(
                'context'         => 'submit',
                'field_key'       => 'player_user',
                'field_type'      => 'user_picker',
                'label'           => 'Player this agreement is for',
                'required'        => 1,
                'sort_order'      => 6,
                'help_text'       => 'Search for the player. If not found, type their name.',
                'condition_json'  => wp_json_encode( array(
                    'field_key' => 'submitter_role',
                    'operator'  => 'in',
                    'value'     => array( 'staff', 'coordinator' ),
                ) ),
                'attributes_json' => wp_json_encode( array(
                    'fallback'    => 'free_text',
                    'store_id_in' => 'player_user_id',
                ) ),
            ),
            array(
                'context'    => 'submit',
                'field_key'  => 'player_user_id',
                'field_type' => 'hidden',
                'label'      => 'Player User ID',
                'sort_order' => 7,
            ),
            // Chronicle picker — needed for Chronicle/{chronicle_slug}/HST assignee resolution.
            // BA: all chronicles shown (no role filtering) — agreement can involve any chronicle.
            array(
                'context'         => 'submit',
                'field_key'       => 'chronicle_slug',
                'field_type'      => 'chronicle_picker',
                'label'           => 'Chronicle',
                'required'        => 1,
                'sort_order'      => 8,
                'help_text'       => 'Select the chronicle this agreement is associated with.',
                'attributes_json' => wp_json_encode( array(
                    'roles' => array( '*' ),
                ) ),
            ),
            // Coordinator genre — needed for Coordinator/{coordinator_genre}/Coordinator assignee resolution.
            array(
                'context'         => 'submit',
                'field_key'       => 'coordinator_genre',
                'field_type'      => 'coordinator_picker',
                'label'           => 'Coordinator Genre',
                'required'        => 1,
                'sort_order'      => 10,
                'help_text'       => 'Select the coordinator genre this agreement falls under.',
                'attributes_json' => wp_json_encode( array(
                    'roles' => array( 'Coordinator' ),
                ) ),
            ),
            // BA-004: Template selector populates the agreement_template htmlarea.
            array(
                'context'         => 'submit',
                'field_key'       => 'ba_template',
                'field_type'      => 'template_selector',
                'label'           => 'Agreement Template',
                'required'        => 0,
                'sort_order'      => 12,
                'help_text'       => 'Select a template to pre-fill the agreement text. You can edit after selection.',
                'attributes_json' => wp_json_encode( array( 'target_field' => 'agreement_template' ) ),
                'options_json'    => wp_json_encode( array(
                    'standard' => array(
                        'label'   => 'Standard Binding Agreement',
                        'content' => '<h3>OWBN Binding Agreement</h3>'
                            . '<p>This binding agreement is entered into on <strong>{date}</strong>.</p>'
                            . '<p><strong>Player:</strong> {player_user}</p>'
                            . '<p><strong>Submitted by:</strong> {submitter_name}</p>'
                            . '<hr />'
                            . '<h4>Agreement Terms</h4>'
                            . '<p>[Enter specific terms and conditions here]</p>'
                            . '<hr />'
                            . '<h4>Acknowledgment</h4>'
                            . '<p>By signing below, each party acknowledges they have read, understand, and agree to the terms of this binding agreement as set forth by One World by Night.</p>',
                    ),
                ) ),
            ),
            array(
                'context'    => 'submit',
                'field_key'  => 'agreement_template',
                'field_type' => 'htmlarea',
                'label'      => 'Agreement Text',
                'required'   => 1,
                'sort_order' => 15,
                'help_text'  => 'Pre-filled from template above. Edit as needed.',
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
                'help_text'  => 'Sign your role below. Other signatures are collected during review.',
            ),
            // BA-001: signed_by_role controls which sig is active for the submitter's role.
            array(
                'context'         => 'submit',
                'field_key'       => 'sig_player',
                'field_type'      => 'signature',
                'label'           => 'Player Signature',
                'required'        => 1,
                'sort_order'      => 40,
                'attributes_json' => wp_json_encode( array( 'signed_by_role' => 'player' ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'sig_chronicle',
                'field_type'      => 'signature',
                'label'           => 'Chronicle Representative Signature',
                'required'        => 1,
                'sort_order'      => 50,
                'attributes_json' => wp_json_encode( array( 'signed_by_role' => 'staff' ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'sig_coordinator',
                'field_type'      => 'signature',
                'label'           => 'Coordinator Signature',
                'required'        => 0,
                'sort_order'      => 60,
                'attributes_json' => wp_json_encode( array( 'signed_by_role' => 'coordinator' ) ),
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
            // Auto-capture submitter's WP user ID (used by JS to auto-set player_user_id for player submitters).
            array(
                'context'         => 'submit',
                'field_key'       => 'submitter_user_id',
                'field_type'      => 'auto_prop',
                'label'           => 'Submitter User ID',
                'required'        => 1,
                'sort_order'      => 91,
                'attributes_json' => wp_json_encode( array( 'source' => 'user_id' ) ),
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
            // BA-001: Step-aware signature fields for review panel.
            // These use the same meta keys as submit-context sigs.
            array(
                'context'         => 'review',
                'field_key'       => 'sig_player',
                'field_type'      => 'signature',
                'label'           => 'Player Signature',
                'required'        => 0,
                'sort_order'      => 30,
                'attributes_json' => wp_json_encode( array(
                    'signed_by_role' => 'player',
                    'for_steps'      => array( 'player_comment' ),
                ) ),
            ),
            array(
                'context'         => 'review',
                'field_key'       => 'sig_chronicle',
                'field_type'      => 'signature',
                'label'           => 'Chronicle Representative Signature',
                'required'        => 0,
                'sort_order'      => 40,
                'attributes_json' => wp_json_encode( array(
                    'signed_by_role' => 'staff',
                    'for_steps'      => array( 'staff_comment', 'staff_review' ),
                ) ),
            ),
            array(
                'context'         => 'review',
                'field_key'       => 'sig_coordinator',
                'field_type'      => 'signature',
                'label'           => 'Coordinator Signature',
                'required'        => 0,
                'sort_order'      => 50,
                'attributes_json' => wp_json_encode( array(
                    'signed_by_role' => 'coordinator',
                    'for_steps'      => array( 'coord_review' ),
                ) ),
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
