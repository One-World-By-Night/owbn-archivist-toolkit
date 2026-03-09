<?php
/**
 * OAT Domains List Table.
 *
 * WP_List_Table for displaying all registered domains.
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class OAT_Domains_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct( array(
			'singular' => 'domain',
			'plural'   => 'domains',
			'ajax'     => false,
		) );
	}

	/**
	 * @return array
	 */
	public function get_columns() {
		return array(
			'drag'           => '<span class="dashicons dashicons-menu" title="Drag to reorder"></span>',
			'slug'           => 'Slug',
			'label'          => 'Label',
			'sort_order'     => 'Order',
			'archivist_mode' => 'Archivist Mode',
			'active'         => 'Active',
			'workflow'       => 'Workflow Steps',
			'fields'         => 'Form Fields',
			'actions'        => 'Actions',
		);
	}

	/**
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'slug'       => array( 'slug', false ),
			'label'      => array( 'label', false ),
			'sort_order' => array( 'sort_order', true ),
		);
	}

	public function prepare_items() {
		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
		);

		$rows = OAT_Domain::get_all( true );

		// Count workflow steps and form fields per domain.
		$this->items = array();
		foreach ( $rows as $row ) {
			$item = (array) $row;
			$item['step_count']  = count( OAT_Workflow_Step::get_for_domain( $row->slug, true ) );
			$item['field_count'] = count( OAT_Form_Field::all_for_domain( $row->slug, true ) );
			$this->items[] = (object) $item;
		}

		// Sort.
		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : 'sort_order';
		$order   = isset( $_GET['order'] ) && 'desc' === $_GET['order'] ? 'desc' : 'asc';

		usort( $this->items, function ( $a, $b ) use ( $orderby, $order ) {
			if ( 'sort_order' === $orderby ) {
				$cmp = (int) $a->sort_order - (int) $b->sort_order;
				if ( 0 === $cmp ) {
					$cmp = strcmp( $a->label, $b->label );
				}
			} else {
				$cmp = strcmp( $a->$orderby, $b->$orderby );
			}
			return 'desc' === $order ? -$cmp : $cmp;
		} );
	}
	/**
	 * Emit data-id on each row for drag-and-drop reordering.
	 */
	public function single_row( $item ) {
		echo '<tr data-id="' . (int) $item->id . '">';
		$this->single_row_columns( $item );
		echo '</tr>';
	}

	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'drag':
				return '<span class="dashicons dashicons-menu oat-drag-handle" style="cursor:grab;color:#999;"></span>';

			case 'slug':
				$url = admin_url( 'admin.php?page=oat-domains&action=edit&domain_id=' . $item->id );
				return '<a href="' . esc_url( $url ) . '"><strong>' . esc_html( $item->slug ) . '</strong></a>';

			case 'label':
				return esc_html( $item->label );

			case 'sort_order':
				return (int) $item->sort_order;

			case 'archivist_mode':
				return esc_html( ucfirst( $item->archivist_mode ) );

			case 'active':
				return $item->active ? '<span style="color:green;">Yes</span>' : '<span style="color:gray;">No</span>';

			case 'workflow':
				$url = admin_url( 'admin.php?page=oat-workflow-steps&domain=' . urlencode( $item->slug ) );
				return '<a href="' . esc_url( $url ) . '">' . (int) $item->step_count . ' steps</a>';

			case 'fields':
				$url = admin_url( 'admin.php?page=oat-form-fields&domain=' . urlencode( $item->slug ) );
				return '<a href="' . esc_url( $url ) . '">' . (int) $item->field_count . ' fields</a>';

			case 'actions':
				return self::render_actions( $item );

			default:
				return '';
		}
	}
	private static function render_actions( $item ) {
		$actions = array();

		// Edit.
		$edit_url = admin_url( 'admin.php?page=oat-domains&action=edit&domain_id=' . $item->id );
		$actions[] = '<a href="' . esc_url( $edit_url ) . '">Edit</a>';

		// Toggle active.
		if ( $item->active ) {
			$url = wp_nonce_url(
				admin_url( 'admin.php?page=oat-domains&action=deactivate&domain_id=' . $item->id ),
				'oat_toggle_domain_' . $item->id
			);
			$actions[] = '<a href="' . esc_url( $url ) . '">Deactivate</a>';
		} else {
			$url = wp_nonce_url(
				admin_url( 'admin.php?page=oat-domains&action=activate&domain_id=' . $item->id ),
				'oat_toggle_domain_' . $item->id
			);
			$actions[] = '<a href="' . esc_url( $url ) . '">Activate</a>';
		}

		// Delete (only DB-only domains with no entries).
		$url = wp_nonce_url(
			admin_url( 'admin.php?page=oat-domains&action=delete&domain_id=' . $item->id ),
			'oat_delete_domain_' . $item->id
		);
		$actions[] = '<a href="' . esc_url( $url ) . '" onclick="return confirm(\'Delete this domain? This cannot be undone.\');" style="color:#a00;">Delete</a>';

		return implode( ' | ', $actions );
	}
}
