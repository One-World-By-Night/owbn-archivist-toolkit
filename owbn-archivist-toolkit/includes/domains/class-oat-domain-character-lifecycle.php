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
