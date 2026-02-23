<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap">
	<h1><?php echo esc_html( $title ); ?></h1>

	<?php settings_errors( 'oat_form_fields' ); ?>

	<p>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=oat-form-fields&domain=' . urlencode( $field['domain_slug'] ) ) ); ?>">&larr; Back to Fields</a>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=oat-form-fields' ) ); ?>">
		<?php wp_nonce_field( 'oat_save_field' ); ?>
		<input type="hidden" name="field_id" value="<?php echo (int) $field['id']; ?>">

		<table class="form-table">
			<!-- Domain -->
			<tr>
				<th><label for="domain_slug">Domain</label></th>
				<td>
					<?php if ( $is_edit ) : ?>
						<input type="text" id="domain_slug" value="<?php echo esc_attr( $field['domain_slug'] ); ?>" class="regular-text" readonly disabled>
						<input type="hidden" name="domain_slug" value="<?php echo esc_attr( $field['domain_slug'] ); ?>">
					<?php else : ?>
						<select name="domain_slug" id="domain_slug" required>
							<?php foreach ( $domains as $d ) : ?>
								<option value="<?php echo esc_attr( $d['slug'] ); ?>"<?php selected( $field['domain_slug'], $d['slug'] ); ?>>
									<?php echo esc_html( $d['label'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					<?php endif; ?>
				</td>
			</tr>

			<!-- Context -->
			<tr>
				<th><label for="context">Context</label></th>
				<td>
					<select name="context" id="context" required>
						<?php foreach ( $contexts as $ctx ) : ?>
							<option value="<?php echo esc_attr( $ctx ); ?>"<?php selected( $field['context'], $ctx ); ?>>
								<?php echo esc_html( ucfirst( $ctx ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description">When this field appears: submit, review, escalate, or resolve.</p>
				</td>
			</tr>

			<!-- Field Key -->
			<tr>
				<th><label for="field_key">Field Key</label></th>
				<td>
					<input type="text" name="field_key" id="field_key" value="<?php echo esc_attr( $field['field_key'] ); ?>" class="regular-text" required pattern="[a-z0-9_]+" title="Lowercase letters, numbers, and underscores only.">
					<?php if ( $is_edit ) : ?>
						<p class="description">Changing the key may break existing data references.</p>
					<?php else : ?>
						<p class="description">Unique identifier. Lowercase, underscores only (e.g., <code>character_name</code>).</p>
					<?php endif; ?>
				</td>
			</tr>

			<!-- Field Type -->
			<tr>
				<th><label for="field_type">Type</label></th>
				<td>
					<select name="field_type" id="field_type" required>
						<?php foreach ( $field_types as $type_val => $type_label ) : ?>
							<option value="<?php echo esc_attr( $type_val ); ?>"<?php selected( $field['field_type'], $type_val ); ?>>
								<?php echo esc_html( $type_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>

			<!-- Label -->
			<tr>
				<th><label for="label">Label</label></th>
				<td>
					<input type="text" name="label" id="label" value="<?php echo esc_attr( $field['label'] ); ?>" class="regular-text" required>
				</td>
			</tr>

			<!-- Sort Order -->
			<tr>
				<th><label for="sort_order">Sort Order</label></th>
				<td>
					<input type="number" name="sort_order" id="sort_order" value="<?php echo (int) $field['sort_order']; ?>" min="0" step="10" class="small-text">
					<p class="description">Fields display in ascending order within their context.</p>
				</td>
			</tr>

			<!-- Required -->
			<tr>
				<th>Required</th>
				<td>
					<label>
						<input type="checkbox" name="required" value="1"<?php checked( $field['required'] ); ?>>
						This field must be filled in.
					</label>
				</td>
			</tr>

			<!-- Active -->
			<tr>
				<th>Active</th>
				<td>
					<label>
						<input type="checkbox" name="active" value="1"<?php checked( $field['active'] ); ?>>
						Inactive fields are hidden from forms but preserved in the database.
					</label>
				</td>
			</tr>

			<!-- Placeholder -->
			<tr>
				<th><label for="placeholder">Placeholder</label></th>
				<td>
					<input type="text" name="placeholder" id="placeholder" value="<?php echo esc_attr( $field['placeholder'] ); ?>" class="regular-text">
				</td>
			</tr>

			<!-- Help Text -->
			<tr>
				<th><label for="help_text">Help Text</label></th>
				<td>
					<textarea name="help_text" id="help_text" rows="3" class="large-text"><?php echo esc_textarea( $field['help_text'] ); ?></textarea>
					<p class="description">Shown below the field as guidance for the user.</p>
				</td>
			</tr>

			<!-- Default Value -->
			<tr>
				<th><label for="default_value">Default Value</label></th>
				<td>
					<input type="text" name="default_value" id="default_value" value="<?php echo esc_attr( $field['default_value'] ); ?>" class="regular-text">
				</td>
			</tr>

			<!-- Options JSON (for select/radio/checkbox) -->
			<tr>
				<th><label for="options_json">Options JSON</label></th>
				<td>
					<textarea name="options_json" id="options_json" rows="4" class="large-text code"><?php echo esc_textarea( $field['options_json'] ); ?></textarea>
					<p class="description">For select/radio/checkbox types. JSON object: <code>{"value": "Label", ...}</code></p>
				</td>
			</tr>

			<!-- Condition JSON -->
			<tr>
				<th><label for="condition_json">Condition JSON</label></th>
				<td>
					<textarea name="condition_json" id="condition_json" rows="3" class="large-text code"><?php echo esc_textarea( $field['condition_json'] ); ?></textarea>
					<p class="description">Show this field only when condition is met. Example: <code>{"field_key": "action_type", "value": "transfer"}</code></p>
				</td>
			</tr>

			<!-- Attributes JSON -->
			<tr>
				<th><label for="attributes_json">Attributes JSON</label></th>
				<td>
					<textarea name="attributes_json" id="attributes_json" rows="4" class="large-text code"><?php echo esc_textarea( $field['attributes_json'] ); ?></textarea>
					<p class="description">Extra configuration. Examples: <code>{"rows": 6}</code>, <code>{"roles": ["HST"], "show_role": false}</code></p>
				</td>
			</tr>

			<!-- Validation JSON -->
			<tr>
				<th><label for="validation_json">Validation JSON</label></th>
				<td>
					<textarea name="validation_json" id="validation_json" rows="3" class="large-text code"><?php echo esc_textarea( $field['validation_json'] ); ?></textarea>
					<p class="description">Custom validation rules. Example: <code>{"min_length": 3, "max_length": 255}</code></p>
				</td>
			</tr>
		</table>

		<?php submit_button( $is_edit ? 'Update Field' : 'Add Field', 'primary', 'oat_save_field' ); ?>
	</form>
</div>
