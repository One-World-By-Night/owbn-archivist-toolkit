<?php

defined( 'ABSPATH' ) || exit;

class OAT_Domain_Custom_Content implements OAT_Domain_Interface {

    /**
     * @return string
     */
    public function get_slug() {
        return 'custom_content';
    }

    /**
     * @return string
     */
    public function get_label() {
        return 'Custom Content';
    }

    /**
     * Workflow: Player → Staff → Coordinator (conditional) → Archivist.
     *
     * Timer and routing driven by regulation_level:
     *   coordinator_notify   → skip coord step (notify only)
     *   coordinator_approval → BBP timer (14 days, 2 bumps)
     *   disallowed           → timer with auto_deny
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
                'timer'              => array(
                    'duration'      => 1209600, // 14 days.
                    'auto_action'   => 'auto_approve',
                    'bump_required' => 2,
                ),
                'condition'          => array(
                    'meta_key' => 'requires_coord',
                    'operator' => '=',
                    'value'    => '1',
                ),
                'multi_approve'      => true,
            ),
            array(
                'id'                 => 'archivist',
                'label'              => 'Archivist Review',
                'assignee_role'      => 'Exec/Archivist/Coordinator',
                'visibility_tier'    => OAT_Constants::TIER_ARCHIVIST,
                'on_approve'         => null,
                'on_deny'            => null,
                'on_request_changes' => 'coord_review',
                'timer'              => null,
                'condition'          => null,
                'multi_approve'      => false,
            ),
        );
    }

    /**
     * Data-carrying submit meta keys (excludes auto_prop, headings).
     *
     * @return array
     */
    public function get_meta_keys() {
        return array(
            'submitter_role',
            'character',
            'content_name',
            'content_type',
            'xp_cost',
            'met_rules',
            'chronicle_slug',
            'hst_approved',
            'creature_type',
            'creature_sub_type',
            'ru_rules',
        );
    }
    public function validate( $entry, $meta ) {
        $valid_roles = array( 'player', 'staff', 'coordinator', 'archivist' );
        if ( empty( $meta['submitter_role'] ) || ! in_array( $meta['submitter_role'], $valid_roles, true ) ) {
            return new WP_Error( 'invalid_submitter_role', 'Submitter role is required.' );
        }

        if ( empty( $meta['character'] ) ) {
            return new WP_Error( 'missing_character', 'Character selection is required.' );
        }

        if ( empty( $meta['content_name'] ) ) {
            return new WP_Error( 'missing_content_name', 'Content name is required.' );
        }

        if ( empty( $meta['content_type'] ) ) {
            return new WP_Error( 'missing_content_type', 'Content type is required.' );
        }

        if ( ! isset( $meta['xp_cost'] ) || $meta['xp_cost'] === '' ) {
            return new WP_Error( 'missing_xp_cost', 'XP cost is required.' );
        }

        if ( empty( $meta['met_rules'] ) ) {
            return new WP_Error( 'missing_met_rules', 'MET Rules / Mechanics is required.' );
        }

        if ( empty( $meta['chronicle_slug'] ) ) {
            return new WP_Error( 'missing_chronicle', 'Chronicle is required.' );
        }

        if ( empty( $meta['hst_approved'] ) ) {
            return new WP_Error( 'missing_hst', 'HST who approved is required.' );
        }

        return true;
    }

    /**
     * @return string
     */
    public function get_archivist_mode() {
        return 'manual';
    }

