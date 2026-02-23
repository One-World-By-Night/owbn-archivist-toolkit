<?php
/**
 * OAT Form Fields Admin Page.
 *
 * CRUD UI for managing per-domain form field definitions.
 * Archivist-only (oat_admin capability).
 */

defined( 'ABSPATH' ) || exit;

class OAT_Page_Form_Fields {

	/**
	 * Render the form fields management page.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! OAT_Authorization::check( OAT_Constants::CAP_ADMIN ) ) {
			wp_die( 'You do not have permission to manage form fields.' );
		}

		// Handle GET actions (activate, deactivate, delete).
		$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : '';
		if ( in_array( $action, array( 'activate', 'deactivate', 'delete' ), true ) ) {
			self::handle_get_action( $action );
		}

		// Handle POST: re-seed form fields.
		if ( 'POST' === $_SERVER['REQUEST_METHOD'] && ! empty( $_POST['oat_reseed_fields'] ) ) {
			check_admin_referer( 'oat_reseed_fields' );
			$counts = OAT_Seeder::run();
			$msg    = sprintf( 'seeded&fields=%d&domains=%d&steps=%d', $counts['fields'], $counts['domains'], $counts['steps'] );
			wp_redirect( admin_url( 'admin.php?page=oat-form-fields&message=' . $msg ) );
			exit;
		}

		// Handle POST (add/edit save).
		if ( 'POST' === $_SERVER['REQUEST_METHOD'] && ! empty( $_POST['oat_save_field'] ) ) {
			check_admin_referer( 'oat_save_field' );
			self::handle_save();
		}

		// Determine view: edit form or list.
		if ( 'edit' === $action || 'add' === $action ) {
			self::render_edit_form();
			return;
		}

		// List view.
		$list_table = new OAT_Form_Fields_List_Table();
		$list_table->prepare_items();

		$message = isset( $_GET['message'] ) ? sanitize_text_field( $_GET['message'] ) : '';

		include OAT_PLUGIN_DIR . 'templates/admin/form-fields.php';
	}

	/**
	 * Handle GET-based actions (activate, deactivate, delete).
	 *
	 * @param string $action
	 * @return void
	 */
	private static function handle_get_action( $action ) {
		$field_id = isset( $_GET['field_id'] ) ? absint( $_GET['field_id'] ) : 0;
		$domain   = isset( $_GET['domain'] ) ? sanitize_text_field( $_GET['domain'] ) : '';

		if ( 0 === $field_id ) {
			return;
		}

		switch ( $action ) {
			case 'activate':
				check_admin_referer( 'oat_toggle_field_' . $field_id );
				OAT_Form_Field::set_active( $field_id, true );
				wp_redirect( admin_url( 'admin.php?page=oat-form-fields&domain=' . urlencode( $domain ) . '&message=activated' ) );
				exit;

			case 'deactivate':
				check_admin_referer( 'oat_toggle_field_' . $field_id );
				OAT_Form_Field::set_active( $field_id, false );
				wp_redirect( admin_url( 'admin.php?page=oat-form-fields&domain=' . urlencode( $domain ) . '&message=deactivated' ) );
				exit;

			case 'delete':
				check_admin_referer( 'oat_delete_field_' . $field_id );
				OAT_Form_Field::delete( $field_id );
				wp_redirect( admin_url( 'admin.php?page=oat-form-fields&domain=' . urlencode( $domain ) . '&message=deleted' ) );
				exit;
		}
	}

