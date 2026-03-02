<?php

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class OAT_Entry_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( array(
            'singular' => 'entry',
            'plural'   => 'entries',
            'ajax'     => false,
        ) );
    }

    /**
     * @return array
     */
    public function get_columns() {
        return array(
            'id'            => 'ID',
            'domain'        => 'Domain',
            'status'        => 'Status',
            'current_step'  => 'Step',
            'originator_id' => 'Submitted By',
            'chronicle_slug' => 'Chronicle',
            'created_at'    => 'Date',
        );
    }

    /**
     * @return array
     */
    public function get_sortable_columns() {
        return array(
            'id'         => array( 'id', false ),
            'domain'     => array( 'domain', false ),
            'status'     => array( 'status', false ),
            'created_at' => array( 'created_at', true ),
        );
    }

    /**
     * @return void
     */
    public function prepare_items() {
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $offset = ( $current_page - 1 ) * $per_page;

        $args = array(
            'per_page' => $per_page,
            'offset'   => $offset,
        );

        // Filters.
        if ( ! empty( $_GET['domain'] ) ) {
            $args['domain'] = sanitize_text_field( $_GET['domain'] );
        }
        if ( ! empty( $_GET['status'] ) ) {
            $args['status'] = sanitize_text_field( $_GET['status'] );
        }

        // Sorting — whitelist allowed columns to prevent SQL injection.
        $allowed_orderby = array( 'id', 'domain', 'status', 'created_at' );
        if ( ! empty( $_GET['orderby'] ) && in_array( $_GET['orderby'], $allowed_orderby, true ) ) {
            $args['orderby'] = $_GET['orderby'];
        }
        if ( ! empty( $_GET['order'] ) && in_array( strtoupper( $_GET['order'] ), array( 'ASC', 'DESC' ), true ) ) {
            $args['order'] = strtoupper( $_GET['order'] );
        }

        $this->items = OAT_Entry::all( $args );

        $total = OAT_Entry::count( $args );

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
    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'id':
                $url = admin_url( 'admin.php?page=owc-oat-entry&entry_id=' . $item->id );
                $out = '<a href="' . esc_url( $url ) . '"><strong>#' . esc_html( $item->id ) . '</strong></a>';

                // Delete action (D-030, 7.9b): WP admin only, entry never reached coord/archivist.
                if ( OAT_Entry::can_delete( $item ) ) {
                    $delete_url = wp_nonce_url(
                        admin_url( 'admin.php?page=oat-entries&action=delete&entry_id=' . $item->id ),
                        'oat_delete_entry_' . $item->id
                    );
                    $out .= '<div class="row-actions"><span class="delete"><a href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'Delete this entry and all related data? This cannot be undone.\');">Delete</a></span></div>';
                }

                return $out;
            case 'domain':
                $label = OAT_Domain_Registry::get_label( $item->domain );
                return '' !== $label ? esc_html( $label ) : esc_html( $item->domain );
            case 'status':
                return '<span class="oat-status oat-status-' . esc_attr( $item->status ) . '">' . esc_html( ucfirst( str_replace( '_', ' ', $item->status ) ) ) . '</span>';
            case 'current_step':
                return esc_html( str_replace( '_', ' ', $item->current_step ) );
            case 'originator_id':
                $user = get_userdata( $item->originator_id );
                return $user ? esc_html( $user->display_name ) : '#' . $item->originator_id;
            case 'chronicle_slug':
                return esc_html( $item->chronicle_slug );
            case 'created_at':
                return esc_html( wp_date( 'Y-m-d H:i', $item->created_at ) );
            default:
                return '';
        }
    }

    /**
     * Output filter dropdowns above the table.
     *
     * @param string $which
     * @return void
     */
    protected function extra_tablenav( $which ) {
        if ( $which !== 'top' ) {
            return;
        }

        $domains = OAT_Domain_Registry::get_all();
        $current_domain = isset( $_GET['domain'] ) ? sanitize_text_field( $_GET['domain'] ) : '';
        $current_status = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';

        echo '<div class="alignleft actions">';

        // Domain filter.
        echo '<select name="domain">';
        echo '<option value="">All Domains</option>';
        foreach ( $domains as $d ) {
            $selected = $current_domain === $d['slug'] ? ' selected' : '';
            echo '<option value="' . esc_attr( $d['slug'] ) . '"' . $selected . '>' . esc_html( $d['label'] ) . '</option>';
        }
        echo '</select>';

        // Status filter.
        $statuses = array( 'pending', 'held', 'approved', 'denied', 'cancelled', 'auto_approved', 'auto_denied' );
        echo '<select name="status">';
        echo '<option value="">All Statuses</option>';
        foreach ( $statuses as $s ) {
            $selected = $current_status === $s ? ' selected' : '';
            echo '<option value="' . esc_attr( $s ) . '"' . $selected . '>' . esc_html( ucfirst( str_replace( '_', ' ', $s ) ) ) . '</option>';
        }
        echo '</select>';

        submit_button( 'Filter', '', 'filter_action', false );
        echo '</div>';
    }
}
