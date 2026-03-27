<?php

defined( 'ABSPATH' ) || exit;

class OAT_Domain_Disciplinary_Actions implements OAT_Domain_Interface {

	public function get_slug() {
		return 'disciplinary_actions';
	}

	public function get_label() {
		return 'Disciplinary Actions';
	}

	public function get_forms() {
		return array(
			array( 'slug' => 'da_chronicle', 'label' => 'Chronicle / Local DA' ),
			array( 'slug' => 'da_global',    'label' => 'Global DA' ),
		);
	}

	// Two conditional sub-flows determined by da_type meta (D-060):
	//   Chronicle: Submit → Staff Review → Coordinator Review → Archivist.
	//   Global:    Submit → Exec Review → Council Vote → Archivist.
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
				'assignee_role'      => 'Staff',
				'visibility_tier'    => OAT_Constants::TIER_STAFF,
				'on_approve'         => 'coord_review',
				'on_deny'            => null,
				'on_request_changes' => 'submit',
				'timer'              => null,
				'condition'          => array(
					'meta_key' => 'da_type',
					'operator' => '=',
					'value'    => 'chronicle',
				),
				'multi_approve'      => false,
			),
			array(
				'id'                 => 'coord_review',
				'label'              => 'Coordinator Review',
				'assignee_role'      => 'Coordinator',
				'visibility_tier'    => OAT_Constants::TIER_COORDINATOR,
				'on_approve'         => 'archivist',
				'on_deny'            => null,
				'on_request_changes' => 'submit',
				'timer'              => null,
				'condition'          => array(
					'meta_key' => 'da_type',
					'operator' => '=',
					'value'    => 'chronicle',
				),
				'multi_approve'      => false,
			),
			array(
				'id'                 => 'exec_review',
				'label'              => 'Executive Review',
				'assignee_role'      => 'Exec/Head-Coordinator/Coordinator',
				'visibility_tier'    => OAT_Constants::TIER_COORDINATOR,
				'on_approve'         => 'council_vote',
				'on_deny'            => null,
				'on_request_changes' => 'submit',
				'timer'              => null,
				'condition'          => array(
					'meta_key' => 'da_type',
					'operator' => '=',
					'value'    => 'global',
				),
				'multi_approve'      => false,
			),
			array(
				'id'                 => 'council_vote',
				'label'              => 'Council Vote',
				'assignee_role'      => 'Exec/Head-Coordinator',
				'visibility_tier'    => OAT_Constants::TIER_COORDINATOR,
				'on_approve'         => 'archivist',
				'on_deny'            => null,
				'on_request_changes' => null,
				'timer'              => null,
				'condition'          => array(
					'meta_key' => 'da_type',
					'operator' => '=',
					'value'    => 'global',
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
				'on_request_changes' => null,
				'timer'              => null,
				'condition'          => null,
				'multi_approve'      => false,
			),
		);
	}

	public function get_meta_keys() {
		return array(
			'record_type' => array(
				'label'    => 'Disciplinary Record Type',
				'type'     => 'select',
				'required' => true,
				'options'  => array(
					'new'       => 'New',
					'reduction' => 'Reduction',
				),
			),
			'da_type' => array(
				'label'    => 'Type of Disciplinary Action',
				'type'     => 'select',
				'required' => true,
				'options'  => array(
					'chronicle' => 'Chronicle / Local',
					'global'    => 'Global',
				),
			),
			'player_name' => array(
				'label'    => 'Player receiving DA',
				'type'     => 'user_picker',
				'required' => true,
			),
			'player_user_id' => array(
				'label'    => 'Player User ID',
				'type'     => 'hidden',
				'required' => false,
			),
			'chronicle_slug' => array(
				'label'    => 'Chronicle issuing DA',
				'type'     => 'chronicle_picker',
				'required' => true,
			),
			'reporter_name' => array(
				'label'    => 'Person reporting / filing DA',
				'type'     => 'auto_prop',
				'required' => true,
			),
			'da_details' => array(
				'label'    => 'Details of the disciplinary action',
				'type'     => 'htmlarea',
				'required' => true,
			),
			'da_action' => array(
				'label'    => 'Action taken',
				'type'     => 'textarea',
				'required' => true,
			),
			'vote_link' => array(
				'label'    => 'Link to Council vote',
				'type'     => 'url',
				'required' => false,
			),
			'vote_link_punishment' => array(
				'label'    => 'Link to Council vote (punishment)',
				'type'     => 'url',
				'required' => false,
			),
			'notification_type' => array(
				'label'    => 'DA Notification',
				'type'     => 'select',
				'required' => true,
				'options'  => array(
					'system' => 'System',
					'manual' => 'Manual',
				),
			),
			'additional_notes' => array(
				'label'    => 'Additional Notes',
				'type'     => 'textarea',
				'required' => false,
			),
			'location_clarification' => array(
				'label'    => 'Local DA Clarification',
				'type'     => 'text',
				'required' => false,
			),
			'coord_removal' => array(
				'label'    => 'Coordinator Removal?',
				'type'     => 'checkbox',
				'required' => false,
			),
			'st_removal' => array(
				'label'    => 'Storyteller Removal?',
				'type'     => 'checkbox',
				'required' => false,
			),
			'st_removal_chronicle' => array(
				'label'    => 'ST Removal: Chronicle',
				'type'     => 'chronicle_picker',
				'required' => false,
			),
			'st_removal_timeframe' => array(
				'label'    => 'ST Removal: Timeframe',
				'type'     => 'text',
				'required' => false,
			),
			'archived' => array(
				'label'    => 'Archived',
				'type'     => 'checkbox',
				'required' => false,
			),
		);
	}

	public function validate( $entry, $meta ) {
		$valid_record_types = array( 'new', 'reduction' );
		if ( empty( $meta['record_type'] ) || ! in_array( $meta['record_type'], $valid_record_types, true ) ) {
			return new WP_Error( 'invalid_record_type', 'Record type must be new or reduction.' );
		}

		$valid_types = array( 'chronicle', 'global' );
		if ( empty( $meta['da_type'] ) || ! in_array( $meta['da_type'], $valid_types, true ) ) {
			return new WP_Error( 'invalid_da_type', 'DA type must be chronicle or global.' );
		}

		if ( empty( $meta['player_name'] ) ) {
			return new WP_Error( 'missing_player', 'Player name is required.' );
		}

		if ( empty( $meta['reporter_name'] ) ) {
			return new WP_Error( 'missing_reporter', 'Reporter name is required.' );
		}

		if ( empty( $meta['da_details'] ) ) {
			return new WP_Error( 'missing_details', 'DA details are required.' );
		}

		if ( empty( $meta['da_action'] ) ) {
			return new WP_Error( 'missing_action', 'Action taken is required.' );
		}

		if ( ! empty( $meta['notification_type'] ) ) {
			$valid_notif = array( 'system', 'manual' );
			if ( ! in_array( $meta['notification_type'], $valid_notif, true ) ) {
				return new WP_Error( 'invalid_notification_type', 'Notification type must be system or manual.' );
			}
		}

		return true;
	}

	public function seed_form_fields() {
		if ( ! class_exists( 'OAT_Form_Field' ) ) {
			return 0;
		}

		$shared   = self::shared_submit_fields();
		$review   = self::review_fields();
		$escalate = self::escalate_fields();
		$resolve  = self::resolve_fields();
		$count    = 0;

		// Chronicle / Local DA.
		$count += OAT_Form_Field::seed( 'da_chronicle', array_merge(
			array(
				array(
					'context'         => 'submit',
					'field_key'       => 'da_type',
					'field_type'      => 'hidden',
					'label'           => 'DA Type',
					'sort_order'      => 5,
					'attributes_json' => wp_json_encode( array( 'default' => 'chronicle' ) ),
				),
			),
			$shared,
			array(
				array(
					'context'    => 'submit',
					'field_key'  => 'chronicle_slug',
					'field_type' => 'chronicle_picker',
					'label'      => 'Chronicle issuing DA',
					'required'   => 1,
					'sort_order' => 30,
				),
				array(
					'context'    => 'submit',
					'field_key'  => 'location_clarification',
					'field_type' => 'text',
					'label'      => 'Local DA Clarification',
					'sort_order' => 35,
				),
			),
			self::shared_detail_fields(),
			self::submitter_fields(),
			$review,
			$escalate,
			$resolve
		) );

		// Global DA.
		$count += OAT_Form_Field::seed( 'da_global', array_merge(
			array(
				array(
					'context'         => 'submit',
					'field_key'       => 'da_type',
					'field_type'      => 'hidden',
					'label'           => 'DA Type',
					'sort_order'      => 5,
					'attributes_json' => wp_json_encode( array( 'default' => 'global' ) ),
				),
			),
			$shared,
			self::shared_detail_fields(),
			array(
				array(
					'context'    => 'submit',
					'field_key'  => 'vote_link',
					'field_type' => 'url',
					'label'      => 'Link to Council vote (guilt)',
					'sort_order' => 70,
				),
				array(
					'context'    => 'submit',
					'field_key'  => 'vote_link_punishment',
					'field_type' => 'url',
					'label'      => 'Link to Council vote (punishment)',
					'sort_order' => 72,
				),
			),
			self::submitter_fields(),
			$review,
			$escalate,
			$resolve
		) );

		return $count;
	}

	public function get_archivist_mode() {
		return 'auto';
	}

	// Record type + player picker — shared by both forms.
	private static function shared_submit_fields() {
		return array(
			array(
				'context'      => 'submit',
				'field_key'    => 'record_type',
				'field_type'   => 'select',
				'label'        => 'Disciplinary Record Type',
				'required'     => 1,
				'sort_order'   => 10,
				'options_json' => wp_json_encode( array(
					'new'       => 'New',
					'reduction' => 'Reduction',
				) ),
			),
			array(
				'context'         => 'submit',
				'field_key'       => 'player_name',
				'field_type'      => 'user_picker',
				'label'           => 'Player receiving DA',
				'required'        => 1,
				'sort_order'      => 20,
				'help_text'       => 'Search for a player. If not found, type their name.',
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
				'sort_order' => 21,
			),
		);
	}

	// Reporter, details, action, removal fields — shared by both forms.
	private static function shared_detail_fields() {
		return array(
			array(
				'context'         => 'submit',
				'field_key'       => 'reporter_name',
				'field_type'      => 'auto_prop',
				'label'           => 'Person reporting / filing DA',
				'required'        => 1,
				'sort_order'      => 40,
				'attributes_json' => wp_json_encode( array( 'source' => 'user_name' ) ),
			),
			array(
				'context'    => 'submit',
				'field_key'  => 'da_details',
				'field_type' => 'htmlarea',
				'label'      => 'Details of the disciplinary action',
				'required'   => 1,
				'sort_order' => 50,
			),
			array(
				'context'    => 'submit',
				'field_key'  => 'da_action',
				'field_type' => 'textarea',
				'label'      => 'Action taken',
				'required'   => 1,
				'sort_order' => 60,
			),
			array(
				'context'    => 'submit',
				'field_key'  => 'coord_removal',
				'field_type' => 'checkbox',
				'label'      => 'Coordinator Removal?',
				'sort_order' => 65,
			),
			array(
				'context'    => 'submit',
				'field_key'  => 'st_removal',
				'field_type' => 'checkbox',
				'label'      => 'Storyteller Removal?',
				'sort_order' => 67,
			),
			array(
				'context'        => 'submit',
				'field_key'      => 'st_removal_chronicle',
				'field_type'     => 'chronicle_picker',
				'label'          => 'ST Removal: Chronicle',
				'sort_order'     => 68,
				'condition_json' => wp_json_encode( array(
					'field_key' => 'st_removal',
					'value'     => '1',
				) ),
			),
			array(
				'context'        => 'submit',
				'field_key'      => 'st_removal_timeframe',
				'field_type'     => 'text',
				'label'          => 'ST Removal: Timeframe',
				'sort_order'     => 69,
				'condition_json' => wp_json_encode( array(
					'field_key' => 'st_removal',
					'value'     => '1',
				) ),
			),
			array(
				'context'         => 'submit',
				'field_key'       => 'additional_notes',
				'field_type'      => 'textarea',
				'label'           => 'Additional Notes',
				'sort_order'      => 75,
				'attributes_json' => wp_json_encode( array( 'rows' => 6 ) ),
			),
			array(
				'context'      => 'submit',
				'field_key'    => 'notification_type',
				'field_type'   => 'select',
				'label'        => 'DA Notification',
				'required'     => 1,
				'sort_order'   => 78,
				'options_json' => wp_json_encode( array(
					'system' => 'System',
					'manual' => 'Manual',
				) ),
			),
			array(
				'context'    => 'submit',
				'field_key'  => 'archived',
				'field_type' => 'checkbox',
				'label'      => 'Archived',
				'sort_order' => 105,
			),
		);
	}

	private static function submitter_fields() {
		return array(
			array(
				'context'         => 'submit',
				'field_key'       => 'submitter_name',
				'field_type'      => 'auto_prop',
				'label'           => 'Submitter Name',
				'required'        => 1,
				'sort_order'      => 80,
				'attributes_json' => wp_json_encode( array( 'source' => 'user_name' ) ),
			),
			array(
				'context'         => 'submit',
				'field_key'       => 'submitter_email',
				'field_type'      => 'auto_prop',
				'label'           => 'Submitter Email',
				'required'        => 1,
				'sort_order'      => 90,
				'attributes_json' => wp_json_encode( array( 'source' => 'user_email' ) ),
			),
			array(
				'context'         => 'submit',
				'field_key'       => 'submitter_pid',
				'field_type'      => 'auto_prop',
				'label'           => 'Player ID',
				'required'        => 1,
				'sort_order'      => 100,
				'attributes_json' => wp_json_encode( array( 'source' => 'player_id' ) ),
			),
		);
	}

	private static function review_fields() {
		return array(
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
		);
	}

	private static function escalate_fields() {
		return array(
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
		);
	}

	private static function resolve_fields() {
		return array(
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
				'context'         => 'resolve',
				'field_key'       => 'me_too_chronicles',
				'field_type'      => 'chronicle_picker',
				'label'           => 'Additional Chronicles Executing This DA',
				'required'        => 0,
				'sort_order'      => 25,
				'help_text'       => 'Select chronicles that are also executing this disciplinary action.',
				'attributes_json' => wp_json_encode( array( 'roles' => array( '*' ) ) ),
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
		);
	}
}