    /**
     * Seed DB-driven form fields for this domain (D-056, D-057, D-058).
     *
     * @return int Number of fields inserted.
     */
    public function seed_form_fields() {
        if ( ! class_exists( 'OAT_Form_Field' ) ) {
            return 0;
        }

        // Creature taxonomy (D-057) is populated via CSV import (table.csv format:
        // type,affiliation,name). Seeds with empty options_json — admin uploads CSV
        // through Form Fields UI to populate.

        return OAT_Form_Field::seed( 'custom_content', array(
            // ── Submit context (12 fields) ─────────────────────────────────
            array(
                'context'         => 'submit',
                'field_key'       => 'submitter_role',
                'field_type'      => 'select',
                'label'           => 'Submitting As',
                'required'        => 1,
                'sort_order'      => 10,
                'options_json'    => wp_json_encode( array(
                    'player'      => 'As Player',
                    'staff'       => 'As Staff',
                    'coordinator' => 'As Coordinator',
                    'archivist'   => 'As Archivist',
                ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'character',
                'field_type'      => 'character_picker',
                'label'           => 'Character',
                'required'        => 1,
                'sort_order'      => 20,
                'help_text'       => 'Search for an existing character or create a new one.',
                'options_json'    => '{}',
                'attributes_json' => wp_json_encode( array(
                    'filter_by' => 'submitter_role',
                ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'content_name',
                'field_type'      => 'text',
                'label'           => 'Name of Custom Content',
                'required'        => 1,
                'sort_order'      => 30,
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'content_type',
                'field_type'      => 'select',
                'label'           => 'Content Type',
                'required'        => 1,
                'sort_order'      => 40,
                'help_text'       => 'Options depend on creature type selected above.',
                'options_json'    => '{}',
                'attributes_json' => wp_json_encode( array(
                    'cascading_from' => 'creature_type',
                ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'xp_cost',
                'field_type'      => 'number',
                'label'           => 'XP Cost',
                'required'        => 1,
                'sort_order'      => 50,
                'attributes_json' => wp_json_encode( array( 'min' => 0 ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'met_rules',
                'field_type'      => 'htmlarea',
                'label'           => 'MET Rules / Mechanics',
                'required'        => 1,
                'sort_order'      => 60,
                'help_text'       => 'Full mechanical write-up of the custom content.',
                'attributes_json' => wp_json_encode( array( 'rows' => 10 ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'chronicle_slug',
                'field_type'      => 'chronicle_picker',
                'label'           => 'Chronicle Where Content Was Created',
                'required'        => 1,
                'sort_order'      => 70,
                'attributes_json' => wp_json_encode( array(
                    'filter_by'   => 'submitter_role',
                    'role_scopes' => array(
                        'player'      => array( 'Player', 'Staff', 'CM' ),
                        'staff'       => array( 'HST', 'Staff', 'CM' ),
                        'coordinator' => array( '*' ),
                        'archivist'   => array( '*' ),
                    ),
                ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'hst_approved',
                'field_type'      => 'dependent_lookup',
                'label'           => 'HST Who Approved',
                'required'        => 1,
                'sort_order'      => 80,
                'help_text'       => 'Auto-populated from chronicle selection when possible.',
                'attributes_json' => wp_json_encode( array(
                    'depends_on' => 'chronicle_slug',
                    'lookup'     => 'asc_role',
                    'role_path'  => 'chronicle/{value}/hst',
                    'fallback'   => 'editable',
                ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'submitter_name',
                'field_type'      => 'auto_prop',
                'label'           => 'Submitter Name',
                'required'        => 1,
                'sort_order'      => 90,
                'attributes_json' => wp_json_encode( array( 'source' => 'user_name' ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'submitter_email',
                'field_type'      => 'auto_prop',
                'label'           => 'Submitter Email',
                'required'        => 1,
                'sort_order'      => 100,
                'attributes_json' => wp_json_encode( array( 'source' => 'user_email' ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'submitter_pid',
                'field_type'      => 'auto_prop',
                'label'           => 'Player ID',
                'required'        => 1,
                'sort_order'      => 110,
                'attributes_json' => wp_json_encode( array( 'source' => 'player_id' ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'ru_rules',
                'field_type'      => 'rule_picker',
                'label'           => 'Applicable R&U Classifications',
                'required'        => 0,
                'sort_order'      => 120,
                'help_text'       => 'Select any applicable Regulation & Usage rules.',
            ),

            // ── Review context (2 fields) ──────────────────────────────────
            array(
                'context'    => 'review',
                'field_key'  => 'section_review',
                'field_type' => 'heading',
                'label'      => 'Review',
                'required'   => 0,
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

            // ── Escalate context (2 fields) ────────────────────────────────
            array(
                'context'    => 'escalate',
                'field_key'  => 'section_escalate',
                'field_type' => 'heading',
                'label'      => 'Escalation',
                'required'   => 0,
                'sort_order' => 10,
            ),
            array(
                'context'         => 'escalate',
                'field_key'       => 'escalation_reason',
                'field_type'      => 'textarea',
                'label'           => 'Reason for Escalation',
                'required'        => 1,
                'sort_order'      => 20,
                'placeholder'     => 'Reason for escalation...',
                'attributes_json' => wp_json_encode( array( 'rows' => 4 ) ),
            ),

            // ── Resolve context (3 fields) ─────────────────────────────────
            array(
                'context'    => 'resolve',
                'field_key'  => 'section_resolve',
                'field_type' => 'heading',
                'label'      => 'Resolution',
                'required'   => 0,
                'sort_order' => 10,
            ),
            array(
                'context'         => 'resolve',
                'field_key'       => 'resolution_note',
                'field_type'      => 'textarea',
                'label'           => 'Resolution Notes',
                'required'        => 0,
                'sort_order'      => 20,
                'placeholder'     => 'Resolution notes...',
                'attributes_json' => wp_json_encode( array( 'rows' => 4 ) ),
            ),
            array(
                'context'      => 'resolve',
                'field_key'    => 'resolution_type',
                'field_type'   => 'select',
                'label'        => 'Resolution',
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
}
