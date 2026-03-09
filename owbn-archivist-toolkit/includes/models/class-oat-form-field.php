<?php
/**
 * OAT Form Field Model.
 *
 * CRUD operations for the oat_form_fields table.
 * Stores per-domain, per-context form field definitions.
 */

defined( 'ABSPATH' ) || exit;

class OAT_Form_Field {

	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'oat_form_fields';
	}

	/**
	 * Get all active fields for a domain + context, ordered by sort_order.
	 *
	 * Checks form_slug first (Phase 9), falls back to domain_slug for
	 * backwards compatibility with pre-migration data.
	 *
	 * @param string $domain_slug Domain slug (also used as form_slug fallback).
	 * @param string $context     Context (submit, review, escalate, resolve).
	 * @return array Array of field definition arrays.
	 */
	public static function get_fields( $domain_slug, $context = 'submit' ) {
		global $wpdb;
		$table = self::table();

		// Try form_slug first.
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE form_slug = %s AND context = %s AND active = 1 ORDER BY sort_order ASC",
			$domain_slug,
			$context
		) );

		// Fall back to domain_slug for pre-migration rows.
		if ( empty( $rows ) ) {
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM {$table} WHERE domain_slug = %s AND context = %s AND active = 1 ORDER BY sort_order ASC",
				$domain_slug,
				$context
			) );
		}

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$fields = array();
		foreach ( $rows as $row ) {
			$fields[] = self::row_to_field( $row );
		}
		return $fields;
	}

	/**
	 * Get all active fields for a form_slug + context.
	 *
	 * @param string $form_slug Form slug.
	 * @param string $context   Context.
	 * @return array Array of field definition arrays.
	 */
	public static function get_fields_by_form( $form_slug, $context = 'submit' ) {
		global $wpdb;

		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . self::table() . ' WHERE form_slug = %s AND context = %s AND active = 1 ORDER BY sort_order ASC',
			$form_slug,
			$context
		) );

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$fields = array();
		foreach ( $rows as $row ) {
			$fields[] = self::row_to_field( $row );
		}
		return $fields;
	}

	/**
	 * Get a single field by form_slug (or domain_slug fallback) + context + key.
	 *
	 * @param string $slug      Form slug (or domain slug for pre-migration data).
	 * @param string $context   Context.
	 * @param string $field_key Field key.
	 * @return array|null Field definition or null.
	 */
	public static function get_field( $slug, $context, $field_key ) {
		global $wpdb;
		$table = self::table();

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE form_slug = %s AND context = %s AND field_key = %s LIMIT 1",
			$slug,
			$context,
			$field_key
		) );

		if ( ! $row ) {
			$row = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM {$table} WHERE domain_slug = %s AND context = %s AND field_key = %s LIMIT 1",
				$slug,
				$context,
				$field_key
			) );
		}

		return $row ? self::row_to_field( $row ) : null;
	}

	/**
	 * Find a field row by ID.
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
	 * Get all fields for a form (all contexts), optionally including inactive.
	 *
	 * Tries form_slug first, falls back to domain_slug.
	 *
	 * @param string $slug             Form slug (or domain slug fallback).
	 * @param bool   $include_inactive Whether to include inactive fields.
	 * @return array Array of field definition arrays.
	 */
	public static function all_for_domain( $slug, $include_inactive = false ) {
		global $wpdb;
		$table = self::table();

		$active_clause = $include_inactive ? '' : ' AND active = 1';

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE form_slug = %s{$active_clause} ORDER BY context ASC, sort_order ASC",
			$slug
		) );

		if ( empty( $rows ) ) {
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM {$table} WHERE domain_slug = %s{$active_clause} ORDER BY context ASC, sort_order ASC",
				$slug
			) );
		}

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$fields = array();
		foreach ( $rows as $row ) {
			$fields[] = self::row_to_field( $row );
		}
		return $fields;
	}

	/**
	 * Get all fields for a form_slug (all contexts).
	 *
	 * @param string $form_slug        Form slug.
	 * @param bool   $include_inactive Whether to include inactive fields.
	 * @return array
	 */
	public static function all_for_form( $form_slug, $include_inactive = false ) {
		global $wpdb;
		$table = self::table();

		$active_clause = $include_inactive ? '' : ' AND active = 1';

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE form_slug = %s{$active_clause} ORDER BY context ASC, sort_order ASC",
			$form_slug
		) );

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$fields = array();
		foreach ( $rows as $row ) {
			$fields[] = self::row_to_field( $row );
		}
		return $fields;
	}

	/**
	 * Upsert a field definition.
	 *
	 * @param array $data Field data (domain_slug, context, field_key, field_type, ...).
	 * @return int|false Inserted/updated row ID or false.
	 */
	public static function save( array $data ) {
		global $wpdb;
		$table = self::table();

		$domain_slug = isset( $data['domain_slug'] ) ? sanitize_text_field( $data['domain_slug'] ) : '';
		$context     = isset( $data['context'] ) ? sanitize_text_field( $data['context'] ) : 'submit';
		$field_key   = isset( $data['field_key'] ) ? sanitize_text_field( $data['field_key'] ) : '';

		if ( '' === $domain_slug || '' === $field_key ) {
			return false;
		}

		$row_data = self::prepare_row( $data );

		// Check if exists.
		$existing = $wpdb->get_row( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE domain_slug = %s AND context = %s AND field_key = %s LIMIT 1",
			$domain_slug,
			$context,
			$field_key
		) );

		if ( $existing ) {
			$wpdb->update( $table, $row_data, array( 'id' => (int) $existing->id ) );
			return (int) $existing->id;
		}

		$wpdb->insert( $table, $row_data );
		return $wpdb->insert_id ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Delete a field (hard delete).
	 *
	 * @param int $id Field row ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		return (bool) $wpdb->delete( self::table(), array( 'id' => (int) $id ), array( '%d' ) );
	}

	/**
	 * Activate or deactivate a field.
	 *
	 * @param int  $id     Field row ID.
	 * @param bool $active Whether to activate (true) or deactivate (false).
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
	 * Seed fields for a form (and optional domain) from an array of definitions.
	 * Only inserts if the form_slug+context+key combination doesn't exist.
	 * Falls back to domain_slug+context+key check for pre-migration compat.
	 *
	 * @param string $slug   Form slug (or domain slug for legacy callers).
	 * @param array  $fields Array of field definitions.
	 * @return int Number of fields inserted.
	 */
	public static function seed( $slug, array $fields ) {
		global $wpdb;
		$table    = self::table();
		$inserted = 0;

		foreach ( $fields as $field ) {
			$context   = isset( $field['context'] ) ? sanitize_text_field( $field['context'] ) : 'submit';
			$field_key = isset( $field['field_key'] ) ? sanitize_text_field( $field['field_key'] ) : '';

			if ( '' === $field_key ) {
				continue;
			}

			// Skip if already exists (check form_slug first, then domain_slug).
			$exists = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$table} WHERE form_slug = %s AND context = %s AND field_key = %s LIMIT 1",
				$slug,
				$context,
				$field_key
			) );

			if ( ! $exists ) {
				$exists = $wpdb->get_var( $wpdb->prepare(
					"SELECT id FROM {$table} WHERE domain_slug = %s AND context = %s AND field_key = %s LIMIT 1",
					$slug,
					$context,
					$field_key
				) );
			}

			if ( $exists ) {
				continue;
			}

			$field['domain_slug'] = $slug;
			$field['form_slug']   = $slug;
			$row_data = self::prepare_row( $field );

			$wpdb->insert( $table, $row_data );
			if ( $wpdb->insert_id ) {
				$inserted++;
			}
		}

		return $inserted;
	}

	/**
	 * Delete all fields for a domain (optionally by context).
	 *
	 * @param string      $domain_slug Domain slug.
	 * @param string|null $context     Optional context to limit deletion.
	 * @return int Number of rows deleted.
	 */
	public static function delete_for_domain( $domain_slug, $context = null ) {
		global $wpdb;
		$table = self::table();

		$where = array( 'domain_slug' => $domain_slug );
		$format = array( '%s' );

		if ( null !== $context ) {
			$where['context'] = $context;
			$format[] = '%s';
		}

		return (int) $wpdb->delete( $table, $where, $format );
	}

	/**
	 * Get distinct domains that have form fields.
	 *
	 * @return array Array of domain_slug strings.
	 */
	public static function get_domains() {
		global $wpdb;
		return $wpdb->get_col( 'SELECT DISTINCT domain_slug FROM ' . self::table() . ' ORDER BY domain_slug ASC' );
	}

	/**
	 * Get distinct contexts for a domain.
	 *
	 * @param string $domain_slug Domain slug.
	 * @return array Array of context strings.
	 */
	public static function get_contexts( $domain_slug ) {
		global $wpdb;
		return $wpdb->get_col( $wpdb->prepare(
			'SELECT DISTINCT context FROM ' . self::table() . ' WHERE domain_slug = %s ORDER BY context ASC',
			$domain_slug
		) );
	}

	// ══════════════════════════════════════════════════════════════════════════
	// INTERNAL HELPERS
	// ══════════════════════════════════════════════════════════════════════════

	/**
	 * Convert a DB row to a normalized field definition array.
	 *
	 * @param object $row Database row.
	 * @return array Field definition.
	 */
	private static function row_to_field( $row ) {
		$field = array(
			'id'          => (int) $row->id,
			'domain_slug' => $row->domain_slug,
			'form_slug'   => isset( $row->form_slug ) ? $row->form_slug : $row->domain_slug,
			'context'     => $row->context,
			'key'         => $row->field_key,
			'type'        => $row->field_type,
			'label'       => $row->label,
			'required'    => (bool) $row->required,
			'sort_order'  => (int) $row->sort_order,
			'placeholder' => $row->placeholder,
			'help_text'   => $row->help_text,
			'default'     => $row->default_value,
			'active'      => (bool) $row->active,
		);

		// Decode JSON columns.
		$field['options']    = self::json_decode_safe( $row->options_json );
		$field['validation'] = self::json_decode_safe( $row->validation_json );
		$field['condition']  = self::json_decode_safe( $row->condition_json );
		$field['attributes'] = self::json_decode_safe( $row->attributes_json );

		return $field;
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
			'domain_slug', 'form_slug', 'context', 'field_key', 'field_type',
			'label', 'placeholder', 'help_text', 'default_value',
		);

		foreach ( $text_cols as $col ) {
			if ( isset( $data[ $col ] ) ) {
				$row[ $col ] = sanitize_text_field( $data[ $col ] );
			}
		}

		if ( isset( $data['required'] ) ) {
			$row['required'] = $data['required'] ? 1 : 0;
		}

		if ( isset( $data['sort_order'] ) ) {
			$row['sort_order'] = (int) $data['sort_order'];
		}

		if ( isset( $data['active'] ) ) {
			$row['active'] = $data['active'] ? 1 : 0;
		}

		// JSON columns: accept arrays or already-encoded strings.
		$json_cols = array(
			'options_json', 'validation_json', 'condition_json', 'attributes_json',
		);

		foreach ( $json_cols as $col ) {
			if ( isset( $data[ $col ] ) ) {
				$val = $data[ $col ];
				$row[ $col ] = is_string( $val ) ? $val : wp_json_encode( $val );
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
