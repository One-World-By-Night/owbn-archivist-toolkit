<?php
/**
 * OAT Seeder.
 *
 * Populates oat_domains, oat_workflow_steps, and oat_form_fields tables
 * with default definitions from PHP domain classes. Only inserts rows
 * that don't already exist — admin customizations are preserved on
 * re-activation.
 */

defined( 'ABSPATH' ) || exit;

class OAT_Seeder {

	/**
	 * Run seeding for all registered domains.
	 *
	 * Called during activation (after tables are created) and
	 * optionally from an admin "Re-seed" button.
	 *
	 * @return array Counts: 'domains', 'steps', 'fields'.
	 */
	public static function run() {
		$counts = array(
			'domains' => 0,
			'steps'   => 0,
			'fields'  => 0,
		);

		$domains = apply_filters( 'oat_register_domains', array() );

		foreach ( $domains as $domain ) {
			if ( ! ( $domain instanceof OAT_Domain_Interface ) ) {
				continue;
			}

			// Seed domain definition.
			$counts['domains'] += self::seed_domain( $domain );

			// Seed workflow steps.
			$counts['steps'] += self::seed_workflow_steps( $domain );

			// Seed form fields.
			if ( method_exists( $domain, 'seed_form_fields' ) ) {
				$counts['fields'] += $domain->seed_form_fields();
			}
		}

		return $counts;
	}

	/**
	 * Seed a domain row from a PHP domain class.
	 *
	 * @param OAT_Domain_Interface $domain Domain instance.
	 * @return int 1 if inserted, 0 if already exists.
	 */
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

	/**
	 * Seed workflow steps from a PHP domain class.
	 *
	 * @param OAT_Domain_Interface $domain Domain instance.
	 * @return int Number of steps inserted.
	 */
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
