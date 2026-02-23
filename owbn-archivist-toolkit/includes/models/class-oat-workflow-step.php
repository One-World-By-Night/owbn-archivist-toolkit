<?php
/**
 * OAT Workflow Step Model.
 *
 * CRUD operations for the oat_workflow_steps table.
 * Stores per-domain workflow step definitions with routing, timers, and conditions.
 */

defined( 'ABSPATH' ) || exit;

class OAT_Workflow_Step {

	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'oat_workflow_steps';
	}

	/**
	 * Find a step row by ID.
	 *
	 * @param int $id Row ID.
	 * @return object|null DB row or null.
	 */
	public static function find( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . self::table() . ' WHERE id = %d',
			$id
		) );
	}

	/**
	 * Find a step by domain slug and step ID.
	 *
	 * @param string $domain_slug Domain slug.
	 * @param string $step_id     Step identifier.
	 * @return object|null DB row or null.
	 */
	public static function find_by_step( $domain_slug, $step_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . self::table() . ' WHERE domain_slug = %s AND step_id = %s',
			$domain_slug,
			$step_id
		) );
	}

	/**
	 * Get all steps for a domain, ordered by sort_order.
	 *
	 * @param string $domain_slug     Domain slug.
	 * @param bool   $include_inactive Include inactive steps.
	 * @return array Array of DB row objects.
	 */
	public static function get_for_domain( $domain_slug, $include_inactive = false ) {
		global $wpdb;
		$table = self::table();

		if ( $include_inactive ) {
			return $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM {$table} WHERE domain_slug = %s ORDER BY sort_order ASC",
				$domain_slug
			) );
		}

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE domain_slug = %s AND active = 1 ORDER BY sort_order ASC",
			$domain_slug
		) );
	}

	/**
	 * Get a step config array for the workflow engine.
	 *
	 * Converts a DB row into the array format the engine expects.
	 *
	 * @param string $domain_slug Domain slug.
	 * @param string $step_id     Step identifier.
	 * @return array|null Step config array or null if not found.
	 */
	public static function get_step_config( $domain_slug, $step_id ) {
		$row = self::find_by_step( $domain_slug, $step_id );
		if ( ! $row ) {
			return null;
		}
		return self::row_to_config( $row );
	}

	/**
	 * Get the full workflow template for a domain as an array of step configs.
	 *
	 * @param string $domain_slug Domain slug.
	 * @return array Array of step config arrays (same format as domain get_workflow_template).
	 */
	public static function get_workflow_template( $domain_slug ) {
		$rows = self::get_for_domain( $domain_slug );
		$steps = array();
		foreach ( $rows as $row ) {
			$steps[] = self::row_to_config( $row );
		}
		return $steps;
	}

	/**
	 * Create or update a workflow step.
	 *
	 * @param array $data Step data.
	 * @return int|false Row ID or false on failure.
	 */
	public static function save( array $data ) {
		global $wpdb;
		$table = self::table();

		$domain_slug = isset( $data['domain_slug'] ) ? sanitize_text_field( $data['domain_slug'] ) : '';
		$step_id     = isset( $data['step_id'] ) ? sanitize_text_field( $data['step_id'] ) : '';

		if ( '' === $domain_slug || '' === $step_id ) {
			return false;
		}

		$row = self::prepare_row( $data );

		$existing = self::find_by_step( $domain_slug, $step_id );

		if ( $existing ) {
			$wpdb->update( $table, $row, array( 'id' => (int) $existing->id ) );
			return (int) $existing->id;
		}

		$wpdb->insert( $table, $row );
		return $wpdb->insert_id ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Delete a step (hard delete).
	 *
	 * @param int $id Row ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		return (bool) $wpdb->delete( self::table(), array( 'id' => (int) $id ), array( '%d' ) );
	}

	/**
	 * Activate or deactivate a step.
	 *
	 * @param int  $id     Row ID.
	 * @param bool $active Whether to activate.
	 * @return bool
	 */
	public static function set_active( $id, $active ) {
		global $wpdb;
		return (bool) $wpdb->update(
			self::table(),
			array( 'active' => $active ? 1 : 0 ),
			array( 'id' => (int) $id ),
			array( '%d' ),
			array( '%d' )
		);
	}

	/**
	 * Delete all steps for a domain.
	 *
	 * @param string $domain_slug Domain slug.
	 * @return int Number of rows deleted.
	 */
	public static function delete_for_domain( $domain_slug ) {
		global $wpdb;
		return (int) $wpdb->delete(
			self::table(),
			array( 'domain_slug' => $domain_slug ),
			array( '%s' )
		);
	}

	/**
	 * Seed steps for a domain from an array of step definitions.
	 * Only inserts if the domain_slug+step_id combination doesn't exist.
	 *
	 * @param string $domain_slug Domain slug.
	 * @param array  $steps       Array of step definitions.
	 * @return int Number of steps inserted.
	 */
	public static function seed( $domain_slug, array $steps ) {
		global $wpdb;
		$table    = self::table();
		$inserted = 0;

		foreach ( $steps as $step ) {
			$step_id = isset( $step['step_id'] ) ? sanitize_text_field( $step['step_id'] ) : '';
			if ( '' === $step_id ) {
				continue;
			}

			$exists = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$table} WHERE domain_slug = %s AND step_id = %s LIMIT 1",
				$domain_slug,
				$step_id
			) );

			if ( $exists ) {
				continue;
			}

			$step['domain_slug'] = $domain_slug;
			$row = self::prepare_row( $step );

			$wpdb->insert( $table, $row );
			if ( $wpdb->insert_id ) {
				$inserted++;
			}
		}

		return $inserted;
	}

	// ══════════════════════════════════════════════════════════════════════════
	// INTERNAL HELPERS
	// ══════════════════════════════════════════════════════════════════════════

	/**
	 * Convert a DB row to the step config array the engine expects.
	 *
	 * @param object $row Database row.
	 * @return array Step config.
	 */
	private static function row_to_config( $row ) {
		$config = array(
			'id'                 => $row->step_id,
			'label'              => $row->label,
			'assignee_role'      => $row->assignee_role !== null ? $row->assignee_role : '',
			'visibility_tier'    => $row->visibility_tier,
			'on_approve'         => $row->on_approve,
			'on_deny'            => $row->on_deny,
			'on_request_changes' => $row->on_request_changes,
			'timer'              => self::json_decode_safe( $row->timer_json ),
			'condition'          => self::json_decode_safe( $row->condition_json ),
			'multi_approve'      => (bool) $row->multi_approve,
		);

		return $config;
	}

	/**
	 * Prepare a row array from input data for insert/update.
	 *
	 * @param array $data Input data.
	 * @return array Column => value pairs.
	 */
	private static function prepare_row( array $data ) {
		$row = array();

		$text_cols = array(
			'domain_slug', 'step_id', 'label', 'assignee_role',
			'visibility_tier', 'on_approve', 'on_deny', 'on_request_changes',
		);

		foreach ( $text_cols as $col ) {
			if ( array_key_exists( $col, $data ) ) {
				$row[ $col ] = $data[ $col ] !== null ? sanitize_text_field( $data[ $col ] ) : null;
			}
		}

		if ( isset( $data['sort_order'] ) ) {
			$row['sort_order'] = (int) $data['sort_order'];
		}

		if ( isset( $data['multi_approve'] ) ) {
			$row['multi_approve'] = $data['multi_approve'] ? 1 : 0;
		}

		if ( isset( $data['active'] ) ) {
			$row['active'] = $data['active'] ? 1 : 0;
		}

		// JSON columns: accept arrays or already-encoded strings.
		$json_cols = array( 'timer_json', 'condition_json' );
		foreach ( $json_cols as $col ) {
			if ( array_key_exists( $col, $data ) ) {
				$val = $data[ $col ];
				if ( null === $val ) {
					$row[ $col ] = null;
				} else {
					$row[ $col ] = is_string( $val ) ? $val : wp_json_encode( $val );
				}
			}
		}

		return $row;
	}

	/**
	 * Safely decode a JSON string.
	 *
	 * @param string|null $json JSON string.
	 * @return array|null Decoded data or null.
	 */
	private static function json_decode_safe( $json ) {
		if ( null === $json || '' === $json ) {
			return null;
		}
		$decoded = json_decode( $json, true );
		return is_array( $decoded ) ? $decoded : null;
	}
}
