<?php

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class OAT_Form_Fields_List_Table extends WP_List_Table {

	/**
	 * Current domain filter.
	 *
	 * @var string
	 */
	private $domain_slug = '';

	/**
	 * Current context filter.
	 *
	 * @var string
	 */
	private $context = '';

	public function __construct() {
		parent::__construct( array(
			'singular' => 'form-field',
			'plural'   => 'form-fields',
			'ajax'     => false,
		) );
	}

	/**
	 * @return array
	 */
	public function get_columns() {
		return array(
			'sort_order' => 'Order',
			'field_key'  => 'Key',
			'field_type' => 'Type',
			'label'      => 'Label',
			'context'    => 'Context',
			'required'   => 'Req',
			'active'     => 'Active',
			'actions'    => 'Actions',
		);
	}

	/**
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'sort_order' => array( 'sort_order', true ),
			'field_key'  => array( 'field_key', false ),
			'context'    => array( 'context', false ),
			'field_type' => array( 'field_type', false ),
		);
	}

	/**
	 * @return void
	 */
	public function prepare_items() {
		$this->domain_slug = isset( $_GET['domain'] ) ? sanitize_text_field( $_GET['domain'] ) : '';
		$this->context     = isset( $_GET['context'] ) ? sanitize_text_field( $_GET['context'] ) : '';

		if ( '' === $this->domain_slug ) {
			$this->items = array();
			$this->set_pagination_args( array( 'total_items' => 0, 'per_page' => 20 ) );
			$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
			return;
		}

		$include_inactive = true;
		$all = OAT_Form_Field::all_for_domain( $this->domain_slug, $include_inactive );

		// Filter by context if set.
		if ( '' !== $this->context ) {
			$all = array_values( array_filter( $all, function ( $f ) {
				return $f['context'] === $this->context;
			} ) );
		}

		// Sort — default by context then sort_order.
		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : 'sort_order';
		$order   = isset( $_GET['order'] ) && strtoupper( $_GET['order'] ) === 'DESC' ? 'DESC' : 'ASC';

		$allowed = array( 'sort_order', 'field_key', 'context', 'field_type' );
		if ( ! in_array( $orderby, $allowed, true ) ) {
			$orderby = 'sort_order';
		}

		usort( $all, function ( $a, $b ) use ( $orderby, $order ) {
			if ( $orderby === 'sort_order' ) {
				// Primary: context, secondary: sort_order.
				$cmp = strcmp( $a['context'], $b['context'] );
				if ( 0 === $cmp ) {
					$cmp = $a['sort_order'] - $b['sort_order'];
				}
			} else {
				$av = isset( $a[ $orderby ] ) ? $a[ $orderby ] : '';
				$bv = isset( $b[ $orderby ] ) ? $b[ $orderby ] : '';
				$cmp = is_int( $av ) ? ( $av - $bv ) : strcmp( (string) $av, (string) $bv );
			}
			return $order === 'DESC' ? -$cmp : $cmp;
		} );

		// Pagination.
		$per_page     = 50;
		$current_page = $this->get_pagenum();
		$total        = count( $all );
		$this->items  = array_slice( $all, ( $current_page - 1 ) * $per_page, $per_page );

		$this->set_pagination_args( array(
			'total_items' => $total,
			'per_page'    => $per_page,
		) );

		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
		);
	}

	/**
	 * @param array  $item
	 * @param string $column_name
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'sort_order':
				return (int) $item['sort_order'];

			case 'field_key':
				return '<code>' . esc_html( $item['key'] ) . '</code>';

			case 'field_type':
				return esc_html( $item['type'] );

			case 'label':
				$label = esc_html( $item['label'] );
				if ( ! empty( $item['help_text'] ) ) {
					$label .= ' <span class="dashicons dashicons-info-outline" title="' . esc_attr( $item['help_text'] ) . '"></span>';
				}
				return $label;

			case 'context':
				return '<span class="oat-context-badge oat-context-' . esc_attr( $item['context'] ) . '">'
					. esc_html( ucfirst( $item['context'] ) ) . '</span>';

			case 'required':
				return $item['required'] ? '<span class="dashicons dashicons-yes" style="color:#46b450;"></span>' : '&mdash;';

			case 'active':
				return $item['active']
					? '<span class="dashicons dashicons-yes" style="color:#46b450;" title="Active"></span>'
					: '<span class="dashicons dashicons-no" style="color:#dc3232;" title="Inactive"></span>';

			case 'actions':
				return $this->render_actions( $item );

			default:
				return '';
		}
	}

	/**
	 * Render action links for a field row.
	 *
	 * @param array $item
	 * @return string
	 */
	private function render_actions( $item ) {
		$page   = 'oat-form-fields';
		$domain = urlencode( $this->domain_slug );
		$id     = (int) $item['id'];

		$links = array();

		// Edit.
		$edit_url = admin_url( "admin.php?page={$page}&action=edit&field_id={$id}&domain={$domain}" );
		$links[]  = '<a href="' . esc_url( $edit_url ) . '">Edit</a>';

		// Toggle active/inactive.
		if ( $item['active'] ) {
			$deact_url = wp_nonce_url(
				admin_url( "admin.php?page={$page}&action=deactivate&field_id={$id}&domain={$domain}" ),
				'oat_toggle_field_' . $id
			);
			$links[] = '<a href="' . esc_url( $deact_url ) . '" style="color:#a00;">Deactivate</a>';
		} else {
			$act_url = wp_nonce_url(
				admin_url( "admin.php?page={$page}&action=activate&field_id={$id}&domain={$domain}" ),
				'oat_toggle_field_' . $id
			);
			$links[] = '<a href="' . esc_url( $act_url ) . '" style="color:#46b450;">Activate</a>';
		}

		// Delete.
		$del_url = wp_nonce_url(
			admin_url( "admin.php?page={$page}&action=delete&field_id={$id}&domain={$domain}" ),
			'oat_delete_field_' . $id
		);
		$links[] = '<a href="' . esc_url( $del_url ) . '" class="oat-delete-link" style="color:#a00;" onclick="return confirm(\'Delete this field permanently?\');">Delete</a>';

		return implode( ' | ', $links );
	}

	/**
	 * Output filter dropdowns above the table.
	 *
	 * @param string $which
	 * @return void
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which || '' === $this->domain_slug ) {
			return;
		}

		$contexts        = OAT_Form_Field::get_contexts( $this->domain_slug );
		$current_context = $this->context;

		echo '<div class="alignleft actions">';

		echo '<select name="context">';
		echo '<option value="">All Contexts</option>';
		foreach ( $contexts as $ctx ) {
			$sel = ( $current_context === $ctx ) ? ' selected' : '';
			echo '<option value="' . esc_attr( $ctx ) . '"' . $sel . '>' . esc_html( ucfirst( $ctx ) ) . '</option>';
		}
		echo '</select>';

		submit_button( 'Filter', '', 'filter_action', false );
		echo '</div>';
	}

	/**
	 * Message when no items are found.
	 *
	 * @return void
	 */
	public function no_items() {
		if ( '' === $this->domain_slug ) {
			echo 'Select a domain above to manage its form fields.';
		} else {
			echo 'No form fields found for this domain.';
		}
	}
}
