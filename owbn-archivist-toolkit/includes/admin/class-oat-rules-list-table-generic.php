<?php
/**
 * OAT Rules List Table (Generic).
 *
 * WP_List_Table for the oat_rules table. Filterable by rule_type and domain.
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class OAT_Rules_List_Table_Generic extends WP_List_Table {

    private $rule_type;

    public function __construct( $rule_type = 'submission_check' ) {
        $this->rule_type = $rule_type;
        parent::__construct( array(
            'singular' => 'rule',
            'plural'   => 'rules',
            'ajax'     => false,
        ) );
    }

    public function get_columns() {
        return array(
            'id'           => 'ID',
            'label'        => __( 'Label', 'owbn-archivist-toolkit' ),
            'domain'       => __( 'Domain', 'owbn-archivist-toolkit' ),
            'form_slug'    => __( 'Form', 'owbn-archivist-toolkit' ),
            'check_source' => __( 'Source', 'owbn-archivist-toolkit' ),
            'check_field'  => __( 'Field', 'owbn-archivist-toolkit' ),
            'operator'     => __( 'Operator', 'owbn-archivist-toolkit' ),
            'check_value'  => __( 'Value', 'owbn-archivist-toolkit' ),
            'action'       => __( 'Action', 'owbn-archivist-toolkit' ),
            'priority'     => __( 'Priority', 'owbn-archivist-toolkit' ),
            'active'       => __( 'Active', 'owbn-archivist-toolkit' ),
            'actions'      => '',
        );
    }

    public function get_sortable_columns() {
        return array(
            'priority' => array( 'priority', true ),
            'domain'   => array( 'domain', false ),
            'label'    => array( 'label', false ),
        );
    }

    public function prepare_items() {
        $per_page = 25;
        $page     = $this->get_pagenum();
        $args     = array(
            'rule_type' => $this->rule_type,
            'per_page'  => $per_page,
            'offset'    => ( $page - 1 ) * $per_page,
        );

        if ( isset( $_GET['domain'] ) && $_GET['domain'] !== '' ) {
            $args['domain'] = sanitize_text_field( $_GET['domain'] );
        }
        if ( isset( $_GET['filter_active'] ) && $_GET['filter_active'] !== '' ) {
            $args['active'] = (int) $_GET['filter_active'];
        }
        if ( isset( $_GET['s'] ) && $_GET['s'] !== '' ) {
            $args['search'] = sanitize_text_field( $_GET['s'] );
        }

        $this->items = OAT_Rule::all( $args );

        $total = OAT_Rule::count( $args );
        $this->set_pagination_args( array(
            'total_items' => $total,
            'per_page'    => $per_page,
        ) );
    }

    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'active':
                return $item->active ? '<span style="color:#080;">&#9679; Yes</span>' : '<span style="color:#999;">&#9675; No</span>';
            case 'action':
                $colors = array( 'block' => '#d63638', 'warn' => '#dba617', 'require' => '#2271b1' );
                $color  = $colors[ $item->action ] ?? '#666';
                return '<span style="color:' . $color . ';font-weight:600;">' . esc_html( ucfirst( $item->action ) ) . '</span>';
            case 'domain':
                return $item->domain ? esc_html( $item->domain ) : '<em>all</em>';
            case 'form_slug':
                return $item->form_slug ? esc_html( $item->form_slug ) : '<em>all</em>';
            case 'actions':
                $edit_url   = admin_url( 'admin.php?page=oat-submission-rules&action=edit&rule_id=' . $item->id );
                $toggle_url = wp_nonce_url( admin_url( 'admin.php?page=oat-submission-rules&action=toggle&rule_id=' . $item->id ), 'oat_rule_toggle_' . $item->id );
                $delete_url = wp_nonce_url( admin_url( 'admin.php?page=oat-submission-rules&action=delete&rule_id=' . $item->id ), 'oat_rule_delete_' . $item->id );
                $links  = '<a href="' . esc_url( $edit_url ) . '">' . __( 'Edit', 'owbn-archivist-toolkit' ) . '</a>';
                $links .= ' | <a href="' . esc_url( $toggle_url ) . '">' . ( $item->active ? __( 'Deactivate', 'owbn-archivist-toolkit' ) : __( 'Activate', 'owbn-archivist-toolkit' ) ) . '</a>';
                $links .= ' | <a href="' . esc_url( $delete_url ) . '" style="color:#a00;" onclick="return confirm(\'Delete this rule?\');">' . __( 'Delete', 'owbn-archivist-toolkit' ) . '</a>';
                return $links;
            default:
                return esc_html( $item->$column_name ?? '' );
        }
    }

    protected function extra_tablenav( $which ) {
        if ( 'top' !== $which ) return;
        $current_domain = isset( $_GET['domain'] ) ? sanitize_text_field( $_GET['domain'] ) : '';
        $domains = array( '' => __( 'All Domains', 'owbn-archivist-toolkit' ), 'character_lifecycle' => 'Character Lifecycle', 'custom_content' => 'Custom Content', 'disciplinary_actions' => 'Disciplinary Actions' );
        echo '<div class="alignleft actions">';
        echo '<select name="domain">';
        foreach ( $domains as $val => $label ) {
            echo '<option value="' . esc_attr( $val ) . '" ' . selected( $current_domain, $val, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
        submit_button( __( 'Filter', 'owbn-archivist-toolkit' ), '', 'filter_action', false );
        echo '</div>';
    }
}
