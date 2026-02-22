<?php

defined( 'ABSPATH' ) || exit;

class OAT_Domain_Chronicle_Reporting implements OAT_Domain_Interface {

    /**
     * @return string
     */
    public function get_slug() {
        return 'chronicle_reporting';
    }

    /**
     * @return string
     */
    public function get_label() {
        return 'Chronicle Reporting';
    }

    /**
     * Workflow: Staff → Archivist (auto). No coordinator step, no timer.
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
                'on_request_changes' => 'submit',
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
            'report_period' => array(
                'label'    => 'Reporting Period',
                'type'     => 'text',
                'required' => true,
            ),
            'active_players' => array(
                'label'    => 'Active Players',
                'type'     => 'number',
                'required' => false,
            ),
            'active_characters' => array(
                'label'    => 'Active Characters',
                'type'     => 'number',
                'required' => false,
            ),
            'game_sessions' => array(
                'label'    => 'Game Sessions Held',
                'type'     => 'number',
                'required' => false,
            ),
            'report_notes' => array(
                'label'    => 'Additional Notes',
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
                array( 'key' => 'report_period', 'type' => 'text', 'label' => 'Reporting Period', 'required' => true ),
                array( 'key' => 'active_players', 'type' => 'number', 'label' => 'Active Players', 'required' => false ),
                array( 'key' => 'active_characters', 'type' => 'number', 'label' => 'Active Characters', 'required' => false ),
                array( 'key' => 'game_sessions', 'type' => 'number', 'label' => 'Game Sessions Held', 'required' => false ),
                array( 'key' => 'report_notes', 'type' => 'textarea', 'label' => 'Additional Notes', 'required' => false ),
            ),
            'review' => array(
                array( 'key' => 'summary', 'type' => 'readonly', 'label' => 'Report Summary' ),
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
        if ( empty( $meta['report_period'] ) ) {
            return new WP_Error( 'missing_report_period', 'Reporting period is required.' );
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