	/**
	 * Handle POST save (add or update).
	 *
	 * @return void
	 */
	private static function handle_save() {
		$field_id = isset( $_POST['field_id'] ) ? absint( $_POST['field_id'] ) : 0;

		$data = array(
			'domain_slug'  => sanitize_text_field( $_POST['domain_slug'] ),
			'context'      => sanitize_text_field( $_POST['context'] ),
			'field_key'    => sanitize_text_field( $_POST['field_key'] ),
			'field_type'   => sanitize_text_field( $_POST['field_type'] ),
			'label'        => sanitize_text_field( $_POST['label'] ),
			'required'     => ! empty( $_POST['required'] ) ? 1 : 0,
			'sort_order'   => absint( $_POST['sort_order'] ),
			'placeholder'  => isset( $_POST['placeholder'] ) ? sanitize_text_field( $_POST['placeholder'] ) : '',
			'help_text'    => isset( $_POST['help_text'] ) ? sanitize_text_field( $_POST['help_text'] ) : '',
			'default_value' => isset( $_POST['default_value'] ) ? sanitize_text_field( $_POST['default_value'] ) : '',
			'active'       => ! empty( $_POST['active'] ) ? 1 : 0,
		);

		// JSON columns — accept raw JSON strings from the textarea.
		$json_cols = array( 'options_json', 'validation_json', 'condition_json', 'attributes_json' );
		foreach ( $json_cols as $col ) {
			$raw = isset( $_POST[ $col ] ) ? trim( wp_unslash( $_POST[ $col ] ) ) : '';
			if ( '' !== $raw ) {
				// Validate JSON.
				$decoded = json_decode( $raw, true );
				if ( null === $decoded && 'null' !== strtolower( $raw ) ) {
					add_settings_error( 'oat_form_fields', 'invalid_json', "Invalid JSON in {$col}.", 'error' );
					return;
				}
				$data[ $col ] = $raw;
			} else {
				$data[ $col ] = null;
			}
		}

		$saved_id = OAT_Form_Field::save( $data );

		if ( false === $saved_id ) {
			add_settings_error( 'oat_form_fields', 'save_failed', 'Failed to save field. Check required values.', 'error' );
			return;
		}

		$msg = $field_id > 0 ? 'updated' : 'added';
		wp_redirect( admin_url( 'admin.php?page=oat-form-fields&domain=' . urlencode( $data['domain_slug'] ) . '&message=' . $msg ) );
		exit;
	}

	/**
	 * Render the add/edit form.
	 *
	 * @return void
	 */
	private static function render_edit_form() {
		$field_id = isset( $_GET['field_id'] ) ? absint( $_GET['field_id'] ) : 0;
		$domain   = isset( $_GET['domain'] ) ? sanitize_text_field( $_GET['domain'] ) : '';

		$field = null;
		if ( $field_id > 0 ) {
			$row = OAT_Form_Field::find( $field_id );
			if ( $row ) {
				$field = (array) $row;
			}
		}

		// Defaults for new field.
		if ( ! $field ) {
			$field = array(
				'id'              => 0,
				'domain_slug'     => $domain,
				'context'         => 'submit',
				'field_key'       => '',
				'field_type'      => 'text',
				'label'           => '',
				'required'        => 0,
				'sort_order'      => 100,
				'placeholder'     => '',
				'help_text'       => '',
				'default_value'   => '',
				'active'          => 1,
				'options_json'    => '',
				'validation_json' => '',
				'condition_json'  => '',
				'attributes_json' => '',
			);
		}

		$is_edit = $field_id > 0;
		$title   = $is_edit ? 'Edit Form Field' : 'Add Form Field';

		// Get domains for dropdown (returns slug => info arrays).
		$domains = OAT_Domain_Registry::get_all();

		// Field types.
		$field_types = array(
			'text'              => 'Text',
			'textarea'          => 'Textarea',
			'htmlarea'          => 'Rich Text (HTML)',
			'number'            => 'Number',
			'select'            => 'Select',
			'checkbox'          => 'Checkbox',
			'radio'             => 'Radio',
			'date'              => 'Date',
			'hidden'            => 'Hidden',
			'heading'           => 'Section Heading',
			'chronicle_picker'  => 'Chronicle Picker',
			'coordinator_picker' => 'Coordinator Picker',
			'rule_picker'       => 'Regulation Rule Picker',
			'auto_prop'         => 'Auto Property',
		);

		$contexts = array( 'submit', 'review', 'escalate', 'resolve' );

		include OAT_PLUGIN_DIR . 'templates/admin/form-field-edit.php';
	}
}
