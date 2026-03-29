<?php
/**
 * OAT Workflow Steps Admin Page.
 *
 * CRUD UI for managing per-domain workflow step definitions (D-055).
 * Archivist-only (oat_admin capability).
 */

defined( 'ABSPATH' ) || exit;

class OAT_Page_Workflow_Steps {

	/**
	 * Render the workflow steps management page.
	 *
	 * @return void
	 */
	public static function render( $embedded = false ) {
		if ( ! OAT_Authorization::check( OAT_Constants::CAP_ADMIN ) ) {
			wp_die( 'You do not have permission to manage workflow steps.' );
		}

		// Handle GET actions (activate, deactivate, delete).
		$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : '';
		if ( in_array( $action, array( 'activate', 'deactivate', 'delete' ), true ) ) {
			self::handle_get_action( $action );
		}

		// Handle POST (add/edit save).
		if ( 'POST' === $_SERVER['REQUEST_METHOD'] && ! empty( $_POST['oat_save_step'] ) ) {
			check_admin_referer( 'oat_save_step' );
			self::handle_save();
		}

		// Determine view: edit form or list.
		if ( 'edit' === $action || 'add' === $action ) {
			self::render_edit_form();
			return;
		}

		// List view — require a domain to be selected.
		$current_domain = isset( $_GET['domain'] ) ? sanitize_text_field( $_GET['domain'] ) : '';
		$domains = OAT_Domain_Registry::get_all();

		$list_table = null;
		if ( '' !== $current_domain ) {
			$list_table = new OAT_Workflow_Steps_List_Table( $current_domain );
			$list_table->prepare_items();
		}

		$message = isset( $_GET['message'] ) ? sanitize_text_field( $_GET['message'] ) : '';

		include OAT_PLUGIN_DIR . 'templates/admin/workflow-steps.php';
	}

	/**
	 * Handle GET-based actions.
	 *
	 * @param string $action
	 * @return void
	 */
	private static function handle_get_action( $action ) {
		$step_id = isset( $_GET['step_id'] ) ? absint( $_GET['step_id'] ) : 0;
		$domain  = isset( $_GET['domain'] ) ? sanitize_text_field( $_GET['domain'] ) : '';

		if ( 0 === $step_id ) {
			return;
		}

		switch ( $action ) {
			case 'activate':
				check_admin_referer( 'oat_toggle_step_' . $step_id );
				OAT_Workflow_Step::set_active( $step_id, true );
				wp_redirect( admin_url( 'admin.php?page=oat-workflow-steps&domain=' . urlencode( $domain ) . '&message=activated' ) );
				exit;

			case 'deactivate':
				check_admin_referer( 'oat_toggle_step_' . $step_id );
				OAT_Workflow_Step::set_active( $step_id, false );
				wp_redirect( admin_url( 'admin.php?page=oat-workflow-steps&domain=' . urlencode( $domain ) . '&message=deactivated' ) );
				exit;

			case 'delete':
				check_admin_referer( 'oat_delete_step_' . $step_id );
				OAT_Workflow_Step::delete( $step_id );
				wp_redirect( admin_url( 'admin.php?page=oat-workflow-steps&domain=' . urlencode( $domain ) . '&message=deleted' ) );
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
			'domain_slug'        => sanitize_text_field( $_POST['domain_slug'] ),
			'step_id'            => sanitize_text_field( $_POST['step_id'] ),
			'label'              => sanitize_text_field( $_POST['label'] ),
			'sort_order'         => absint( $_POST['sort_order'] ),
			'assignee_role'      => isset( $_POST['assignee_role'] ) && '' !== $_POST['assignee_role']
				? sanitize_text_field( $_POST['assignee_role'] ) : null,
			'visibility_tier'    => sanitize_text_field( $_POST['visibility_tier'] ),
			'on_approve'         => isset( $_POST['on_approve'] ) && '' !== $_POST['on_approve']
				? sanitize_text_field( $_POST['on_approve'] ) : null,
			'on_deny'            => isset( $_POST['on_deny'] ) && '' !== $_POST['on_deny']
				? sanitize_text_field( $_POST['on_deny'] ) : null,
			'on_request_changes' => isset( $_POST['on_request_changes'] ) && '' !== $_POST['on_request_changes']
				? sanitize_text_field( $_POST['on_request_changes'] ) : null,
			'multi_approve'      => ! empty( $_POST['multi_approve'] ) ? 1 : 0,
			'active'             => ! empty( $_POST['active'] ) ? 1 : 0,
		);

		if ( empty( $data['domain_slug'] ) || empty( $data['step_id'] ) || empty( $data['label'] ) ) {
			add_settings_error( 'oat_workflow_steps', 'missing_fields', 'Domain, step ID, and label are required.', 'error' );
			return;
		}

		// JSON columns.
		foreach ( array( 'timer_json', 'condition_json' ) as $col ) {
			$raw = isset( $_POST[ $col ] ) ? trim( wp_unslash( $_POST[ $col ] ) ) : '';
			if ( '' !== $raw ) {
				$decoded = json_decode( $raw, true );
				if ( null === $decoded && 'null' !== strtolower( $raw ) ) {
					add_settings_error( 'oat_workflow_steps', 'invalid_json', "Invalid JSON in {$col}.", 'error' );
					return;
				}
				$data[ $col ] = $raw;
			} else {
				$data[ $col ] = null;
			}
		}

		$saved_id = OAT_Workflow_Step::save( $data );

		if ( false === $saved_id ) {
			add_settings_error( 'oat_workflow_steps', 'save_failed', 'Failed to save step.', 'error' );
			return;
		}

		$row_id = isset( $_POST['row_id'] ) ? absint( $_POST['row_id'] ) : 0;
		$msg = $row_id > 0 ? 'updated' : 'added';
		wp_redirect( admin_url( 'admin.php?page=oat-workflow-steps&domain=' . urlencode( $data['domain_slug'] ) . '&message=' . $msg ) );
		exit;
	}

	/**
	 * Render the add/edit form.
	 *
	 * @return void
	 */
	private static function render_edit_form() {
		$row_id = isset( $_GET['step_id'] ) ? absint( $_GET['step_id'] ) : 0;
		$domain = isset( $_GET['domain'] ) ? sanitize_text_field( $_GET['domain'] ) : '';

		$step = null;
		if ( $row_id > 0 ) {
			$row = OAT_Workflow_Step::find( $row_id );
			if ( $row ) {
				$step = (array) $row;
			}
		}

		// Defaults for new step.
		if ( ! $step ) {
			$step = array(
				'id'                 => 0,
				'domain_slug'        => $domain,
				'step_id'            => '',
				'label'              => '',
				'sort_order'         => 0,
				'assignee_role'      => '',
				'visibility_tier'    => 'staff',
				'on_approve'         => '',
				'on_deny'            => '',
				'on_request_changes' => '',
				'timer_json'         => '',
				'condition_json'     => '',
				'multi_approve'      => 0,
				'active'             => 1,
			);
		}

		$is_edit = $row_id > 0;
		$title   = $is_edit ? 'Edit Workflow Step' : 'Add Workflow Step';

		// Get existing steps for this domain (for routing dropdowns).
		$existing_steps = OAT_Workflow_Step::get_for_domain( $step['domain_slug'], true );

		$tiers = array( 'staff', 'coordinator', 'archivist' );

		include OAT_PLUGIN_DIR . 'templates/admin/workflow-step-edit.php';
	}
}
