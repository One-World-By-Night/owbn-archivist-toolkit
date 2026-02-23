<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap">
	<h1><?php echo esc_html( $title ); ?></h1>

	<?php settings_errors( 'oat_workflow_steps' ); ?>

	<p>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=oat-workflow-steps&domain=' . urlencode( $step['domain_slug'] ) ) ); ?>">&larr; Back to Steps</a>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=oat-workflow-steps' ) ); ?>">
		<?php wp_nonce_field( 'oat_save_step' ); ?>
		<input type="hidden" name="row_id" value="<?php echo (int) $step['id']; ?>">
		<input type="hidden" name="domain_slug" value="<?php echo esc_attr( $step['domain_slug'] ); ?>">

		<table class="form-table">
			<!-- Domain (read-only) -->
			<tr>
				<th>Domain</th>
				<td>
					<code><?php echo esc_html( $step['domain_slug'] ); ?></code>
				</td>
			</tr>

			<!-- Step ID -->
			<tr>
				<th><label for="step_id">Step ID</label></th>
				<td>
					<?php if ( $is_edit ) : ?>
						<input type="text" id="step_id" value="<?php echo esc_attr( $step['step_id'] ); ?>" class="regular-text" readonly disabled>
						<input type="hidden" name="step_id" value="<?php echo esc_attr( $step['step_id'] ); ?>">
						<p class="description">Step ID cannot be changed after creation.</p>
					<?php else : ?>
						<input type="text" name="step_id" id="step_id" value="<?php echo esc_attr( $step['step_id'] ); ?>" class="regular-text" required pattern="[a-z0-9_]+" title="Lowercase letters, numbers, and underscores only.">
						<p class="description">Unique identifier within this domain (e.g., <code>staff_review</code>).</p>
					<?php endif; ?>
				</td>
			</tr>

			<!-- Label -->
			<tr>
				<th><label for="label">Label</label></th>
				<td>
					<input type="text" name="label" id="label" value="<?php echo esc_attr( $step['label'] ); ?>" class="regular-text" required>
				</td>
			</tr>

			<!-- Sort Order -->
			<tr>
				<th><label for="sort_order">Sort Order</label></th>
				<td>
					<input type="number" name="sort_order" id="sort_order" value="<?php echo (int) $step['sort_order']; ?>" min="0" step="10" class="small-text">
					<p class="description">Steps execute in ascending order.</p>
				</td>
			</tr>

			<!-- Assignee Role -->
			<tr>
				<th><label for="assignee_role">Assignee Role</label></th>
				<td>
					<input type="text" name="assignee_role" id="assignee_role" value="<?php echo esc_attr( $step['assignee_role'] ); ?>" class="regular-text">
					<p class="description">accessSchema role path pattern. Use <code>{chronicle_slug}</code> and <code>{coordinator_genre}</code> as placeholders.<br>Example: <code>Chronicle/{chronicle_slug}/HST</code></p>
				</td>
			</tr>

			<!-- Visibility Tier -->
			<tr>
				<th><label for="visibility_tier">Visibility Tier</label></th>
				<td>
					<select name="visibility_tier" id="visibility_tier">
						<?php foreach ( $tiers as $tier ) : ?>
							<option value="<?php echo esc_attr( $tier ); ?>"<?php selected( $step['visibility_tier'], $tier ); ?>>
								<?php echo esc_html( ucfirst( $tier ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description">Controls timeline entry visibility for this step.</p>
				</td>
			</tr>

			<!-- Routing: On Approve -->
			<tr>
				<th><label for="on_approve">On Approve</label></th>
				<td>
					<select name="on_approve" id="on_approve">
						<option value="">-- Terminal (end workflow) --</option>
						<?php foreach ( $existing_steps as $s ) : ?>
							<option value="<?php echo esc_attr( $s->step_id ); ?>"<?php selected( $step['on_approve'], $s->step_id ); ?>>
								<?php echo esc_html( $s->step_id . ' — ' . $s->label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description">Next step when this step is approved. Leave empty for terminal approval.</p>
				</td>
			</tr>

			<!-- Routing: On Deny -->
			<tr>
				<th><label for="on_deny">On Deny</label></th>
				<td>
					<select name="on_deny" id="on_deny">
						<option value="">-- Terminal (deny entry) --</option>
						<?php foreach ( $existing_steps as $s ) : ?>
							<option value="<?php echo esc_attr( $s->step_id ); ?>"<?php selected( $step['on_deny'], $s->step_id ); ?>>
								<?php echo esc_html( $s->step_id . ' — ' . $s->label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>

			<!-- Routing: On Request Changes -->
			<tr>
				<th><label for="on_request_changes">On Request Changes</label></th>
				<td>
					<select name="on_request_changes" id="on_request_changes">
						<option value="">-- None --</option>
						<?php foreach ( $existing_steps as $s ) : ?>
							<option value="<?php echo esc_attr( $s->step_id ); ?>"<?php selected( $step['on_request_changes'], $s->step_id ); ?>>
								<?php echo esc_html( $s->step_id . ' — ' . $s->label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description">Step to route back to when changes are requested (always one step back).</p>
				</td>
			</tr>

			<!-- Multi-Approve -->
			<tr>
				<th>Multi-Approve</th>
				<td>
					<label>
						<input type="checkbox" name="multi_approve" value="1"<?php checked( $step['multi_approve'] ); ?>>
						All assignees must approve before advancing (D-019).
					</label>
				</td>
			</tr>

			<!-- Active -->
			<tr>
				<th>Active</th>
				<td>
					<label>
						<input type="checkbox" name="active" value="1"<?php checked( $step['active'] ); ?>>
						Inactive steps are skipped during routing.
					</label>
				</td>
			</tr>

			<!-- Timer JSON -->
			<tr>
				<th><label for="timer_json">Timer Config (JSON)</label></th>
				<td>
					<textarea name="timer_json" id="timer_json" rows="4" class="large-text code"><?php echo esc_textarea( $step['timer_json'] ); ?></textarea>
					<p class="description">Timer config. Example: <code>{"duration": 1209600, "auto_action": "auto_approve", "bump_required": 2}</code><br>
					<code>duration</code>: seconds (1209600 = 14 days). <code>auto_action</code>: <code>auto_approve</code> or <code>auto_deny</code>. <code>bump_required</code>: number of bumps needed.</p>
				</td>
			</tr>

			<!-- Condition JSON -->
			<tr>
				<th><label for="condition_json">Condition (JSON)</label></th>
				<td>
					<textarea name="condition_json" id="condition_json" rows="3" class="large-text code"><?php echo esc_textarea( $step['condition_json'] ); ?></textarea>
					<p class="description">Conditional routing — step is skipped (auto-advances to on_approve target) if condition evaluates to false.<br>
					Example: <code>{"meta_key": "requires_coord", "operator": "=", "value": "1"}</code><br>
					Operators: <code>=</code>, <code>!=</code>, <code>&gt;</code>, <code>&lt;</code>, <code>in</code></p>
				</td>
			</tr>
		</table>

		<?php submit_button( $is_edit ? 'Update Step' : 'Add Step', 'primary', 'oat_save_step' ); ?>
	</form>
</div>
