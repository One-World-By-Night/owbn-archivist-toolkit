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
     * @return array
     */
    public function get_meta_keys() {
        return array(
            'content_name' => array(
                'label'    => 'Content Name',
                'type'     => 'text',
                'required' => true,
            ),
            'content_type' => array(
                'label'    => 'Content Type',
                'type'     => 'select',
                'required' => true,
                'options'  => array(
                    'discipline' => 'Discipline',
                    'combo'      => 'Combo Discipline',
                    'ritual'     => 'Ritual',
                    'merit'      => 'Merit',
                    'flaw'       => 'Flaw',
                    'thaumaturgy_path' => 'Thaumaturgy Path',
                    'necromancy_path'  => 'Necromancy Path',
                    'other'      => 'Other',
                ),
            ),
            'mechanics' => array(
                'label'    => 'Mechanics',
                'type'     => 'textarea',
                'required' => true,
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
        );
    }

    /**
     * @param object $entry
     * @param array  $meta
     * @return true|WP_Error
     */
    public function validate( $entry, $meta ) {
        if ( empty( $meta['content_name'] ) ) {
            return new WP_Error( 'missing_content_name', 'Content name is required.' );
        }

        $valid_types = array( 'discipline', 'combo', 'ritual', 'merit', 'flaw', 'thaumaturgy_path', 'necromancy_path', 'other' );
        if ( empty( $meta['content_type'] ) || ! in_array( $meta['content_type'], $valid_types, true ) ) {
            return new WP_Error( 'invalid_content_type', 'Invalid content type.' );
        }

        if ( empty( $meta['mechanics'] ) ) {
            return new WP_Error( 'missing_mechanics', 'Mechanics description is required.' );
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
