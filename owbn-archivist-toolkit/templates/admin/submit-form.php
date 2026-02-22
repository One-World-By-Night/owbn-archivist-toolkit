<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap">
    <h1>New Submission</h1>

    <?php settings_errors( 'oat_submit' ); ?>

    <form method="post" id="oat-submit-form">
        <?php wp_nonce_field( 'oat_submit' ); ?>

        <table class="form-table">
            <tr>
                <th><label for="oat_domain">Domain</label></th>
                <td>
                    <select name="oat_domain" id="oat_domain" required>
                        <option value="">Select a domain...</option>
                        <?php foreach ( $domains as $d ) : ?>
                            <option value="<?php echo esc_attr( $d->get_slug() ); ?>" <?php selected( $selected_domain, $d->get_slug() ); ?>
                                data-form-fields="<?php echo esc_attr( wp_json_encode( $d->get_form_fields() ) ); ?>"
                            ><?php echo esc_html( $d->get_label() ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>

        <!-- Domain-specific fields rendered here by JS or server-side -->
        <div id="oat-domain-fields">
            <?php if ( $selected_domain ) :
                $sel_domain = OAT_Domain_Registry::get( $selected_domain );
                if ( $sel_domain ) :
                    $fields = $sel_domain->get_form_fields();
                    $submit_fields = isset( $fields['submit'] ) ? $fields['submit'] : array();
            ?>
                <table class="form-table">
                    <?php foreach ( $submit_fields as $field ) :
                        $cond_attrs = '';
                        if ( ! empty( $field['condition'] ) && is_array( $field['condition'] ) ) {
                            $cond_key = key( $field['condition'] );
                            $cond_val = $field['condition'][ $cond_key ];
                            $cond_attrs = ' data-condition-field="' . esc_attr( $cond_key ) . '" data-condition-value="' . esc_attr( $cond_val ) . '"';
                        }
                    ?>
                        <tr<?php echo $cond_attrs; ?>>
                            <th><label for="oat_meta_<?php echo esc_attr( $field['key'] ); ?>"><?php echo esc_html( $field['label'] ); ?></label></th>
                            <td>
                                <?php if ( $field['type'] === 'select' && ! empty( $field['options'] ) ) : ?>
                                    <select name="oat_meta_<?php echo esc_attr( $field['key'] ); ?>" id="oat_meta_<?php echo esc_attr( $field['key'] ); ?>" <?php echo ! empty( $field['required'] ) ? 'required' : ''; ?>>
                                        <option value="">Select...</option>
                                        <?php foreach ( $field['options'] as $val => $label ) : ?>
                                            <option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $label ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php elseif ( $field['type'] === 'textarea' ) : ?>
                                    <textarea name="oat_meta_<?php echo esc_attr( $field['key'] ); ?>" id="oat_meta_<?php echo esc_attr( $field['key'] ); ?>" rows="4" class="large-text" <?php echo ! empty( $field['required'] ) ? 'required' : ''; ?>></textarea>
                                <?php elseif ( $field['type'] === 'rule_picker' ) : ?>
                                    <div class="oat-rule-picker">
                                        <input type="text" id="oat_rule_search" placeholder="Search regulation rules..." class="regular-text" autocomplete="off">
                                        <div id="oat-selected-rules"></div>
                                    </div>
                                <?php elseif ( $field['type'] === 'number' ) : ?>
                                    <input type="number" name="oat_meta_<?php echo esc_attr( $field['key'] ); ?>" id="oat_meta_<?php echo esc_attr( $field['key'] ); ?>" class="small-text" <?php echo ! empty( $field['required'] ) ? 'required' : ''; ?>>
                                <?php else : ?>
                                    <input type="text" name="oat_meta_<?php echo esc_attr( $field['key'] ); ?>" id="oat_meta_<?php echo esc_attr( $field['key'] ); ?>" class="regular-text" <?php echo ! empty( $field['required'] ) ? 'required' : ''; ?>>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; endif; ?>
        </div>

        <table class="form-table">
            <tr>
                <th><label for="oat_chronicle_slug">Chronicle</label></th>
                <td><input type="text" name="oat_chronicle_slug" id="oat_chronicle_slug" class="regular-text" placeholder="Chronicle slug (optional)"></td>
            </tr>
            <tr>
                <th><label for="oat_coordinator_genre">Coordinator Genre</label></th>
                <td><input type="text" name="oat_coordinator_genre" id="oat_coordinator_genre" class="regular-text" placeholder="Genre (optional, auto-set from rules)"></td>
            </tr>
        </table>

        <?php submit_button( 'Submit Entry' ); ?>
    </form>
</div>
