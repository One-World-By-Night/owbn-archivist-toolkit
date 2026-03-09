<?php

defined( 'ABSPATH' ) || exit;

class OAT_Form_Field {

	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'oat_form_fields';
	}

	public static function get_fields( $domain_slug, $context = 'submit' ) {
		global $wpdb;
		$table = self::table();

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE form_slug = %s AND context = %s AND active = 1 ORDER BY sort_order ASC",
			$domain_slug,
			$context
		) );

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

	public static function find( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . self::table() . ' WHERE id = %d',
			$id
		) );
	}

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

	public static function delete( $id ) {
		global $wpdb;
		return (bool) $wpdb->delete( self::table(), array( 'id' => (int) $id ), array( '%d' ) );
	}

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

	public static function get_domains() {
		global $wpdb;
		return $wpdb->get_col( 'SELECT DISTINCT domain_slug FROM ' . self::table() . ' ORDER BY domain_slug ASC' );
	}

	public static function get_contexts( $domain_slug ) {
		global $wpdb;
		return $wpdb->get_col( $wpdb->prepare(
			'SELECT DISTINCT context FROM ' . self::table() . ' WHERE domain_slug = %s ORDER BY context ASC',
			$domain_slug
		) );
	}

	/**
	 * Get all active fields flagged as public_registry.
	 *
	 * @return array Field keys grouped by form_slug.
	 */
	public static function get_public_registry_keys() {
		global $wpdb;
		$rows = $wpdb->get_results(
			'SELECT form_slug, field_key FROM ' . self::table() . ' WHERE public_registry = 1 AND active = 1'
		);
		$keys = array();
		foreach ( $rows as $row ) {
			$slug = $row->form_slug ?: 'unknown';
			$keys[ $slug ][] = $row->field_key;
		}
		return $keys;
	}

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

		$field['options']         = self::json_decode_safe( $row->options_json );
		$field['validation']      = self::json_decode_safe( $row->validation_json );
		$field['condition']       = self::json_decode_safe( $row->condition_json );
		$field['attributes']      = self::json_decode_safe( $row->attributes_json );
		$field['public_registry'] = isset( $row->public_registry ) ? (bool) $row->public_registry : false;

		return $field;
	}

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

		if ( isset( $data['public_registry'] ) ) {
			$row['public_registry'] = $data['public_registry'] ? 1 : 0;
		}

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

	private static function json_decode_safe( $json ) {
		if ( null === $json || '' === $json ) {
			return null;
		}
		$decoded = json_decode( $json, true );
		return is_array( $decoded ) ? $decoded : null;
	}
}
