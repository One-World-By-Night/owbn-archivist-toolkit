<?php
/**
 * OAT Workflow Steps List Table.
 *
 * WP_List_Table for displaying workflow steps for a single domain.
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class OAT_Workflow_Steps_List_Table extends WP_List_Table {

	/** @var string */
	private $domain_slug;

	/**
	 * @param string $domain_slug Domain to show steps for.
	 */
	public function __construct( $domain_slug ) {
		parent::__construct( array(
			'singular' => 'step',
			'plural'   => 'steps',
			'ajax'     => false,
		) );
		$this->domain_slug = $domain_slug;
	}

	/**
	 * @return array
	 */
	public function get_columns() {
		return array(
			'sort_order'         => '#',
			'step_id'            => 'Step ID',
			'label'              => 'Label',
			'assignee_role'      => 'Assignee Role',
			'visibility_tier'    => 'Tier',
			'on_approve'         => 'On Approve',
			'on_deny'            => 'On Deny',
			'on_request_changes' => 'On Req Changes',
			'timer'              => 'Timer',
			'active'             => 'Active',
			'actions'            => 'Actions',
		);
	}

	public function prepare_items() {
		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			array(),
		);

		$this->items = OAT_Workflow_Step::get_for_domain( $this->domain_slug, true );
	}

	/**
	 * @param object $item
	 * @param string $column_name
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'sort_order':
				return (int) $item->sort_order;

			case 'step_id':
				$url = admin_url( 'admin.php?page=oat-workflow-steps&action=edit&step_id=' . $item->id . '&domain=' . urlencode( $this->domain_slug ) );
				return '<a href="' . esc_url( $url ) . '"><strong>' . esc_html( $item->step_id ) . '</strong></a>';

			case 'label':
				return esc_html( $item->label );

			case 'assignee_role':
				return $item->assignee_role ? '<code>' . esc_html( $item->assignee_role ) . '</code>' : '<em>none</em>';

			case 'visibility_tier':
				return esc_html( ucfirst( $item->visibility_tier ) );

			case 'on_approve':
				return $item->on_approve ? '<code>' . esc_html( $item->on_approve ) . '</code>' : '<em>terminal</em>';

			case 'on_deny':
				return $item->on_deny ? '<code>' . esc_html( $item->on_deny ) . '</code>' : '<em>terminal</em>';

			case 'on_request_changes':
				return $item->on_request_changes ? '<code>' . esc_html( $item->on_request_changes ) . '</code>' : '<em>none</em>';

			case 'timer':
				if ( empty( $item->timer_json ) ) {
					return '<em>none</em>';
				}
				$timer = json_decode( $item->timer_json, true );
				if ( ! is_array( $timer ) ) {
					return '<em>invalid</em>';
				}
				$dur = isset( $timer['duration'] ) ? (int) $timer['duration'] : 0;
				$days = round( $dur / 86400 );
				$action = isset( $timer['auto_action'] ) ? $timer['auto_action'] : '?';
				$bumps = isset( $timer['bump_required'] ) ? (int) $timer['bump_required'] : 0;
				return esc_html( "{$days}d / {$action}" . ( $bumps ? " / {$bumps} bumps" : '' ) );

			case 'active':
				return $item->active ? '<span style="color:green;">Yes</span>' : '<span style="color:gray;">No</span>';

			case 'actions':
				return self::render_actions( $item, $this->domain_slug );

			default:
				return '';
		}
	}

	/**
	 * @param object $item
	 * @param string $domain_slug
	 * @return string
	 */
	private static function render_actions( $item, $domain_slug ) {
		$actions = array();

		// Edit.
		$edit_url = admin_url( 'admin.php?page=oat-workflow-steps&action=edit&step_id=' . $item->id . '&domain=' . urlencode( $domain_slug ) );
		$actions[] = '<a href="' . esc_url( $edit_url ) . '">Edit</a>';

		// Toggle active.
		if ( $item->active ) {
			$url = wp_nonce_url(
				admin_url( 'admin.php?page=oat-workflow-steps&action=deactivate&step_id=' . $item->id . '&domain=' . urlencode( $domain_slug ) ),
				'oat_toggle_step_' . $item->id
			);
			$actions[] = '<a href="' . esc_url( $url ) . '">Deactivate</a>';
		} else {
			$url = wp_nonce_url(
				admin_url( 'admin.php?page=oat-workflow-steps&action=activate&step_id=' . $item->id . '&domain=' . urlencode( $domain_slug ) ),
				'oat_toggle_step_' . $item->id
			);
			$actions[] = '<a href="' . esc_url( $url ) . '">Activate</a>';
		}

		// Delete.
		$url = wp_nonce_url(
			admin_url( 'admin.php?page=oat-workflow-steps&action=delete&step_id=' . $item->id . '&domain=' . urlencode( $domain_slug ) ),
			'oat_delete_step_' . $item->id
		);
		$actions[] = '<a href="' . esc_url( $url ) . '" onclick="return confirm(\'Delete this step? This cannot be undone.\');" style="color:#a00;">Delete</a>';

		return implode( ' | ', $actions );
	}
}
