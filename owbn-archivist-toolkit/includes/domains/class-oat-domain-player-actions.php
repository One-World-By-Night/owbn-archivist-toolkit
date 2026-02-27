<?php

defined( 'ABSPATH' ) || exit;

class OAT_Domain_Player_Actions implements OAT_Domain_Interface {

    /**
     * Constructor — register on-approve listener.
     */
    public function __construct() {
        add_action( 'oat_entry_approved', array( $this, 'on_approved' ), 20, 3 );
    }

    /**
     * @return string
     */
    public function get_slug() {
        return 'player_actions';
    }

    /**
     * @return string
     */
    public function get_label() {
        return 'Player Actions';
    }

    /**
     * Workflow: Submit → Chronicle Review (conditional) OR Coordinator Review (conditional).
     *
     * Only one review step activates per entry based on action_type meta.
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
                'on_approve'         => 'chron_review',
                'on_deny'            => null,
                'on_request_changes' => null,
                'timer'              => null,
                'condition'          => null,
                'multi_approve'      => false,
            ),
            array(
                'id'                 => 'chron_review',
                'label'              => 'Chronicle Review',
                'assignee_role'      => 'chronicle/{chronicle_slug}/cm',
                'visibility_tier'    => OAT_Constants::TIER_STAFF,
                'on_approve'         => null,
                'on_deny'            => null,
                'on_request_changes' => 'submit',
                'timer'              => null,
                'condition'          => array(
                    'meta_key' => 'action_type',
                    'operator' => '=',
                    'value'    => 'join_chronicle',
                ),
                'multi_approve'      => false,
            ),
            array(
                'id'                 => 'coord_review',
                'label'              => 'Coordinator Review',
                'assignee_role'      => 'coordinator/{coordinator_genre}/coordinator',
                'visibility_tier'    => OAT_Constants::TIER_COORDINATOR,
                'on_approve'         => null,
                'on_deny'            => null,
                'on_request_changes' => 'submit',
                'timer'              => null,
                'condition'          => array(
                    'meta_key' => 'action_type',
                    'operator' => '=',
                    'value'    => 'genre_register',
                ),
                'multi_approve'      => false,
            ),
        );
    }

    /**
     * @return array
     */
    public function get_meta_keys() {
        return array(
            'action_type',
            'chronicle_slug',
            'coordinator_genre',
            'notes',
        );
    }

    /**
     * @param object $entry
     * @param array  $meta
     * @return true|WP_Error
     */
    public function validate( $entry, $meta ) {
        $valid_types = array( 'join_chronicle', 'genre_register' );
        if ( empty( $meta['action_type'] ) || ! in_array( $meta['action_type'], $valid_types, true ) ) {
            return new WP_Error( 'invalid_action_type', 'Please select an action.' );
        }

        if ( $meta['action_type'] === 'join_chronicle' && empty( $meta['chronicle_slug'] ) ) {
            return new WP_Error( 'missing_chronicle', 'Chronicle is required for Join a Chronicle.' );
        }

        if ( $meta['action_type'] === 'genre_register' && empty( $meta['coordinator_genre'] ) ) {
            return new WP_Error( 'missing_coordinator', 'Coordinator office is required for Genre Register.' );
        }

        return true;
    }

    /**
     * @return string
     */
    public function get_archivist_mode() {
        return 'auto';
    }

    /**
     * Seed form fields for this domain.
     *
     * @return int Number of fields inserted.
     */
    public function seed_form_fields() {
        if ( ! class_exists( 'OAT_Form_Field' ) ) {
            return 0;
        }

        return OAT_Form_Field::seed( 'player_actions', array(
            array(
                'context'      => 'submit',
                'field_key'    => 'action_type',
                'field_type'   => 'select',
                'label'        => 'Action',
                'required'     => 1,
                'sort_order'   => 10,
                'help_text'    => 'What would you like to do?',
                'options_json' => wp_json_encode( array(
                    'join_chronicle' => 'Join a Chronicle',
                    'genre_register' => 'Genre Register',
                ) ),
            ),
            array(
                'context'        => 'submit',
                'field_key'      => 'chronicle_slug',
                'field_type'     => 'chronicle_picker',
                'label'          => 'Chronicle',
                'required'       => 1,
                'sort_order'     => 20,
                'help_text'      => 'Select the chronicle you want to join.',
                'condition_json' => wp_json_encode( array(
                    'field_key' => 'action_type',
                    'value'     => 'join_chronicle',
                ) ),
            ),
            array(
                'context'        => 'submit',
                'field_key'      => 'coordinator_genre',
                'field_type'     => 'coordinator_picker',
                'label'          => 'Coordinator Office',
                'required'       => 1,
                'sort_order'     => 30,
                'help_text'      => 'Select the coordinator office to register with.',
                'condition_json' => wp_json_encode( array(
                    'field_key' => 'action_type',
                    'value'     => 'genre_register',
                ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'notes',
                'field_type'      => 'textarea',
                'label'           => 'Notes',
                'required'        => 0,
                'sort_order'      => 40,
                'placeholder'     => 'Any additional notes...',
                'attributes_json' => wp_json_encode( array( 'rows' => 4 ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'submitter_name',
                'field_type'      => 'auto_prop',
                'label'           => 'Submitter Name',
                'required'        => 1,
                'sort_order'      => 50,
                'attributes_json' => wp_json_encode( array( 'source' => 'user_name' ) ),
            ),
            array(
                'context'         => 'submit',
                'field_key'       => 'submitter_email',
                'field_type'      => 'auto_prop',
                'label'           => 'Submitter Email',
                'required'        => 1,
                'sort_order'      => 60,
                'attributes_json' => wp_json_encode( array( 'source' => 'user_email' ) ),
            ),
        ) );
    }

    /**
     * On-approve: grant ASC role to the submitter.
     *
     * @param int   $entry_id Entry ID.
     * @param int   $user_id  Approving user ID.
     * @param array $data     Action data.
     */
    public function on_approved( $entry_id, $user_id, $data ) {
        if ( ! class_exists( 'OAT_Entry' ) || ! class_exists( 'OAT_Entry_Meta' ) ) {
            return;
        }

        $entry = OAT_Entry::get( (int) $entry_id );
        if ( ! $entry || $entry->domain !== 'player_actions' ) {
            return;
        }

        if ( ! function_exists( 'owc_asc_grant_role' ) ) {
            return;
        }

        $originator = get_userdata( (int) $entry->originator_id );
        if ( ! $originator ) {
            return;
        }

        $email       = $originator->user_email;
        $action_type = OAT_Entry_Meta::get( (int) $entry->id, 'action_type' );

        if ( $action_type === 'join_chronicle' && ! empty( $entry->chronicle_slug ) ) {
            owc_asc_grant_role( 'oat', $email, 'chronicle/' . $entry->chronicle_slug . '/player' );
        } elseif ( $action_type === 'genre_register' && ! empty( $entry->coordinator_genre ) ) {
            owc_asc_grant_role( 'oat', $email, 'coordinator/' . $entry->coordinator_genre . '/player' );
        }
    }
}
