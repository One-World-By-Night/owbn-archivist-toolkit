<?php
/**
 * OAT Submission Rules Admin Page.
 *
 * CRUD admin for the oat_rules table (rule_type = submission_check).
 */

defined( 'ABSPATH' ) || exit;

class OAT_Page_Submission_Rules {

    public static function render() {
        if ( ! OAT_Authorization::check( OAT_Constants::CAP_MANAGE_RULES ) ) {
            wp_die( __( 'You do not have permission to manage rules.', 'owbn-archivist-toolkit' ) );
        }

        // Handle GET actions (toggle, delete).
        self::handle_get_actions();

        // Handle POST (add/edit).
        if ( $_SERVER['REQUEST_METHOD'] === 'POST' && ! empty( $_POST['oat_rule_save'] ) ) {
            self::handle_save();
        }

        $action  = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : '';
        $rule_id = isset( $_GET['rule_id'] ) ? absint( $_GET['rule_id'] ) : 0;
        $editing = ( $action === 'edit' && $rule_id ) ? OAT_Rule::find( $rule_id ) : null;

        $list_table = new OAT_Rules_List_Table_Generic( 'submission_check' );
        $list_table->prepare_items();

        self::render_page( $list_table, $editing );
    }

    private static function handle_get_actions() {
        $action  = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : '';
        $rule_id = isset( $_GET['rule_id'] ) ? absint( $_GET['rule_id'] ) : 0;

        if ( $action === 'toggle' && $rule_id ) {
            check_admin_referer( 'oat_rule_toggle_' . $rule_id );
            OAT_Rule::toggle( $rule_id );
            wp_safe_redirect( admin_url( 'admin.php?page=oat-submission-rules&message=updated' ) );
            exit;
        }

        if ( $action === 'delete' && $rule_id ) {
            check_admin_referer( 'oat_rule_delete_' . $rule_id );
            OAT_Rule::delete( $rule_id );
            wp_safe_redirect( admin_url( 'admin.php?page=oat-submission-rules&message=deleted' ) );
            exit;
        }
    }

    private static function handle_save() {
        check_admin_referer( 'oat_rule_save' );

        $data = array(
            'rule_type'    => 'submission_check',
            'label'        => sanitize_text_field( $_POST['label'] ?? '' ),
            'domain'       => sanitize_text_field( $_POST['domain'] ?? '' ),
            'form_slug'    => sanitize_text_field( $_POST['form_slug'] ?? '' ),
            'check_source' => sanitize_text_field( $_POST['check_source'] ?? 'entry' ),
            'check_field'  => sanitize_text_field( $_POST['check_field'] ?? '' ),
            'operator'     => sanitize_text_field( $_POST['operator'] ?? 'equals' ),
            'check_value'  => sanitize_text_field( $_POST['check_value'] ?? '' ),
            'action'       => sanitize_text_field( $_POST['action_type'] ?? 'block' ),
            'message'      => sanitize_textarea_field( $_POST['message'] ?? '' ),
            'active'       => isset( $_POST['active'] ) ? 1 : 0,
            'priority'     => absint( $_POST['priority'] ?? 10 ),
        );

        $rule_id = absint( $_POST['rule_id'] ?? 0 );

        if ( $rule_id ) {
            OAT_Rule::update( $rule_id, $data );
            wp_safe_redirect( admin_url( 'admin.php?page=oat-submission-rules&message=updated' ) );
        } else {
            $result = OAT_Rule::create( $data );
            if ( is_wp_error( $result ) ) {
                add_settings_error( 'oat_rules', 'create_error', $result->get_error_message(), 'error' );
                return;
            }
            wp_safe_redirect( admin_url( 'admin.php?page=oat-submission-rules&message=created' ) );
        }
        exit;
    }

