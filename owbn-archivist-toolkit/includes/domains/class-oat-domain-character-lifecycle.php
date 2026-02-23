<?php

defined( 'ABSPATH' ) || exit;

class OAT_Domain_Character_Lifecycle implements OAT_Domain_Interface {

    /**
     * @return string
     */
    public function get_slug() {
        return 'character_lifecycle';
    }

    /**
     * @return string
     */
    public function get_label() {
        return 'Character Lifecycle';
    }

    /**
     * Workflow: Player → Staff → Coordinator (conditional) → Archivist.
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
                'multi_approve'      => false,
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
     * @return array
     */
    public function get_meta_keys() {
        return array(
            'character_name' => array(
                'label'    => 'Character Name',
                'type'     => 'text',
                'required' => true,
            ),
            'character_id' => array(
                'label'    => 'Character ID',
                'type'     => 'number',
                'required' => false,
            ),
            'action_type' => array(
                'label'    => 'Action Type',
                'type'     => 'select',
                'required' => true,
                'options'  => array(
                    'transfer'             => 'Transfer',
                    'death'                => 'Death',
                    'registration'         => 'Registration',
                    'ru_request'           => 'R&U Request',
                    'learn_custom_content' => 'Learn Custom Content',
                ),
            ),
            'requires_coord' => array(
                'label'    => 'Requires Coordinator',
                'type'     => 'hidden',
                'required' => false,
            ),
            'regulation_level' => array(
                'label'    => 'Regulation Level',
                'type'     => 'hidden',
                'required' => false,
            ),
            'teacher_name' => array(
                'label'    => 'Teacher Name',
                'type'     => 'text',
                'required' => false,
            ),
            'teaching_lineage' => array(
                'label'    => 'Teaching Lineage',
                'type'     => 'textarea',
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
                array( 'key' => 'character_name', 'type' => 'text', 'label' => 'Character Name', 'required' => true ),
                array( 'key' => 'action_type', 'type' => 'select', 'label' => 'Action Type', 'required' => true, 'options' => array(
                    'transfer'             => 'Transfer',
                    'death'                => 'Death',
                    'registration'         => 'Registration',
                    'ru_request'           => 'R&U Request',
                    'learn_custom_content' => 'Learn Custom Content',
                ) ),
                array( 'key' => 'regulation_rules', 'type' => 'rule_picker', 'label' => 'Regulation Rules', 'required' => false ),
                array( 'key' => 'teacher_name', 'type' => 'text', 'label' => 'Teacher Name', 'required' => false, 'condition' => array( 'action_type' => 'learn_custom_content' ) ),
                array( 'key' => 'teaching_lineage', 'type' => 'textarea', 'label' => 'Teaching Lineage', 'required' => false, 'condition' => array( 'action_type' => 'learn_custom_content' ) ),
                array( 'key' => 'note', 'type' => 'textarea', 'label' => 'Notes', 'required' => false ),
            ),
            'review' => array(
                array( 'key' => 'summary', 'type' => 'readonly', 'label' => 'Entry Summary' ),
                array( 'key' => 'note', 'type' => 'textarea', 'label' => 'Review Note', 'required' => true ),
            ),
        );
    }

