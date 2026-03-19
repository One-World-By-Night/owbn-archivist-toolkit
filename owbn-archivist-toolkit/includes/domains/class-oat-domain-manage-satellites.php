<?php

defined( 'ABSPATH' ) || exit;

class OAT_Domain_Manage_Satellites implements OAT_Domain_Interface {

    public function get_slug() {
        return 'manage_satellites';
    }

    public function get_label() {
        return 'Manage Satellites';
    }

    // Workflow: Staff → Membership Coordinator. No timer.
    public function get_workflow_template() {
        return array(
            array(
                'id'                 => 'submit',
                'label'              => 'Submission',
                'assignee_role'      => '',
                'visibility_tier'    => OAT_Constants::TIER_STAFF,
                'on_approve'         => 'membership',
                'on_deny'            => null,
                'on_request_changes' => null,
                'timer'              => null,
                'condition'          => null,
                'multi_approve'      => false,
            ),
            array(
                'id'                 => 'membership',
                'label'              => 'Membership Review',
                'assignee_role'      => 'Exec/Membership/Coordinator',
                'visibility_tier'    => OAT_Constants::TIER_COORDINATOR,
                'on_approve'         => null,
                'on_deny'            => null,
                'on_request_changes' => 'submit',
                'timer'              => null,
                'condition'          => null,
                'multi_approve'      => false,
            ),
        );
    }

    public function get_meta_keys() {
        return array(
            'action_type' => array(
                'label'    => 'Action',
                'type'     => 'select',
                'required' => true,
            ),
            'parent_chronicle' => array(
                'label'    => 'Parent Chronicle',
                'type'     => 'text',
                'required' => true,
            ),
            'satellite_chronicle' => array(
                'label'    => 'Satellite Chronicle',
                'type'     => 'text',
                'required' => false,
            ),
            'satellite_name' => array(
                'label'    => 'Satellite Chronicle Name',
                'type'     => 'text',
                'required' => false,
            ),
            'satellite_genre' => array(
                'label'    => 'Satellite Chronicle Genre',
                'type'     => 'text',
                'required' => false,
            ),
            'satellite_st_name' => array(
                'label'    => 'ST of Satellite Chronicle',
                'type'     => 'text',
                'required' => false,
            ),
            'satellite_st_email' => array(
                'label'    => 'ST Email',
                'type'     => 'email',
                'required' => false,
            ),
            'submitter_name' => array(
                'label'    => 'Contact Name',
                'type'     => 'text',
                'required' => true,
            ),
            'submitter_email' => array(
                'label'    => 'Contact Email',
                'type'     => 'email',
                'required' => true,
            ),
            'reason' => array(
                'label'    => 'Reason / Notes',
                'type'     => 'textarea',
                'required' => false,
            ),
        );
    }

