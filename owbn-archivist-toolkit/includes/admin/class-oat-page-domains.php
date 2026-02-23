<?php
/**
 * OAT Domains Admin Page.
 *
 * CRUD UI for managing domain definitions (D-055).
 * Archivist-only (oat_admin capability).
 */

defined( 'ABSPATH' ) || exit;

class OAT_Page_Domains {

	/**
	 * Render the domains management page.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! OAT_Authorization::check( OAT_Constants::CAP_ADMIN ) ) {
			wp_die( 'You do not have permission to manage domains.' );
		}

		// Handle GET actions (activate, deactivate, delete).
		$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : '';
		if ( in_array( $action, array( 'activate', 'deactivate', 'delete' ), true ) ) {
			self::handle_get_action( $action );
		}

		// Handle POST (add/edit save).
		if ( 'POST' === $_SERVER['REQUEST_METHOD'] && ! empty( $_POST['oat_save_domain'] ) ) {
			check_admin_referer( 'oat_save_domain' );
			self::handle_save();
		}

		// Determine view: edit form or list.
		if ( 'edit' === $action || 'add' === $action ) {
			self::render_edit_form();
			return;
		}

		// List view.
		$list_table = new OAT_Domains_List_Table();
		$list_table->prepare_items();

		$message = isset( $_GET['message'] ) ? sanitize_text_field( $_GET['message'] ) : '';

		include OAT_PLUGIN_DIR . 'templates/admin/domains.php';
	}

	/**
	 * Handle GET-based actions.
	 *
	 * @param string $action
	 * @return void
	 */
	private static function handle_get_action( $action ) {
		$domain_id = isset( $_GET['domain_id'] ) ? absint( $_GET['domain_id'] ) : 0;

		if ( 0 === $domain_id ) {
			return;
		}

		switch ( $action ) {
			case 'activate':
				check_admin_referer( 'oat_toggle_domain_' . $domain_id );
				OAT_Domain::set_active( $domain_id, true );
				wp_redirect( admin_url( 'admin.php?page=oat-domains&message=activated' ) );
				exit;

			case 'deactivate':
				check_admin_referer( 'oat_toggle_domain_' . $domain_id );
				OAT_Domain::set_active( $domain_id, false );
				wp_redirect( admin_url( 'admin.php?page=oat-domains&message=deactivated' ) );
				exit;

			case 'delete':
				check_admin_referer( 'oat_delete_domain_' . $domain_id );
				$domain = OAT_Domain::find( $domain_id );
				if ( $domain ) {
					// Delete associated workflow steps and form fields.
					OAT_Workflow_Step::delete_for_domain( $domain->slug );
					OAT_Form_Field::delete_for_domain( $domain->slug );
					OAT_Domain::delete( $domain_id );
				}
				wp_redirect( admin_url( 'admin.php?page=oat-domains&message=deleted' ) );
				exit;
		}
	}

	/**
	 * Handle POST save (add or update).
	 *
	 * @return void
	 */
	private static function handle_save() {
		$data = array(
			'slug'           => sanitize_text_field( $_POST['slug'] ),
			'label'          => sanitize_text_field( $_POST['label'] ),
			'archivist_mode' => sanitize_text_field( $_POST['archivist_mode'] ),
			'active'         => ! empty( $_POST['active'] ) ? 1 : 0,
		);

		if ( empty( $data['slug'] ) || empty( $data['label'] ) ) {
			add_settings_error( 'oat_domains', 'missing_fields', 'Slug and label are required.', 'error' );
			return;
		}

		// Validate slug format.
		if ( ! preg_match( '/^[a-z0-9_]+$/', $data['slug'] ) ) {
			add_settings_error( 'oat_domains', 'invalid_slug', 'Slug must be lowercase letters, numbers, and underscores only.', 'error' );
			return;
		}

		$saved_id = OAT_Domain::save( $data );

		if ( false === $saved_id ) {
			add_settings_error( 'oat_domains', 'save_failed', 'Failed to save domain.', 'error' );
			return;
		}

		$domain_id = isset( $_POST['domain_id'] ) ? absint( $_POST['domain_id'] ) : 0;
		$msg = $domain_id > 0 ? 'updated' : 'added';
		wp_redirect( admin_url( 'admin.php?page=oat-domains&message=' . $msg ) );
		exit;
	}

	/**
	 * Render the add/edit form.
	 *
	 * @return void
	 */
	private static function render_edit_form() {
		$domain_id = isset( $_GET['domain_id'] ) ? absint( $_GET['domain_id'] ) : 0;

		$domain = null;
		if ( $domain_id > 0 ) {
			$row = OAT_Domain::find( $domain_id );
			if ( $row ) {
				$domain = (array) $row;
			}
		}

		// Defaults for new domain.
		if ( ! $domain ) {
			$domain = array(
				'id'             => 0,
				'slug'           => '',
				'label'          => '',
				'archivist_mode' => 'manual',
				'active'         => 1,
			);
		}

		$is_edit = $domain_id > 0;
		$title   = $is_edit ? 'Edit Domain' : 'Add Domain';

		include OAT_PLUGIN_DIR . 'templates/admin/domain-edit.php';
	}
}
