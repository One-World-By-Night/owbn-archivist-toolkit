<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap">
    <h1>Entry #<?php echo esc_html( $entry->id ); ?> — <?php echo $domain ? esc_html( $domain->get_label() ) : esc_html( $entry->domain ); ?></h1>

    <?php settings_errors( 'oat_entry' ); ?>

    <?php if ( isset( $_GET['created'] ) ) : ?>
        <div class="notice notice-success"><p>Entry created successfully.</p></div>
    <?php endif; ?>

    <!-- Entry Header -->
    <div class="oat-entry-header">
        <table class="form-table">
            <tr><th>Status</th><td><span class="oat-status oat-status-<?php echo esc_attr( $entry->status ); ?>"><?php echo esc_html( ucfirst( str_replace( '_', ' ', $entry->status ) ) ); ?></span></td></tr>
            <tr><th>Current Step</th><td><?php echo esc_html( str_replace( '_', ' ', $entry->current_step ) ); ?></td></tr>
            <tr><th>Originator</th><td><?php $orig = get_userdata( $entry->originator_id ); echo $orig ? esc_html( $orig->display_name ) : '#' . $entry->originator_id; ?></td></tr>
            <?php if ( $entry->chronicle_slug ) : ?>
                <tr><th>Chronicle</th><td><?php echo esc_html( $entry->chronicle_slug ); ?></td></tr>
            <?php endif; ?>
            <tr><th>Created</th><td><?php echo esc_html( wp_date( 'Y-m-d H:i', $entry->created_at ) ); ?></td></tr>
            <tr><th>Updated</th><td><?php echo esc_html( wp_date( 'Y-m-d H:i', $entry->updated_at ) ); ?></td></tr>
        </table>
    </div>

    <!-- Meta Summary -->
    <?php if ( ! empty( $meta ) ) : ?>
        <h2>Details</h2>
        <table class="widefat fixed striped">
            <tbody>
                <?php foreach ( $meta as $m ) : ?>
                    <tr>
                        <th style="width:200px"><?php echo esc_html( ucfirst( str_replace( '_', ' ', $m->meta_key ) ) ); ?></th>
                        <td><?php echo esc_html( $m->meta_value ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- Linked Rules -->
    <?php if ( ! empty( $rules ) ) : ?>
        <h2>Linked Regulation Rules</h2>
        <table class="widefat fixed striped">
            <thead><tr><th>ID</th><th>Genre</th><th>Category</th><th>Condition</th><th>PC Level</th><th>Elevated</th></tr></thead>
            <tbody>
                <?php foreach ( $rules as $r ) : ?>
                    <tr>
                        <td><?php echo esc_html( $r->id ); ?></td>
                        <td><?php echo esc_html( $r->genre ); ?></td>
                        <td><?php echo esc_html( $r->category ); ?></td>
                        <td><?php echo esc_html( $r->condition_name ); ?></td>
                        <td><?php echo esc_html( $r->pc_level ? ucfirst( str_replace( '_', ' ', $r->pc_level ) ) : '—' ); ?></td>
                        <td><?php echo (int) $r->elevation ? '<strong>Yes</strong>' : 'No'; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- Timer Info -->
    <?php if ( $timer ) : ?>
        <h2>Active Timer</h2>
        <table class="form-table">
            <tr><th>Auto Action</th><td><?php echo esc_html( $timer->auto_action ); ?></td></tr>
            <tr><th>Expires</th><td><?php echo esc_html( wp_date( 'Y-m-d H:i', $timer->expires_at ) ); ?></td></tr>
            <tr><th>Bumps</th><td><?php echo esc_html( $timer->bump_count . ' / ' . $timer->bump_required ); ?></td></tr>
            <tr><th>Status</th><td><?php echo esc_html( $timer->status ); ?></td></tr>
        </table>
    <?php endif; ?>

    <!-- Actions -->
    <?php if ( ! empty( $actions ) ) : ?>
        <h2>Actions</h2>
        <div class="oat-actions">
            <?php if ( $bbp_eligible ) : ?>
                <div class="oat-action-card oat-bbp-card">
                    <h3>Auto-Approve (BBP)</h3>
                    <p>This entry is eligible for Bump Bump Pass auto-approval.</p>
                    <form method="post">
                        <?php wp_nonce_field( 'oat_entry_action' ); ?>
                        <input type="hidden" name="oat_action" value="auto_approve">
                        <textarea name="oat_note" placeholder="Note (optional)" rows="2" class="large-text"></textarea>
                        <button type="submit" class="button button-primary">Invoke Auto-Approve</button>
                    </form>
                </div>
            <?php endif; ?>

            <?php
            $action_labels = array(
                'approve'          => 'Approve',
                'deny'             => 'Deny',
                'request_changes'  => 'Request Changes',
                'cancel'           => 'Cancel / Withdraw',
                'bump'             => 'Bump',
                'reassign'         => 'Reassign',
                'delegate'         => 'Delegate',
                'hold'             => 'Hold',
                'resume'           => 'Resume',
                'record'           => 'Record',
                'council_override' => 'Council Override',
                'timer_extend'     => 'Extend Timer',
            );
            ?>

            <?php foreach ( $actions as $action_type ) :
                if ( $action_type === 'auto_approve' ) continue;
                $label = isset( $action_labels[ $action_type ] ) ? $action_labels[ $action_type ] : ucfirst( $action_type );
            ?>
                <div class="oat-action-card">
                    <form method="post">
                        <?php wp_nonce_field( 'oat_entry_action' ); ?>
                        <input type="hidden" name="oat_action" value="<?php echo esc_attr( $action_type ); ?>">

                        <strong><?php echo esc_html( $label ); ?></strong>

                        <?php if ( $action_type === 'council_override' ) : ?>
                            <input type="text" name="vote_reference" placeholder="Vote reference (required)" class="regular-text" required>
                        <?php endif; ?>

                        <?php if ( $action_type === 'timer_extend' ) : ?>
                            <input type="number" name="additional_seconds" placeholder="Seconds to extend" class="small-text" required>
                        <?php endif; ?>

                        <?php if ( $action_type === 'reassign' ) : ?>
                            <input type="number" name="new_user_id" placeholder="New user ID" class="small-text" required>
                        <?php endif; ?>

                        <?php if ( $action_type === 'delegate' ) : ?>
                            <input type="number" name="delegate_user_id" placeholder="Delegate user ID" class="small-text" required>
                        <?php endif; ?>

                        <?php $note_required = ( $action_type !== 'bump' ); ?>
                        <textarea name="oat_note" placeholder="Note (<?php echo $note_required ? 'required' : 'optional'; ?>)" rows="2" class="large-text" <?php echo $note_required ? 'required' : ''; ?>></textarea>
                        <button type="submit" class="button"><?php echo esc_html( $label ); ?></button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Assignees -->
    <?php if ( ! empty( $assignees ) ) : ?>
        <h2>Assignees</h2>
        <table class="widefat fixed striped">
            <thead><tr><th>User</th><th>Step</th><th>Status</th><th>Assigned</th></tr></thead>
            <tbody>
                <?php foreach ( $assignees as $a ) :
                    $auser = get_userdata( $a->user_id );
                ?>
                    <tr>
                        <td><?php echo $auser ? esc_html( $auser->display_name ) : '#' . $a->user_id; ?></td>
                        <td><?php echo esc_html( str_replace( '_', ' ', $a->step ) ); ?></td>
                        <td><?php echo esc_html( ucfirst( $a->status ) ); ?></td>
                        <td><?php echo esc_html( wp_date( 'Y-m-d H:i', $a->assigned_at ) ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- Watchers -->
    <?php if ( ! empty( $watchers ) ) : ?>
        <h2>Watchers</h2>
        <ul>
            <?php foreach ( $watchers as $w ) :
                $wuser = get_userdata( $w->user_id );
            ?>
                <li><?php echo $wuser ? esc_html( $wuser->display_name ) : '#' . $w->user_id; ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <!-- Timeline -->
    <?php if ( ! empty( $timeline ) ) : ?>
        <h2>Timeline</h2>
        <div class="oat-timeline">
            <?php foreach ( $timeline as $event ) :
                $actor = $event->user_id ? get_userdata( $event->user_id ) : null;
            ?>
                <div class="oat-timeline-event oat-tier-<?php echo esc_attr( $event->visibility_tier ); ?>">
                    <div class="oat-timeline-meta">
                        <span class="oat-timeline-date"><?php echo esc_html( wp_date( 'Y-m-d H:i', $event->created_at ) ); ?></span>
                        <span class="oat-timeline-action"><?php echo esc_html( ucfirst( str_replace( '_', ' ', $event->action_type ) ) ); ?></span>
                        <?php if ( $actor ) : ?>
                            <span class="oat-timeline-actor">by <?php echo esc_html( $actor->display_name ); ?></span>
                        <?php else : ?>
                            <span class="oat-timeline-actor">System</span>
                        <?php endif; ?>
                        <span class="oat-timeline-tier">[<?php echo esc_html( $event->visibility_tier ); ?>]</span>
                    </div>
                    <?php if ( $event->note ) : ?>
                        <div class="oat-timeline-note"><?php echo esc_html( $event->note ); ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
