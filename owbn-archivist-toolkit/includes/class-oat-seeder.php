<?php

defined( 'ABSPATH' ) || exit;

class OAT_Seeder {

	public static function run() {
		$counts = array(
			'domains' => 0,
			'steps'   => 0,
			'forms'   => 0,
			'fields'  => 0,
		);

		$domains = apply_filters( 'oat_register_domains', array() );

		foreach ( $domains as $domain ) {
			if ( ! ( $domain instanceof OAT_Domain_Interface ) ) {
				continue;
			}

			$counts['domains'] += self::seed_domain( $domain );
			$counts['steps'] += self::seed_workflow_steps( $domain );
			$counts['forms'] += self::seed_forms_for_domain( $domain );

			if ( method_exists( $domain, 'seed_form_fields' ) ) {
				$counts['fields'] += $domain->seed_form_fields();
			}
		}

		self::seed_default_rules();

		return $counts;
	}

	/**
	 * Seed default submission rules if none exist.
	 */
	private static function seed_default_rules() {
		if ( OAT_Rule::count( array( 'rule_type' => 'submission_check' ) ) > 0 ) {
			return;
		}

		$defaults = array(
			array(
				'label'        => 'Block transfers from probationary chronicles',
				'domain'       => 'character_lifecycle',
				'form_slug'    => 'transfer',
				'check_source' => 'chronicle',
				'check_field'  => 'chronicle_probationary',
				'operator'     => 'equals',
				'check_value'  => '1',
				'action'       => 'block',
				'message'      => 'Transfers from probationary chronicles are not allowed. The originating chronicle must be in good standing before characters can transfer out.',
				'priority'     => 10,
			),
			array(
				'label'        => 'Block transfers from decommissioned chronicles',
				'domain'       => 'character_lifecycle',
				'form_slug'    => 'transfer',
				'check_source' => 'chronicle',
				'check_field'  => 'post_status',
				'operator'     => 'in',
				'check_value'  => 'decommissioned',
				'action'       => 'block',
				'message'      => 'This chronicle is no longer active. Transfers from decommissioned or dissolved chronicles are not permitted.',
				'priority'     => 10,
			),
			array(
				'label'        => 'Block new characters in decommissioned chronicles',
				'domain'       => 'character_lifecycle',
				'form_slug'    => 'new_character',
				'check_source' => 'chronicle',
				'check_field'  => 'post_status',
				'operator'     => 'in',
				'check_value'  => 'decommissioned',
				'action'       => 'block',
				'message'      => 'New characters cannot be registered to decommissioned or dissolved chronicles.',
				'priority'     => 10,
			),
		);

		foreach ( $defaults as $rule ) {
			$rule['rule_type'] = 'submission_check';
			OAT_Rule::create( $rule );
		}
	}

	private static function seed_forms_for_domain( $domain ) {
		if ( ! class_exists( 'OAT_Form' ) || ! class_exists( 'OAT_Domain_Form' ) || ! class_exists( 'OAT_Domain' ) ) {
			return 0;
		}

		$domain_row = OAT_Domain::find_by_slug( $domain->get_slug() );
		if ( ! $domain_row ) {
			return 0;
		}

		$domain_id = (int) $domain_row->id;
		$created   = 0;

		// Multi-form domains define get_forms(); single-form domains fall back to one form matching the domain slug.
		if ( method_exists( $domain, 'get_forms' ) ) {
			$forms = $domain->get_forms();
		} else {
			$forms = array(
				array( 'slug' => $domain->get_slug(), 'label' => $domain->get_label() ),
			);
		}

		foreach ( $forms as $order => $form_def ) {
			$form_id = OAT_Form::seed( array(
				'slug'       => $form_def['slug'],
				'label'      => $form_def['label'],
				'sort_order' => $order * 10,
			) );

			if ( $form_id ) {
				OAT_Domain_Form::assign( $domain_id, (int) $form_id, $order );
				$created++;
			}
		}

		return $created;
	}

	private static function seed_domain( $domain ) {
		if ( ! class_exists( 'OAT_Domain' ) ) {
			return 0;
		}

		$result = OAT_Domain::seed( array(
			'slug'           => $domain->get_slug(),
			'label'          => $domain->get_label(),
			'archivist_mode' => $domain->get_archivist_mode(),
			'active'         => 1,
		) );

		return $result !== false ? 1 : 0;
	}

	private static function seed_workflow_steps( $domain ) {
		if ( ! class_exists( 'OAT_Workflow_Step' ) ) {
			return 0;
		}

		$template = $domain->get_workflow_template();
		$steps    = array();
		$order    = 0;

		foreach ( $template as $step ) {
			$step_data = array(
				'step_id'            => $step['id'],
				'label'              => $step['label'],
				'sort_order'         => $order,
				'assignee_role'      => isset( $step['assignee_role'] ) ? $step['assignee_role'] : null,
				'visibility_tier'    => isset( $step['visibility_tier'] ) ? $step['visibility_tier'] : 'staff',
				'on_approve'         => isset( $step['on_approve'] ) ? $step['on_approve'] : null,
				'on_deny'            => isset( $step['on_deny'] ) ? $step['on_deny'] : null,
				'on_request_changes' => isset( $step['on_request_changes'] ) ? $step['on_request_changes'] : null,
				'timer_json'         => ! empty( $step['timer'] ) ? $step['timer'] : null,
				'condition_json'     => ! empty( $step['condition'] ) ? $step['condition'] : null,
				'multi_approve'      => ! empty( $step['multi_approve'] ) ? 1 : 0,
				'active'             => 1,
			);

			$steps[] = $step_data;
			$order += 10;
		}

		return OAT_Workflow_Step::seed( $domain->get_slug(), $steps );
	}
}
