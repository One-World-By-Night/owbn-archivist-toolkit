<?php

defined( 'ABSPATH' ) || exit;

class OAT_Form {

	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'oat_forms';
	}

	public static function find( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . self::table() . ' WHERE id = %d',
			$id
		) );
	}

	public static function find_by_slug( $slug ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . self::table() . ' WHERE slug = %s',
			$slug
		) );
	}

	public static function get_all( $include_inactive = false ) {
		global $wpdb;
		$table = self::table();

		if ( $include_inactive ) {
			return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY sort_order ASC, label ASC" );
		}

		return $wpdb->get_results( "SELECT * FROM {$table} WHERE active = 1 ORDER BY sort_order ASC, label ASC" );
	}

	public static function save( array $data ) {
		global $wpdb;
		$table = self::table();
		$now   = time();

		$slug = isset( $data['slug'] ) ? sanitize_title( $data['slug'] ) : '';
		if ( '' === $slug ) {
			return false;
		}

		$existing = self::find_by_slug( $slug );

		$row = [
			'slug'        => $slug,
			'label'       => isset( $data['label'] ) ? sanitize_text_field( $data['label'] ) : $slug,
			'description' => isset( $data['description'] ) ? wp_kses_post( $data['description'] ) : null,
			'active'      => isset( $data['active'] ) ? ( $data['active'] ? 1 : 0 ) : 1,
			'sort_order'  => isset( $data['sort_order'] ) ? absint( $data['sort_order'] ) : 0,
			'updated_at'  => $now,
		];

		if ( $existing ) {
			$wpdb->update( $table, $row, [ 'id' => (int) $existing->id ] );
			return (int) $existing->id;
		}

		$row['created_at'] = $now;
		$wpdb->insert( $table, $row );
		return $wpdb->insert_id ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Insert-if-not-exists. Preserves admin customizations on re-seed.
	 */
	public static function seed( array $data ) {
		$slug = isset( $data['slug'] ) ? sanitize_title( $data['slug'] ) : '';
		if ( '' === $slug ) {
			return false;
		}

		$existing = self::find_by_slug( $slug );
		if ( $existing ) {
			return (int) $existing->id;
		}

		return self::save( $data );
	}

	public static function delete( $id ) {
		global $wpdb;
		return (bool) $wpdb->delete( self::table(), [ 'id' => (int) $id ], [ '%d' ] );
	}

	public static function set_active( $id, $active ) {
		global $wpdb;
		return (bool) $wpdb->update(
			self::table(),
			[ 'active' => $active ? 1 : 0, 'updated_at' => time() ],
			[ 'id' => (int) $id ],
			[ '%d', '%d' ],
			[ '%d' ]
		);
	}

	public static function count_fields( $form_slug ) {
		global $wpdb;
		$table = $wpdb->prefix . 'oat_form_fields';
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE form_slug = %s AND active = 1",
			$form_slug
		) );
	}

	public static function count_domains( $form_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'oat_domain_forms';
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE form_id = %d",
			$form_id
		) );
	}
}