    public function seed_form_fields() {
        if ( ! class_exists( 'OAT_Form_Field' ) ) {
            return 0;
        }

        return OAT_Form_Field::seed( 'manage_satellites', array(
            // ── Submit context ───────────────────────────────────────────────
            array(
                'context'         => 'submit',
                'field_key'       => 'parent_chronicle',
                'field_type'      => 'chronicle_picker',
                'label'           => 'Your Chronicle',
                'required'        => 1,
                'sort_order'      => 10,
                'help_text'       => 'Select your chronicle. Only non-probationary, non-satellite chronicles with satellites or the ability to create them are shown.',
                'attributes_json' => wp_json_encode( array(
                    'roles'  => array( 'HST', 'CM' ),
                    'filter' => array(
                        'probationary' => false,
                        'satellite'    => false,
                    ),
                    'auto_props' => array(
                        'submitter_name'  => 'user_name',
                        'submitter_email' => 'user_email',
                    ),
                ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'action_type',
                'field_type'      => 'select',
                'label'           => 'What would you like to do?',
                'required'        => 1,
                'sort_order'      => 20,
                'options_json'    => wp_json_encode( array(
                    'create_new'    => 'Create a New Satellite Chronicle',
                    'decommission'  => 'Decommission an Existing Satellite',
                    'transition'    => 'Propose Transition to Full Chronicle',
                ) ),
            ),

            // ── Existing satellite picker (for decommission/transition) ─────
            array(
                'context'         => 'submit',
                'field_key'       => 'satellite_chronicle',
                'field_type'      => 'satellite_picker',
                'label'           => 'Select Satellite',
                'sort_order'      => 30,
                'help_text'       => 'Choose the satellite chronicle. Only shown for decommission or transition.',
                'attributes_json' => wp_json_encode( array(
                    'depends_on' => 'parent_chronicle',
                    'visible_when' => array( 'action_type' => array( 'decommission', 'transition' ) ),
                ) ),
            ),

            // ── New satellite fields (for create) ───────────────────────────
            array(
                'context'         => 'submit',
                'field_key'       => 'section_new_satellite',
                'field_type'      => 'heading',
                'label'           => 'New Satellite Details',
                'sort_order'      => 40,
                'attributes_json' => wp_json_encode( array(
                    'visible_when' => array( 'action_type' => array( 'create_new' ) ),
                ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'satellite_name',
                'field_type'      => 'text',
                'label'           => 'Satellite Chronicle Name',
                'sort_order'      => 50,
                'help_text'       => 'Use the same format as the parent game name.',
                'attributes_json' => wp_json_encode( array(
                    'visible_when' => array( 'action_type' => array( 'create_new' ) ),
                ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'satellite_genre',
                'field_type'      => 'multi_select',
                'label'           => 'Satellite Chronicle Genre',
                'sort_order'      => 60,
                'attributes_json' => wp_json_encode( array(
                    'source'       => 'owbn_genre_list',
                    'visible_when' => array( 'action_type' => array( 'create_new' ) ),
                ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'satellite_st_name',
                'field_type'      => 'text',
                'label'           => 'ST of Satellite Chronicle',
                'sort_order'      => 70,
                'help_text'       => 'Full name required for website setup. The listed name may be a nickname or abbreviation.',
                'attributes_json' => wp_json_encode( array(
                    'visible_when' => array( 'action_type' => array( 'create_new' ) ),
                ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'satellite_st_email',
                'field_type'      => 'email',
                'label'           => 'ST Email Address',
                'sort_order'      => 80,
                'help_text'       => 'Individual email required for website setup. A group email can be set later via the Chronicle Information Form.',
                'attributes_json' => wp_json_encode( array(
                    'visible_when' => array( 'action_type' => array( 'create_new' ) ),
                ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'satellite_territory_note',
                'field_type'      => 'notice',
                'label'           => 'Satellite games may exist only within the territory the home chronicle owns.',
                'sort_order'      => 85,
                'attributes_json' => wp_json_encode( array(
                    'visible_when' => array( 'action_type' => array( 'create_new' ) ),
                    'style'        => 'warning',
                ) ),
            ),

            // ── Reason (for decommission/transition) ────────────────────────
            array(
                'context'         => 'submit',
                'field_key'       => 'reason',
                'field_type'      => 'textarea',
                'label'           => 'Reason / Notes',
                'sort_order'      => 90,
                'attributes_json' => wp_json_encode( array(
                    'rows' => 6,
                    'visible_when' => array( 'action_type' => array( 'decommission', 'transition' ) ),
                ) ),
            ),

            // ── Contact info ────────────────────────────────────────────────
            array(
                'context'         => 'submit',
                'field_key'       => 'submitter_name',
                'field_type'      => 'auto_prop',
                'label'           => 'Your Name',
                'required'        => 1,
                'sort_order'      => 100,
                'attributes_json' => wp_json_encode( array( 'source' => 'user_name' ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'submitter_email',
                'field_type'      => 'auto_prop',
                'label'           => 'Your Email',
                'required'        => 1,
                'sort_order'      => 110,
                'attributes_json' => wp_json_encode( array( 'source' => 'user_email' ) ),
            ),

            // ── Review context ──────────────────────────────────────────────
            array(
                'context'         => 'review',
                'field_key'       => 'section_review',
                'field_type'      => 'heading',
                'label'           => 'Membership Review',
                'sort_order'      => 10,
            ),
            array(
                'context'         => 'review',
                'field_key'       => 'review_note',
                'field_type'      => 'textarea',
                'label'           => 'Review Comments',
                'sort_order'      => 20,
                'placeholder'     => 'Review comments...',
                'attributes_json' => wp_json_encode( array( 'rows' => 4 ) ),
            ),

            // ── Resolve context ─────────────────────────────────────────────
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
                    'returned' => 'Returned',
                ) ),
            ),
        ) );
    }

    public function validate( $entry, $meta ) {
        if ( empty( $meta['parent_chronicle'] ) ) {
            return new WP_Error( 'missing_parent', 'Parent chronicle selection is required.' );
        }
        if ( empty( $meta['action_type'] ) ) {
            return new WP_Error( 'missing_action', 'Please select an action.' );
        }

        $action = $meta['action_type'];

        if ( $action === 'create_new' ) {
            if ( empty( $meta['satellite_name'] ) ) {
                return new WP_Error( 'missing_name', 'Satellite chronicle name is required.' );
            }
            if ( empty( $meta['satellite_st_name'] ) ) {
                return new WP_Error( 'missing_st', 'ST name is required for new satellites.' );
            }
            if ( empty( $meta['satellite_st_email'] ) ) {
                return new WP_Error( 'missing_st_email', 'ST email is required for new satellites.' );
            }
        }

        if ( in_array( $action, array( 'decommission', 'transition' ), true ) ) {
            if ( empty( $meta['satellite_chronicle'] ) ) {
                return new WP_Error( 'missing_satellite', 'Please select the satellite chronicle.' );
            }
        }

        return true;
    }

    // Auto-generate entry title based on action type
    public function get_entry_title( $meta ) {
        $parent = $meta['parent_chronicle'] ?? '';
        $action = $meta['action_type'] ?? '';

        switch ( $action ) {
            case 'create_new':
                $sat_name = $meta['satellite_name'] ?? 'New Satellite';
                return "New Satellite Chronicle: {$sat_name} (for {$parent})";
            case 'decommission':
                $sat = $meta['satellite_chronicle'] ?? '';
                return "Decommission Satellite: {$sat} (from {$parent})";
            case 'transition':
                $sat = $meta['satellite_chronicle'] ?? '';
                return "Transition to Full Chronicle: {$sat} (from {$parent})";
            default:
                return "Satellite Management: {$parent}";
        }
    }

    public function get_archivist_mode() {
        return 'none';
    }
}
