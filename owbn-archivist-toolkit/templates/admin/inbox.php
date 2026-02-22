<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap">
    <h1>Inbox</h1>

    <form method="get">
        <input type="hidden" name="page" value="oat-inbox">
        <div class="alignleft actions">
            <select name="domain">
                <option value="">All Domains</option>
                <?php foreach ( $domains as $d ) : ?>
                    <option value="<?php echo esc_attr( $d->get_slug() ); ?>" <?php selected( $domain_filter, $d->get_slug() ); ?>><?php echo esc_html( $d->get_label() ); ?></option>
                <?php endforeach; ?>
            </select>
            <?php submit_button( 'Filter', '', 'filter_action', false ); ?>
        </div>
    </form>

    <?php if ( empty( $assignments ) ) : ?>
        <p>No pending items in your inbox.</p>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Entry</th>
                    <th>Domain</th>
                    <th>Status</th>
                    <th>Step</th>
                    <th>Assigned</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $assignments as $item ) : ?>
                    <?php $url = admin_url( 'admin.php?page=oat-entry&entry_id=' . $item->entry_id ); ?>
                    <tr>
                        <td><a href="<?php echo esc_url( $url ); ?>"><strong>#<?php echo esc_html( $item->entry_id ); ?></strong></a></td>
                        <td>
                            <?php
                            $d = OAT_Domain_Registry::get( isset( $item->domain ) ? $item->domain : '' );
                            echo $d ? esc_html( $d->get_label() ) : esc_html( isset( $item->domain ) ? $item->domain : '' );
                            ?>
                        </td>
                        <td><span class="oat-status oat-status-<?php echo esc_attr( isset( $item->entry_status ) ? $item->entry_status : '' ); ?>"><?php echo esc_html( ucfirst( str_replace( '_', ' ', isset( $item->entry_status ) ? $item->entry_status : '' ) ) ); ?></span></td>
                        <td><?php echo esc_html( str_replace( '_', ' ', isset( $item->current_step ) ? $item->current_step : $item->step ) ); ?></td>
                        <td><?php echo esc_html( wp_date( 'Y-m-d H:i', $item->assigned_at ) ); ?></td>
                        <td><a href="<?php echo esc_url( $url ); ?>" class="button button-small">Review</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if ( ! empty( $watched ) ) : ?>
        <h2>Watched Entries</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Entry</th>
                    <th>Since</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $watched as $w ) : ?>
                    <?php $url = admin_url( 'admin.php?page=oat-entry&entry_id=' . $w->entry_id ); ?>
                    <tr>
                        <td><a href="<?php echo esc_url( $url ); ?>">#<?php echo esc_html( $w->entry_id ); ?></a></td>
                        <td><?php echo esc_html( wp_date( 'Y-m-d H:i', $w->added_at ) ); ?></td>
                        <td><a href="<?php echo esc_url( $url ); ?>" class="button button-small">View</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
