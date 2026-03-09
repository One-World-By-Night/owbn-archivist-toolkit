<?php

defined( 'ABSPATH' ) || exit;

class OAT_Domain_Form {

	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'oat_domain_forms';
	}

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

	public static function assign( $domain_id, $form_id, $sort_order = 0 ) {
		global $wpdb;

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

	public static function unassign( $domain_id, $form_id ) {
		global $wpdb;
		return (bool) $wpdb->delete( self::table(), [
			'domain_id' => (int) $domain_id,
			'form_id'   => (int) $form_id,
		] );
	}

	public static function sync_domain( $domain_id, array $form_ids ) {
		global $wpdb;

		$wpdb->delete( self::table(), [ 'domain_id' => (int) $domain_id ] );

		foreach ( $form_ids as $order => $form_id ) {
			self::assign( $domain_id, (int) $form_id, $order );
		}
	}

	public static function delete_for_domain( $domain_id ) {
		global $wpdb;
		return (int) $wpdb->delete( self::table(), [ 'domain_id' => (int) $domain_id ] );
	}

	public static function delete_for_form( $form_id ) {
		global $wpdb;
		return (int) $wpdb->delete( self::table(), [ 'form_id' => (int) $form_id ] );
	}
}
