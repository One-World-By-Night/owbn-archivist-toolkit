<?php

defined( 'ABSPATH' ) || exit;

class OAT_Page_Taxonomy {

	/**
	 * Handle import/export before any output.
	 */
	public static function maybe_handle_actions() {
		if ( ! isset( $_GET['page'] ) || 'oat-taxonomy' !== $_GET['page'] ) {
			return;
		}
		if ( ! OAT_Authorization::check( OAT_Constants::CAP_ARCHIVIST ) ) {
			return;
		}

		// Export JSON.
		if ( ! empty( $_GET['export_json'] ) ) {
			$json = OAT_Creature_Taxonomy::export();
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="oat-creature-taxonomy-' . gmdate( 'Y-m-d' ) . '.json"' );
			header( 'Pragma: no-cache' );
			echo $json;
			exit;
		}

		// Import JSON (POST).
		if ( ! empty( $_POST['import_json'] ) && check_admin_referer( 'oat_taxonomy_import' ) ) {
			$json = wp_unslash( $_POST['import_json'] );
			if ( OAT_Creature_Taxonomy::import( $json ) ) {
				wp_redirect( admin_url( 'admin.php?page=oat-taxonomy&imported=1' ) );
				exit;
			}
		}
	}

	/**
	 * Register AJAX handlers.
	 */
	public static function init_ajax() {
		add_action( 'wp_ajax_oat_taxonomy_save', array( __CLASS__, 'ajax_save' ) );
		add_action( 'wp_ajax_oat_taxonomy_get', array( __CLASS__, 'ajax_get' ) );
	}

	/**
	 * AJAX: Get full taxonomy tree.
	 */
	public static function ajax_get() {
		check_ajax_referer( 'oat_taxonomy', 'nonce' );
		if ( ! OAT_Authorization::check( OAT_Constants::CAP_ARCHIVIST ) ) {
			wp_send_json_error( 'Permission denied.' );
		}
		wp_send_json_success( OAT_Creature_Taxonomy::get() );
	}

	/**
	 * AJAX: Save full taxonomy tree (replaces entire tree).
	 */
	public static function ajax_save() {
		check_ajax_referer( 'oat_taxonomy', 'nonce' );
		if ( ! OAT_Authorization::check( OAT_Constants::CAP_ARCHIVIST ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		$json = wp_unslash( $_POST['taxonomy'] ?? '' );
		$data = json_decode( $json, true );
		if ( ! $data || ! isset( $data['genres'] ) ) {
			wp_send_json_error( 'Invalid taxonomy data.' );
		}

		OAT_Creature_Taxonomy::set( $data );
		wp_send_json_success( array( 'saved' => true ) );
	}

	/**
	 * Render the taxonomy editor page.
	 */
	public static function render( $embedded = false ) {
		if ( ! OAT_Authorization::check( OAT_Constants::CAP_ARCHIVIST ) ) {
			wp_die( 'You do not have permission to manage the creature taxonomy.' );
		}

		// Seed on first visit if empty.
		OAT_Creature_Taxonomy::maybe_seed();

		$taxonomy = OAT_Creature_Taxonomy::get();
		$nonce    = wp_create_nonce( 'oat_taxonomy' );

		include OAT_PLUGIN_DIR . 'templates/admin/taxonomy.php';
	}
}