    private static function render_page( $list_table, $editing = null ) {
        $message = isset( $_GET['message'] ) ? sanitize_text_field( $_GET['message'] ) : '';

        $operators = array(
            'equals'     => __( 'Equals', 'owbn-archivist-toolkit' ),
            'not_equals' => __( 'Not Equals', 'owbn-archivist-toolkit' ),
            'in'         => __( 'In (comma-separated)', 'owbn-archivist-toolkit' ),
            'not_in'     => __( 'Not In (comma-separated)', 'owbn-archivist-toolkit' ),
            'empty'      => __( 'Is Empty', 'owbn-archivist-toolkit' ),
            'not_empty'  => __( 'Is Not Empty', 'owbn-archivist-toolkit' ),
        );

        $sources = array(
            'entry'     => __( 'Entry (meta/fields)', 'owbn-archivist-toolkit' ),
            'chronicle' => __( 'Chronicle (post meta)', 'owbn-archivist-toolkit' ),
            'character' => __( 'Character (registry)', 'owbn-archivist-toolkit' ),
        );

        $actions = array(
            'block'   => __( 'Block — prevent submission', 'owbn-archivist-toolkit' ),
            'warn'    => __( 'Warn — allow but show warning', 'owbn-archivist-toolkit' ),
            'require' => __( 'Require — must meet condition to proceed', 'owbn-archivist-toolkit' ),
        );

        $domains = array(
            ''                     => __( '— All Domains —', 'owbn-archivist-toolkit' ),
            'character_lifecycle'  => 'Character Lifecycle',
            'custom_content'       => 'Custom Content',
            'disciplinary_actions' => 'Disciplinary Actions',
            'chronicle_actions'    => 'Chronicle Actions',
            'player_actions'       => 'Player Actions',
            'governance_records'   => 'Governance Records',
            'binding_agreements'   => 'Binding Agreements',
        );

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Submission Rules', 'owbn-archivist-toolkit' ); ?></h1>
            <p class="description"><?php esc_html_e( 'Rules are evaluated when an entry is submitted. If a rule matches and its action is "Block", the submission is rejected with the message shown to the user.', 'owbn-archivist-toolkit' ); ?></p>

            <?php if ( $message ) : ?>
                <div class="notice notice-success is-dismissible"><p>
                    <?php echo esc_html( ucfirst( $message ) ) . '.'; ?>
                </p></div>
            <?php endif; ?>

            <?php settings_errors( 'oat_rules' ); ?>

            <h2><?php echo $editing ? esc_html__( 'Edit Rule', 'owbn-archivist-toolkit' ) : esc_html__( 'Add New Rule', 'owbn-archivist-toolkit' ); ?></h2>

            <form method="post">
                <?php wp_nonce_field( 'oat_rule_save' ); ?>
                <input type="hidden" name="rule_id" value="<?php echo $editing ? esc_attr( $editing->id ) : '0'; ?>">

                <table class="form-table">
                    <tr>
                        <th><label for="label"><?php esc_html_e( 'Label', 'owbn-archivist-toolkit' ); ?></label></th>
                        <td><input type="text" name="label" id="label" class="regular-text" required
                            value="<?php echo esc_attr( $editing->label ?? '' ); ?>"
                            placeholder="<?php esc_attr_e( 'e.g. Block transfers from probationary chronicles', 'owbn-archivist-toolkit' ); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="domain"><?php esc_html_e( 'Domain', 'owbn-archivist-toolkit' ); ?></label></th>
                        <td>
                            <select name="domain" id="domain">
                                <?php foreach ( $domains as $val => $lbl ) : ?>
                                    <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $editing->domain ?? '', $val ); ?>><?php echo esc_html( $lbl ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e( 'Leave blank to apply to all domains.', 'owbn-archivist-toolkit' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="form_slug"><?php esc_html_e( 'Form', 'owbn-archivist-toolkit' ); ?></label></th>
                        <td><input type="text" name="form_slug" id="form_slug" class="regular-text"
                            value="<?php echo esc_attr( $editing->form_slug ?? '' ); ?>"
                            placeholder="<?php esc_attr_e( 'e.g. transfer (blank = all forms)', 'owbn-archivist-toolkit' ); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="check_source"><?php esc_html_e( 'Check Source', 'owbn-archivist-toolkit' ); ?></label></th>
                        <td>
                            <select name="check_source" id="check_source">
                                <?php foreach ( $sources as $val => $lbl ) : ?>
                                    <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $editing->check_source ?? 'entry', $val ); ?>><?php echo esc_html( $lbl ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="check_field"><?php esc_html_e( 'Field Name', 'owbn-archivist-toolkit' ); ?></label></th>
                        <td><input type="text" name="check_field" id="check_field" class="regular-text" required
                            value="<?php echo esc_attr( $editing->check_field ?? '' ); ?>"
                            placeholder="<?php esc_attr_e( 'e.g. chronicle_probationary', 'owbn-archivist-toolkit' ); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="operator"><?php esc_html_e( 'Operator', 'owbn-archivist-toolkit' ); ?></label></th>
                        <td>
                            <select name="operator" id="operator">
                                <?php foreach ( $operators as $val => $lbl ) : ?>
                                    <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $editing->operator ?? 'equals', $val ); ?>><?php echo esc_html( $lbl ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="check_value"><?php esc_html_e( 'Value', 'owbn-archivist-toolkit' ); ?></label></th>
                        <td><input type="text" name="check_value" id="check_value" class="regular-text"
                            value="<?php echo esc_attr( $editing->check_value ?? '' ); ?>"
                            placeholder="<?php esc_attr_e( 'e.g. 1 or decommissioned,dissolved', 'owbn-archivist-toolkit' ); ?>">
                            <p class="description"><?php esc_html_e( 'For "empty"/"not_empty" operators, leave this blank.', 'owbn-archivist-toolkit' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="action_type"><?php esc_html_e( 'Action', 'owbn-archivist-toolkit' ); ?></label></th>
                        <td>
                            <select name="action_type" id="action_type">
                                <?php foreach ( $actions as $val => $lbl ) : ?>
                                    <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $editing->action ?? 'block', $val ); ?>><?php echo esc_html( $lbl ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="message"><?php esc_html_e( 'Message', 'owbn-archivist-toolkit' ); ?></label></th>
                        <td><textarea name="message" id="message" rows="3" class="large-text" required
                            placeholder="<?php esc_attr_e( 'Shown to the user when the rule blocks their submission.', 'owbn-archivist-toolkit' ); ?>"><?php echo esc_textarea( $editing->message ?? '' ); ?></textarea></td>
                    </tr>
                    <tr>
                        <th><label for="priority"><?php esc_html_e( 'Priority', 'owbn-archivist-toolkit' ); ?></label></th>
                        <td><input type="number" name="priority" id="priority" class="small-text"
                            value="<?php echo esc_attr( $editing->priority ?? 10 ); ?>" min="0" max="999">
                            <p class="description"><?php esc_html_e( 'Lower = evaluated first.', 'owbn-archivist-toolkit' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Active', 'owbn-archivist-toolkit' ); ?></th>
                        <td><label><input type="checkbox" name="active" value="1"
                            <?php checked( $editing ? $editing->active : 1 ); ?>>
                            <?php esc_html_e( 'Rule is active', 'owbn-archivist-toolkit' ); ?></label></td>
                    </tr>
                </table>

                <?php submit_button( $editing ? __( 'Update Rule', 'owbn-archivist-toolkit' ) : __( 'Add Rule', 'owbn-archivist-toolkit' ), 'primary', 'oat_rule_save' ); ?>
            </form>

            <hr>
            <h2><?php esc_html_e( 'Current Rules', 'owbn-archivist-toolkit' ); ?></h2>

            <form method="get">
                <input type="hidden" name="page" value="oat-submission-rules">
                <?php $list_table->search_box( __( 'Search', 'owbn-archivist-toolkit' ), 'rule-search' ); ?>
                <?php $list_table->display(); ?>
            </form>
        </div>
        <?php
    }
}
