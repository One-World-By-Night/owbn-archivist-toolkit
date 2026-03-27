<?php

defined( 'ABSPATH' ) || exit;

class OAT_Domain_Character_Lifecycle implements OAT_Domain_Interface {

	public function get_slug() {
		return 'character_lifecycle';
	}

	public function get_label() {
		return 'Character Lifecycle';
	}

	public function get_forms() {
		return array(
			array( 'slug' => 'cl_transfer',             'label' => 'Transfer' ),
			array( 'slug' => 'cl_death',                'label' => 'Death' ),
			array( 'slug' => 'cl_registration',         'label' => 'Registration' ),
			array( 'slug' => 'cl_ru_request',           'label' => 'R&U Request' ),
			array( 'slug' => 'cl_learn_custom_content',  'label' => 'Learn Custom Content' ),
		);
	}

	// Workflow: Submit → Staff → Gaining Staff (transfer only) → Coord (conditional) → Archivist.
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
				'on_approve'         => 'gaining_staff_review',
				'on_deny'            => null,
				'on_request_changes' => 'submit',
				'timer'              => null,
				'condition'          => null,
				'multi_approve'      => false,
			),
			array(
				'id'                 => 'gaining_staff_review',
				'label'              => 'Gaining Chronicle Review',
				'assignee_role'      => '{transfer_to_chronicle}/HST',
				'visibility_tier'    => OAT_Constants::TIER_STAFF,
				'on_approve'         => 'coord_review',
				'on_deny'            => null,
				'on_request_changes' => 'submit',
				'timer'              => null,
				'condition'          => array(
					'meta_key' => 'transfer_to_chronicle',
					'operator' => 'starts_with',
					'value'    => 'chronicle/',
				),
				'multi_approve'      => false,
			),
			array(
				'id'                 => 'gaining_coord_review',
				'label'              => 'Gaining Coordinator Review',
				'assignee_role'      => '{transfer_to_chronicle}/Coordinator',
				'visibility_tier'    => OAT_Constants::TIER_COORDINATOR,
				'on_approve'         => 'coord_review',
				'on_deny'            => null,
				'on_request_changes' => 'submit',
				'timer'              => null,
				'condition'          => array(
					'meta_key' => 'transfer_to_chronicle',
					'operator' => 'starts_with',
					'value'    => 'coordinator/',
				),
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
					'duration'      => 1209600,
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

	public function get_meta_keys() {
		return array(
			'character_name' => array(
				'label'    => 'Character Name',
				'type'     => 'character_picker',
				'required' => true,
			),
			'character_id' => array(
				'label'    => 'Character ID',
				'type'     => 'hidden',
				'required' => false,
			),
			'pc_npc' => array(
				'label'    => 'PC / NPC',
				'type'     => 'hidden',
				'required' => false,
			),
			'creature_type' => array(
				'label'    => 'Creature Type',
				'type'     => 'hidden',
				'required' => false,
			),
			'creature_sub_type' => array(
				'label'    => 'Creature Sub-Type',
				'type'     => 'hidden',
				'required' => false,
			),
			'ru_sub_status' => array(
				'label'    => 'Character Status',
				'type'     => 'select',
				'required' => false,
				'options'  => array(
					'active'      => 'Active',
					'dead'        => 'Dead',
					'inactive'    => 'Inactive',
					'removed_ic'  => 'Removed IC',
					'removed_ooc' => 'Removed OOC',
				),
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
			'transfer_to_chronicle' => array(
				'label'       => 'Transfer To',
				'type'        => 'entity_picker',
				'required'    => false,
				'description' => 'Select the chronicle or coordinator office receiving this character.',
				'attributes'  => array(
					'chronicle_roles'   => array( '*' ),
					'coordinator_roles' => array( '*' ),
				),
				'condition'   => array(
					'field' => 'action_type',
					'value' => 'transfer',
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

	public function seed_form_fields() {
		if ( ! class_exists( 'OAT_Form_Field' ) ) {
			return 0;
		}

		$shared_submit = self::shared_submit_fields();
		$review        = self::review_fields();
		$escalate      = self::escalate_fields();
		$resolve       = self::resolve_fields();
		$count         = 0;

		// Transfer: chronicle + character + gaining chronicle.
		$count += OAT_Form_Field::seed( 'cl_transfer', array_merge(
			$shared_submit,
			array(
				array(
					'context'         => 'submit',
					'field_key'       => 'action_type',
					'field_type'      => 'hidden',
					'label'           => 'Action Type',
					'sort_order'      => 50,
					'attributes_json' => wp_json_encode( array( 'default' => 'transfer' ) ),
				),
				array(
					'context'         => 'submit',
					'field_key'       => 'transfer_to_chronicle',
					'field_type'      => 'entity_picker',
					'label'           => 'Transfer To',
					'required'        => 1,
					'sort_order'      => 55,
					'help_text'       => 'Select the chronicle or coordinator office receiving this character.',
					'attributes_json' => wp_json_encode( array(
						'chronicle_roles'   => array( '*' ),
						'coordinator_roles' => array( '*' ),
					) ),
				),
			),
			self::system_fields(),
			$review,
			$escalate,
			$resolve
		) );

		// Death: chronicle + character + death description.
		$count += OAT_Form_Field::seed( 'cl_death', array_merge(
			$shared_submit,
			array(
				array(
					'context'         => 'submit',
					'field_key'       => 'action_type',
					'field_type'      => 'hidden',
					'label'           => 'Action Type',
					'sort_order'      => 50,
					'attributes_json' => wp_json_encode( array( 'default' => 'death' ) ),
				),
				array(
					'context'         => 'submit',
					'field_key'       => 'death_description',
					'field_type'      => 'textarea',
					'label'           => 'Description of Death',
					'required'        => 1,
					'sort_order'      => 55,
					'help_text'       => 'Describe the circumstances of the character death.',
					'attributes_json' => wp_json_encode( array( 'rows' => 4 ) ),
				),
			),
			self::system_fields(),
			$review,
			$escalate,
			$resolve
		) );

		// Registration: chronicle + character + regulation rules + coordinator display.
		$count += OAT_Form_Field::seed( 'cl_registration', array_merge(
			$shared_submit,
			array(
				array(
					'context'         => 'submit',
					'field_key'       => 'action_type',
					'field_type'      => 'hidden',
					'label'           => 'Action Type',
					'sort_order'      => 50,
					'attributes_json' => wp_json_encode( array( 'default' => 'registration' ) ),
				),
			),
			self::regulation_fields(),
			self::system_fields(),
			$review,
			$escalate,
			$resolve
		) );

		// R&U Request: chronicle + character + regulation rules + coordinator display.
		$count += OAT_Form_Field::seed( 'cl_ru_request', array_merge(
			$shared_submit,
			array(
				array(
					'context'         => 'submit',
					'field_key'       => 'action_type',
					'field_type'      => 'hidden',
					'label'           => 'Action Type',
					'sort_order'      => 50,
					'attributes_json' => wp_json_encode( array( 'default' => 'ru_request' ) ),
				),
			),
			self::regulation_fields(),
			self::system_fields(),
			$review,
			$escalate,
			$resolve
		) );

		// Learn Custom Content: regulation fields + teacher info.
		$count += OAT_Form_Field::seed( 'cl_learn_custom_content', array_merge(
			$shared_submit,
			array(
				array(
					'context'         => 'submit',
					'field_key'       => 'action_type',
					'field_type'      => 'hidden',
					'label'           => 'Action Type',
					'sort_order'      => 50,
					'attributes_json' => wp_json_encode( array( 'default' => 'learn_custom_content' ) ),
				),
			),
			self::regulation_fields(),
			array(
				array(
					'context'         => 'submit',
					'field_key'       => 'teacher_name',
					'field_type'      => 'character_picker',
					'label'           => 'Teacher Name',
					'sort_order'      => 70,
					'required'        => 1,
					'attributes_json' => wp_json_encode( array( 'help_text' => 'Select the character who taught this content.' ) ),
				),
				array(
					'context'         => 'submit',
					'field_key'       => 'teaching_lineage',
					'field_type'      => 'textarea',
					'label'           => 'Teaching Lineage',
					'sort_order'      => 80,
					'attributes_json' => wp_json_encode( array( 'rows' => 4 ) ),
				),
			),
			self::system_fields(),
			$review,
			$escalate,
			$resolve
		) );

		return $count;
	}

	public function validate( $entry, $meta ) {
		if ( empty( $meta['character_name'] ) ) {
			return new WP_Error( 'missing_character_name', 'Character name is required.' );
		}

		$valid_types = array( 'transfer', 'death', 'registration', 'ru_request', 'learn_custom_content' );
		if ( empty( $meta['action_type'] ) || ! in_array( $meta['action_type'], $valid_types, true ) ) {
			return new WP_Error( 'invalid_action_type', 'Invalid action type.' );
		}

		if ( ! empty( $meta['ru_sub_status'] ) ) {
			$valid_statuses = array( 'active', 'dead', 'inactive', 'removed_ic', 'removed_ooc' );
			if ( ! in_array( $meta['ru_sub_status'], $valid_statuses, true ) ) {
				return new WP_Error( 'invalid_ru_sub_status', 'Invalid character status.' );
			}
		}

		return true;
	}

	public function get_archivist_mode() {
		return 'manual';
	}

	// Fields shared by all 5 forms: chronicle, submitter info, character, status.
	private static function shared_submit_fields() {
		return array(
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
				'context'      => 'submit',
				'field_key'    => 'character_name',
				'field_type'   => 'character_picker',
				'label'        => 'Character Name',
				'required'     => 1,
				'sort_order'   => 40,
				'help_text'    => 'Search for an existing character or create a new one.',
				'options_json' => '{}',
			),
			array(
				'context'      => 'submit',
				'field_key'    => 'ru_sub_status',
				'field_type'   => 'select',
				'label'        => 'Character Status',
				'sort_order'   => 45,
				'options_json' => wp_json_encode( array(
					'active'      => 'Active',
					'dead'        => 'Dead',
					'inactive'    => 'Inactive',
					'removed_ic'  => 'Removed IC',
					'removed_ooc' => 'Removed OOC',
				) ),
			),
		);
	}

	// Regulation rules + coordinator display — used by registration, R&U, learn custom content.
	private static function regulation_fields() {
		return array(
			array(
				'context'    => 'submit',
				'field_key'  => 'regulation_rules',
				'field_type' => 'rule_picker',
				'label'      => 'Regulation Rules',
				'sort_order' => 60,
				'help_text'  => 'Select applicable regulation rules.',
			),
			array(
				'context'    => 'submit',
				'field_key'  => 'coordinator_display',
				'field_type' => 'coordinator_display',
				'label'      => 'Controlling Coordinator(s)',
				'sort_order' => 65,
				'help_text'  => 'Automatically populated from selected regulation rules.',
			),
		);
	}

	// Hidden system fields + notes — shared by all forms.
	private static function system_fields() {
		return array(
			array(
				'context'    => 'submit',
				'field_key'  => 'requires_coord',
				'field_type' => 'hidden',
				'label'      => 'Requires Coordinator',
				'sort_order' => 90,
			),
			array(
				'context'    => 'submit',
				'field_key'  => 'regulation_level',
				'field_type' => 'hidden',
				'label'      => 'Regulation Level',
				'sort_order' => 100,
			),
		);
	}

	private static function review_fields() {
		return array(
			array(
				'context'    => 'review',
				'field_key'  => 'section_review',
				'field_type' => 'heading',
				'label'      => 'Staff Review',
				'sort_order' => 10,
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
		);
	}

	private static function escalate_fields() {
		return array(
			array(
				'context'    => 'escalate',
				'field_key'  => 'section_coord_review',
				'field_type' => 'heading',
				'label'      => 'Coordinator Review',
				'sort_order' => 10,
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
				'sort_order'      => 20,
				'placeholder'     => 'Resolution notes...',
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
		);
	}
}
