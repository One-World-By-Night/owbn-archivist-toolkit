<?php

defined( 'ABSPATH' ) || exit;

class OAT_Domain_Form {

	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'oat_domain_forms';
	}

	/**
	 * Get all forms assigned to a domain, ordered by sort_order.
	 *
	 * @param int $domain_id Domain row ID.
	 * @return array Array of oat_forms rows.
	 */
	public static function get_forms_for_domain( $domain_id ) {
		global $wpdb;
		$jt = self::table();
		$ft = $wpdb->prefix . 'oat_forms';

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT f.*, df.sort_order AS domain_sort_order
			 FROM {$jt} df
			 INNER JOIN {$ft} f ON f.id = df.form_id
			 WHERE df.domain_id = %d AND f.active = 1
			 ORDER BY df.sort_order ASC, f.label ASC",
			$domain_id
		) );
	}

	/**
	 * Get forms for a domain by slug (convenience wrapper).
	 *
	 * @param string $domain_slug Domain slug.
	 * @return array Array of oat_forms rows.
	 */
	public static function get_forms_for_domain_slug( $domain_slug ) {
		if ( ! class_exists( 'OAT_Domain' ) ) {
			return [];
		}
		$domain = OAT_Domain::find_by_slug( $domain_slug );
		if ( ! $domain ) {
			return [];
		}
		return self::get_forms_for_domain( (int) $domain->id );
	}

	/**
	 * Get all domains a form is assigned to.
	 *
	 * @param int $form_id Form row ID.
	 * @return array Array of oat_domains rows.
	 */
	public static function get_domains_for_form( $form_id ) {
		global $wpdb;
		$jt = self::table();
		$dt = $wpdb->prefix . 'oat_domains';

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT d.*, df.sort_order AS form_sort_order
			 FROM {$jt} df
			 INNER JOIN {$dt} d ON d.id = df.domain_id
			 WHERE df.form_id = %d AND d.active = 1
			 ORDER BY d.sort_order ASC, d.label ASC",
			$form_id
		) );
	}

	/**
	 * Assign a form to a domain.
	 *
	 * @param int $domain_id Domain row ID.
	 * @param int $form_id   Form row ID.
	 * @param int $sort_order Sort order within domain.
	 * @return int|false Insert ID or false.
	 */
	public static function assign( $domain_id, $form_id, $sort_order = 0 ) {
		global $wpdb;

		// Skip if already assigned.
		$exists = $wpdb->get_var( $wpdb->prepare(
			'SELECT id FROM ' . self::table() . ' WHERE domain_id = %d AND form_id = %d',
			$domain_id,
			$form_id
		) );

		if ( $exists ) {
			return (int) $exists;
		}

		$wpdb->insert( self::table(), [
			'domain_id'  => (int) $domain_id,
			'form_id'    => (int) $form_id,
			'sort_order' => (int) $sort_order,
			'created_at' => time(),
		] );

		return $wpdb->insert_id ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Unassign a form from a domain.
	 */
	public static function unassign( $domain_id, $form_id ) {
		global $wpdb;
		return (bool) $wpdb->delete( self::table(), [
			'domain_id' => (int) $domain_id,
			'form_id'   => (int) $form_id,
		] );
	}

	/**
	 * Replace all form assignments for a domain.
	 *
	 * @param int   $domain_id Domain row ID.
	 * @param array $form_ids  Array of form IDs to assign (ordered).
	 */
	public static function sync_domain( $domain_id, array $form_ids ) {
		global $wpdb;

		$wpdb->delete( self::table(), [ 'domain_id' => (int) $domain_id ] );

		foreach ( $form_ids as $order => $form_id ) {
			self::assign( $domain_id, (int) $form_id, $order );
		}
	}

	/**
	 * Delete all assignments for a domain.
	 */
	public static function delete_for_domain( $domain_id ) {
		global $wpdb;
		return (int) $wpdb->delete( self::table(), [ 'domain_id' => (int) $domain_id ] );
	}

	/**
	 * Delete all assignments for a form.
	 */
	public static function delete_for_form( $form_id ) {
		global $wpdb;
		return (int) $wpdb->delete( self::table(), [ 'form_id' => (int) $form_id ] );
	}
}
