<?php

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class OAT_Forms_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct( [
			'singular' => 'form',
			'plural'   => 'forms',
			'ajax'     => false,
		] );
	}

	public function get_columns() {
		return [
			'slug'       => 'Slug',
			'label'      => 'Label',
			'fields'     => 'Fields',
			'domains'    => 'Domains',
			'sort_order' => 'Order',
			'active'     => 'Active',
		];
	}

	public function get_sortable_columns() {
		return [
			'slug'       => [ 'slug', false ],
			'label'      => [ 'label', false ],
			'sort_order' => [ 'sort_order', true ],
		];
	}

	public function prepare_items() {
		$this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];

		$this->items = OAT_Form::get_all( true );

		if ( ! is_array( $this->items ) ) {
			$this->items = [];
		}

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

	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'slug':
				$url = admin_url( 'admin.php?page=oat-forms&action=edit&form_id=' . $item->id );
				return '<a href="' . esc_url( $url ) . '"><strong>' . esc_html( $item->slug ) . '</strong></a>';

			case 'label':
				return esc_html( $item->label );

			case 'fields':
				$count = OAT_Form::count_fields( $item->slug );
				$url   = admin_url( 'admin.php?page=oat-form-fields&form_slug=' . urlencode( $item->slug ) );
				return '<a href="' . esc_url( $url ) . '">' . (int) $count . '</a>';

			case 'domains':
				$count = OAT_Form::count_domains( $item->id );
				return (int) $count;

			case 'sort_order':
				return (int) $item->sort_order;

			case 'active':
				if ( $item->active ) {
					$url = wp_nonce_url(
						admin_url( 'admin.php?page=oat-forms&action=deactivate&form_id=' . $item->id ),
						'oat_toggle_form_' . $item->id
					);
					return '<span style="color:green;">Active</span> | <a href="' . esc_url( $url ) . '">Deactivate</a>';
				}
				$url = wp_nonce_url(
					admin_url( 'admin.php?page=oat-forms&action=activate&form_id=' . $item->id ),
					'oat_toggle_form_' . $item->id
				);
				return '<span style="color:#999;">Inactive</span> | <a href="' . esc_url( $url ) . '">Activate</a>';

			default:
				return isset( $item->$column_name ) ? esc_html( $item->$column_name ) : '';
		}
	}
}
