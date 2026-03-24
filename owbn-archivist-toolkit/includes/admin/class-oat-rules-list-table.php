<?php

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class OAT_Rules_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( array(
            'singular' => 'rule',
            'plural'   => 'rules',
            'ajax'     => false,
        ) );
    }

    /**
     * @return array
     */
    public function get_columns() {
        return array(
            'id'                      => 'ID',
            'section_ref'             => 'Ref',
            'genre'                   => 'Genre',
            'category'                => 'Category',
            'subcategory'             => 'Subcategory',
            'condition_name'          => 'Condition',
            'pc_level'                => 'PC Level',
            'npc_level'               => 'NPC Level',
            'controlling_coordinator' => 'Coordinator',
            'elevation'               => 'Elevated',
            'active'                  => 'Active',
        );
    }

    /**
     * @return array
     */
    public function get_sortable_columns() {
        return array(
            'id'    => array( 'id', false ),
            'genre' => array( 'genre', false ),
        );
    }

    /**
     * @return void
     */
    public function prepare_items() {
        $per_page = 50;
        $current_page = $this->get_pagenum();
        $offset = ( $current_page - 1 ) * $per_page;

        $args = array(
            'per_page' => $per_page,
            'offset'   => $offset,
        );

        if ( ! empty( $_GET['genre'] ) ) {
            $args['genre'] = sanitize_text_field( $_GET['genre'] );
        }
        if ( isset( $_GET['active'] ) && $_GET['active'] !== '' ) {
            $args['active'] = absint( $_GET['active'] );
        }
        if ( ! empty( $_GET['s'] ) ) {
            $args['search'] = sanitize_text_field( $_GET['s'] );
        }

        $this->items = OAT_Regulation_Rule::all( $args );

        $total = OAT_Regulation_Rule::count( $args );

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
                return esc_html( $item->id );
            case 'section_ref':
            case 'genre':
            case 'category':
            case 'subcategory':
            case 'condition_name':
            case 'controlling_coordinator':
                return esc_html( isset( $item->$column_name ) ? $item->$column_name : '' );
            case 'pc_level':
            case 'npc_level':
                $val = isset( $item->$column_name ) ? $item->$column_name : '';
                return $val ? esc_html( ucfirst( str_replace( '_', ' ', $val ) ) ) : '—';
            case 'elevation':
                return (int) $item->elevation ? '<strong>Yes</strong>' : 'No';
            case 'active':
                return (int) $item->active ? 'Active' : '<em>Inactive</em>';
            default:
                return '';
        }
    }

    /**
     * Row actions for each rule.
     *
     * @param object $item
     * @return string
     */
    public function column_id( $item ) {
        $actions = array();

        if ( (int) $item->active ) {
            $actions['deactivate'] = sprintf(
                '<form method="post" style="display:inline">' .
                wp_nonce_field( 'oat_deactivate_rule', '_wpnonce', true, false ) .
                '<input type="hidden" name="rule_id" value="%d">' .
                '<button type="submit" name="oat_deactivate_rule" value="1" class="button-link">Deactivate</button>' .
                '</form>',
                $item->id
            );
        }

        return esc_html( $item->id ) . $this->row_actions( $actions );
    }
    protected function extra_tablenav( $which ) {
        if ( $which !== 'top' ) {
            return;
        }

        $current_genre = isset( $_GET['genre'] ) ? sanitize_text_field( $_GET['genre'] ) : '';
        $current_active = isset( $_GET['active'] ) ? $_GET['active'] : '';

        echo '<div class="alignleft actions">';

        // Genre filter.
        echo '<select name="genre">';
        echo '<option value="">All Genres</option>';
        $genres = OAT_Regulation_Rule::get_genres();
        foreach ( $genres as $g ) {
            $selected = $current_genre === $g ? ' selected' : '';
            echo '<option value="' . esc_attr( $g ) . '"' . $selected . '>' . esc_html( $g ) . '</option>';
        }
        echo '</select>';

        // Active filter.
        echo '<select name="active">';
        echo '<option value="">All</option>';
        $selected_active = $current_active === '1' ? ' selected' : '';
        $selected_inactive = $current_active === '0' ? ' selected' : '';
        echo '<option value="1"' . $selected_active . '>Active Only</option>';
        echo '<option value="0"' . $selected_inactive . '>Inactive Only</option>';
        echo '</select>';

        submit_button( 'Filter', '', 'filter_action', false );
        echo '</div>';
    }
}