    /**
     * Seed form fields into oat_form_fields table.
     *
     * @return int Number of fields inserted.
     */
    public function seed_form_fields() {
        if ( ! class_exists( 'OAT_Form_Field' ) ) {
            return 0;
        }

        return OAT_Form_Field::seed( 'character_lifecycle', array(
            // ── Submit context ───────────────────────────────────────────────
            array(
                'context'         => 'submit',
                'field_key'       => 'chronicle_slug',
                'field_type'      => 'chronicle_picker',
                'label'           => 'Chronicle',
                'required'        => 1,
                'sort_order'      => 10,
                'attributes_json' => wp_json_encode( array(
                    'roles'      => array( 'HST', 'Staff', 'CM', 'Player' ),
                    'auto_props' => array(
                        'submitter_name'  => 'user_name',
                        'submitter_email' => 'user_email',
                    ),
                ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'submitter_name',
                'field_type'      => 'auto_prop',
                'label'           => 'Submitted By',
                'required'        => 1,
                'sort_order'      => 20,
                'attributes_json' => wp_json_encode( array( 'source' => 'user_name' ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'submitter_email',
                'field_type'      => 'auto_prop',
                'label'           => 'Submitted By Email',
                'required'        => 1,
                'sort_order'      => 30,
                'attributes_json' => wp_json_encode( array( 'source' => 'user_email' ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'character_name',
                'field_type'      => 'text',
                'label'           => 'Character Name',
                'required'        => 1,
                'sort_order'      => 40,
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'action_type',
                'field_type'      => 'select',
                'label'           => 'Action Type',
                'required'        => 1,
                'sort_order'      => 50,
                'options_json'    => wp_json_encode( array(
                    'transfer'             => 'Transfer',
                    'death'                => 'Death',
                    'registration'         => 'Registration',
                    'ru_request'           => 'R&U Request',
                    'learn_custom_content' => 'Learn Custom Content',
                ) ),
            ),
            // ── Transfer-specific fields ──────────────────────────────────
            array(
                'context'         => 'submit',
                'field_key'       => 'transfer_to_chronicle',
                'field_type'      => 'chronicle_picker',
                'label'           => 'Transfer To Chronicle',
                'required'        => 1,
                'sort_order'      => 55,
                'help_text'       => 'Select the chronicle this character is transferring to.',
                'attributes_json' => wp_json_encode( array(
                    'roles' => array( 'HST' ),
                ) ),
                'condition_json'  => wp_json_encode( array(
                    'field_key' => 'action_type',
                    'value'     => 'transfer',
                ) ),
            ),

            // ── Death-specific fields ────────────────────────────────────────
            array(
                'context'         => 'submit',
                'field_key'       => 'death_description',
                'field_type'      => 'textarea',
                'label'           => 'Description of Death',
                'sort_order'      => 56,
                'help_text'       => 'Describe the circumstances of the character death.',
                'attributes_json' => wp_json_encode( array( 'rows' => 4 ) ),
                'condition_json'  => wp_json_encode( array(
                    'field_key' => 'action_type',
                    'value'     => 'death',
                ) ),
            ),

            // ── R&U / Custom Content fields ──────────────────────────────────
            array(
                'context'         => 'submit',
                'field_key'       => 'regulation_rules',
                'field_type'      => 'rule_picker',
                'label'           => 'Regulation Rules',
                'sort_order'      => 60,
                'help_text'       => 'Select applicable regulation rules.',
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'teacher_name',
                'field_type'      => 'text',
                'label'           => 'Teacher Name',
                'sort_order'      => 70,
                'condition_json'  => wp_json_encode( array(
                    'field_key' => 'action_type',
                    'value'     => 'learn_custom_content',
                ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'teaching_lineage',
                'field_type'      => 'textarea',
                'label'           => 'Teaching Lineage',
                'sort_order'      => 80,
                'attributes_json' => wp_json_encode( array( 'rows' => 4 ) ),
                'condition_json'  => wp_json_encode( array(
                    'field_key' => 'action_type',
                    'value'     => 'learn_custom_content',
                ) ),
            ),

            // ── Hidden / system fields ───────────────────────────────────────
            array(
                'context'         => 'submit',
                'field_key'       => 'requires_coord',
                'field_type'      => 'hidden',
                'label'           => 'Requires Coordinator',
                'sort_order'      => 90,
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'regulation_level',
                'field_type'      => 'hidden',
                'label'           => 'Regulation Level',
                'sort_order'      => 100,
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'note',
                'field_type'      => 'textarea',
                'label'           => 'Notes',
                'sort_order'      => 110,
                'placeholder'     => 'Additional notes...',
                'attributes_json' => wp_json_encode( array( 'rows' => 4 ) ),
            ),

            // ── Review context (Staff Review) ────────────────────────────────
            array(
                'context'         => 'review',
                'field_key'       => 'section_review',
                'field_type'      => 'heading',
                'label'           => 'Staff Review',
                'sort_order'      => 10,
            ),
            array(
                'context'         => 'review',
                'field_key'       => 'review_note',
                'field_type'      => 'textarea',
                'label'           => 'Review Note',
                'required'        => 1,
                'sort_order'      => 20,
                'placeholder'     => 'Staff review comments...',
                'attributes_json' => wp_json_encode( array( 'rows' => 4 ) ),
            ),

            // ── Escalate context (Coordinator Review) ────────────────────────
            array(
                'context'         => 'escalate',
                'field_key'       => 'section_coord_review',
                'field_type'      => 'heading',
                'label'           => 'Coordinator Review',
                'sort_order'      => 10,
            ),
            array(
                'context'         => 'escalate',
                'field_key'       => 'coord_note',
                'field_type'      => 'textarea',
                'label'           => 'Coordinator Note',
                'sort_order'      => 20,
                'placeholder'     => 'Coordinator review comments...',
                'attributes_json' => wp_json_encode( array( 'rows' => 4 ) ),
            ),

            // ── Resolve context (Archivist/Exec) ────────────────────────────
            array(
                'context'         => 'resolve',
                'field_key'       => 'section_resolve',
                'field_type'      => 'heading',
                'label'           => 'Resolution',
                'sort_order'      => 10,
            ),
            array(
                'context'         => 'resolve',
                'field_key'       => 'resolution_note',
                'field_type'      => 'textarea',
                'label'           => 'Resolution Notes',
                'sort_order'      => 20,
                'placeholder'     => 'Resolution notes...',
                'attributes_json' => wp_json_encode( array( 'rows' => 4 ) ),
            ),
            array(
                'context'         => 'resolve',
                'field_key'       => 'resolution_type',
                'field_type'      => 'select',
                'label'           => 'Resolution Type',
                'required'        => 1,
                'sort_order'      => 30,
                'options_json'    => wp_json_encode( array(
                    'approved' => 'Approved',
                    'denied'   => 'Denied',
                    'deferred' => 'Deferred',
                    'returned' => 'Returned',
                ) ),
            ),
        ) );
    }

    /**
     * @param object $entry
     * @param array  $meta
     * @return true|WP_Error
     */
    public function validate( $entry, $meta ) {
        if ( empty( $meta['character_name'] ) ) {
            return new WP_Error( 'missing_character_name', 'Character name is required.' );
        }

        $valid_types = array( 'transfer', 'death', 'registration', 'ru_request', 'learn_custom_content' );
        if ( empty( $meta['action_type'] ) || ! in_array( $meta['action_type'], $valid_types, true ) ) {
            return new WP_Error( 'invalid_action_type', 'Invalid action type.' );
        }

        return true;
    }

    /**
     * @return string
     */
    public function get_archivist_mode() {
        return 'manual';
    }
}
