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
			$counts['forms'] += self::seed_form_for_domain( $domain );

			if ( method_exists( $domain, 'seed_form_fields' ) ) {
				$counts['fields'] += $domain->seed_form_fields();
			}
		}

		return $counts;
	}

	private static function seed_form_for_domain( $domain ) {
		if ( ! class_exists( 'OAT_Form' ) || ! class_exists( 'OAT_Domain_Form' ) || ! class_exists( 'OAT_Domain' ) ) {
			return 0;
		}

		$slug     = $domain->get_slug();
		$existing = OAT_Form::find_by_slug( $slug );
		$created  = 0;

		if ( ! $existing ) {
			$form_id = OAT_Form::seed( [
				'slug'  => $slug,
				'label' => $domain->get_label(),
			] );
			$created = $form_id ? 1 : 0;
		} else {
			$form_id = (int) $existing->id;
		}

		if ( $form_id ) {
			$domain_row = OAT_Domain::find_by_slug( $slug );
			if ( $domain_row ) {
				OAT_Domain_Form::assign( (int) $domain_row->id, (int) $form_id );
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
