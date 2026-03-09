<?php

defined( 'ABSPATH' ) || exit;

class OAT_Page_Forms {

	public static function render() {
		if ( ! OAT_Authorization::check( OAT_Constants::CAP_ADMIN ) ) {
			wp_die( 'You do not have permission to manage forms.' );
		}

		$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : '';

		if ( in_array( $action, [ 'activate', 'deactivate', 'delete' ], true ) ) {
			self::handle_get_action( $action );
		}

		if ( 'POST' === $_SERVER['REQUEST_METHOD'] && ! empty( $_POST['oat_save_form'] ) ) {
			check_admin_referer( 'oat_save_form' );
			self::handle_save();
		}

		if ( 'edit' === $action || 'add' === $action ) {
			self::render_edit_form();
			return;
		}

		$list_table = new OAT_Forms_List_Table();
		$list_table->prepare_items();

		$message = isset( $_GET['message'] ) ? sanitize_text_field( $_GET['message'] ) : '';

		include OAT_PLUGIN_DIR . 'templates/admin/forms.php';
	}

	private static function handle_get_action( $action ) {
		$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
		if ( 0 === $form_id ) {
			return;
		}

		switch ( $action ) {
			case 'activate':
				check_admin_referer( 'oat_toggle_form_' . $form_id );
				OAT_Form::set_active( $form_id, true );
				wp_redirect( admin_url( 'admin.php?page=oat-forms&message=activated' ) );
				exit;

			case 'deactivate':
				check_admin_referer( 'oat_toggle_form_' . $form_id );
				OAT_Form::set_active( $form_id, false );
				wp_redirect( admin_url( 'admin.php?page=oat-forms&message=deactivated' ) );
				exit;

			case 'delete':
				check_admin_referer( 'oat_delete_form_' . $form_id );
				OAT_Domain_Form::delete_for_form( $form_id );
				OAT_Form::delete( $form_id );
				wp_redirect( admin_url( 'admin.php?page=oat-forms&message=deleted' ) );
				exit;
		}
	}

	private static function handle_save() {
		$data = [
			'slug'        => sanitize_title( $_POST['slug'] ),
			'label'       => sanitize_text_field( $_POST['label'] ),
			'description' => isset( $_POST['description'] ) ? wp_kses_post( $_POST['description'] ) : '',
			'active'      => ! empty( $_POST['active'] ) ? 1 : 0,
			'sort_order'  => isset( $_POST['sort_order'] ) ? absint( $_POST['sort_order'] ) : 0,
		];

		if ( empty( $data['slug'] ) || empty( $data['label'] ) ) {
			add_settings_error( 'oat_forms', 'missing_fields', 'Slug and label are required.', 'error' );
			return;
		}

		if ( ! preg_match( '/^[a-z0-9_-]+$/', $data['slug'] ) ) {
			add_settings_error( 'oat_forms', 'invalid_slug', 'Slug must be lowercase letters, numbers, hyphens, and underscores only.', 'error' );
			return;
		}

		$saved_id = OAT_Form::save( $data );

		if ( false === $saved_id ) {
			add_settings_error( 'oat_forms', 'save_failed', 'Failed to save form.', 'error' );
			return;
		}

		// Sync domain assignments.
		$assigned_domains = isset( $_POST['domain_ids'] ) ? array_map( 'absint', $_POST['domain_ids'] ) : [];
		self::sync_domain_assignments( $saved_id, $assigned_domains );

		$form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
		$msg = $form_id > 0 ? 'updated' : 'added';
		wp_redirect( admin_url( 'admin.php?page=oat-forms&message=' . $msg ) );
		exit;
	}

	private static function sync_domain_assignments( $form_id, array $domain_ids ) {
		// Remove all current assignments for this form.
		OAT_Domain_Form::delete_for_form( $form_id );

		// Re-assign selected domains.
		foreach ( $domain_ids as $order => $domain_id ) {
			OAT_Domain_Form::assign( $domain_id, $form_id, $order );
		}
	}

	private static function render_edit_form() {
		$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;

		$form = null;
		if ( $form_id > 0 ) {
			$row = OAT_Form::find( $form_id );
			if ( $row ) {
				$form = (array) $row;
			}
		}

		if ( ! $form ) {
			$form = [
				'id'          => 0,
				'slug'        => '',
				'label'       => '',
				'description' => '',
				'active'      => 1,
				'sort_order'  => 0,
			];
		}

		$is_edit    = $form_id > 0;
		$title      = $is_edit ? 'Edit Form' : 'Add Form';
		$all_domains = OAT_Domain::get_all( true );

		// Get currently assigned domain IDs.
		$assigned_domain_ids = [];
		if ( $is_edit ) {
			$assigned = OAT_Domain_Form::get_domains_for_form( $form_id );
			foreach ( $assigned as $d ) {
				$assigned_domain_ids[] = (int) $d->id;
			}
		}

		include OAT_PLUGIN_DIR . 'templates/admin/form-edit.php';
	}
}
