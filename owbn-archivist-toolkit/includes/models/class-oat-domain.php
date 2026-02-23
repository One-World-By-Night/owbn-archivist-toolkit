<?php
/**
 * OAT Domain Model.
 *
 * CRUD operations for the oat_domains table.
 * Stores domain definitions (slug, label, archivist_mode, active).
 */

defined( 'ABSPATH' ) || exit;

class OAT_Domain {

	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'oat_domains';
	}

	/**
	 * Find a domain row by ID.
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
	 * Find a domain by slug.
	 *
	 * @param string $slug Domain slug.
	 * @return object|null DB row or null.
	 */
	public static function find_by_slug( $slug ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . self::table() . ' WHERE slug = %s',
			$slug
		) );
	}

	/**
	 * Get all domains, optionally including inactive.
	 *
	 * @param bool $include_inactive Whether to include inactive domains.
	 * @return array Array of DB row objects.
	 */
	public static function get_all( $include_inactive = false ) {
		global $wpdb;
		$table = self::table();

		if ( $include_inactive ) {
			return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY label ASC" );
		}

		return $wpdb->get_results( "SELECT * FROM {$table} WHERE active = 1 ORDER BY label ASC" );
	}

	/**
	 * Create or update a domain.
	 *
	 * @param array $data Domain data (slug, label, archivist_mode, active).
	 * @return int|false Row ID or false on failure.
	 */
	public static function save( array $data ) {
		global $wpdb;
		$table = self::table();
		$now   = time();

		$slug = isset( $data['slug'] ) ? sanitize_text_field( $data['slug'] ) : '';
		if ( '' === $slug ) {
			return false;
		}

		$row = array(
			'slug'           => $slug,
			'label'          => isset( $data['label'] ) ? sanitize_text_field( $data['label'] ) : $slug,
			'archivist_mode' => isset( $data['archivist_mode'] ) ? sanitize_text_field( $data['archivist_mode'] ) : 'manual',
			'active'         => isset( $data['active'] ) ? ( $data['active'] ? 1 : 0 ) : 1,
			'updated_at'     => $now,
		);

		$existing = self::find_by_slug( $slug );

		if ( $existing ) {
			$wpdb->update( $table, $row, array( 'id' => (int) $existing->id ) );
			return (int) $existing->id;
		}

		$row['created_at'] = $now;
		$wpdb->insert( $table, $row );
		return $wpdb->insert_id ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Delete a domain (hard delete).
	 *
	 * @param int $id Row ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		return (bool) $wpdb->delete( self::table(), array( 'id' => (int) $id ), array( '%d' ) );
	}

	/**
	 * Activate or deactivate a domain.
	 *
	 * @param int  $id     Row ID.
	 * @param bool $active Whether to activate.
	 * @return bool
	 */
	public static function set_active( $id, $active ) {
		global $wpdb;
		return (bool) $wpdb->update(
			self::table(),
			array( 'active' => $active ? 1 : 0, 'updated_at' => time() ),
			array( 'id' => (int) $id ),
			array( '%d', '%d' ),
			array( '%d' )
		);
	}

	/**
	 * Seed a domain (insert if slug doesn't exist).
	 *
	 * @param array $data Domain data.
	 * @return int|false Row ID if inserted, false if already exists or failed.
	 */
	public static function seed( array $data ) {
		$slug = isset( $data['slug'] ) ? sanitize_text_field( $data['slug'] ) : '';
		if ( '' === $slug ) {
			return false;
		}

		$existing = self::find_by_slug( $slug );
		if ( $existing ) {
			return false;
		}

		return self::save( $data );
	}
}
