<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap">
	<h1><?php echo esc_html( $title ); ?></h1>

	<?php settings_errors( 'oat_form_fields' ); ?>

	<p>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=oat-form-fields&domain=' . urlencode( $field['domain_slug'] ) ) ); ?>">&larr; Back to Fields</a>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=oat-form-fields' ) ); ?>" enctype="multipart/form-data">
		<?php wp_nonce_field( 'oat_save_field' ); ?>
		<input type="hidden" name="field_id" value="<?php echo (int) $field['id']; ?>">

		<table class="form-table">
			<!-- Domain -->
			<tr>
				<th><label for="domain_slug"><?php esc_html_e( 'Domain', 'owbn-archivist-toolkit' ); ?></label></th>
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
				<th><label for="context"><?php esc_html_e( 'Context', 'owbn-archivist-toolkit' ); ?></label></th>
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
				<th><label for="field_key"><?php esc_html_e( 'Field Key', 'owbn-archivist-toolkit' ); ?></label></th>
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
				<th><label for="field_type"><?php esc_html_e( 'Type', 'owbn-archivist-toolkit' ); ?></label></th>
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
				<th><label for="label"><?php esc_html_e( 'Label', 'owbn-archivist-toolkit' ); ?></label></th>
				<td>
					<input type="text" name="label" id="label" value="<?php echo esc_attr( $field['label'] ); ?>" class="regular-text" required>
				</td>
			</tr>

			<!-- Sort Order -->
			<tr>
				<th><label for="sort_order"><?php esc_html_e( 'Sort Order', 'owbn-archivist-toolkit' ); ?></label></th>
				<td>
					<input type="number" name="sort_order" id="sort_order" value="<?php echo (int) $field['sort_order']; ?>" min="0" step="10" class="small-text">
					<p class="description">Fields display in ascending order within their context.</p>
				</td>
			</tr>

			<!-- Required -->
			<tr>
				<th><?php esc_html_e( 'Required', 'owbn-archivist-toolkit' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="required" value="1"<?php checked( $field['required'] ); ?>>
						This field must be filled in.
					</label>
				</td>
			</tr>

			<!-- Active -->
			<tr>
				<th><?php esc_html_e( 'Active', 'owbn-archivist-toolkit' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="active" value="1"<?php checked( $field['active'] ); ?>>
						Inactive fields are hidden from forms but preserved in the database.
					</label>
				</td>
			</tr>

			<!-- Public Registry -->
			<tr>
				<th><?php esc_html_e( 'Public Registry', 'owbn-archivist-toolkit' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="public_registry" value="1"<?php checked( ! empty( $field['public_registry'] ) ); ?>>
						Show in public registry views (e.g., Fame lists). Visible to anyone who can see the character.
					</label>
				</td>
			</tr>

			<!-- Placeholder -->
			<tr>
				<th><label for="placeholder"><?php esc_html_e( 'Placeholder', 'owbn-archivist-toolkit' ); ?></label></th>
				<td>
					<input type="text" name="placeholder" id="placeholder" value="<?php echo esc_attr( $field['placeholder'] ); ?>" class="regular-text">
				</td>
			</tr>

			<!-- Help Text -->
			<tr>
				<th><label for="help_text"><?php esc_html_e( 'Help Text', 'owbn-archivist-toolkit' ); ?></label></th>
				<td>
					<textarea name="help_text" id="help_text" rows="3" class="large-text"><?php echo esc_textarea( $field['help_text'] ); ?></textarea>
					<p class="description">Shown below the field as guidance for the user.</p>
				</td>
			</tr>

			<!-- Default Value -->
			<tr>
				<th><label for="default_value"><?php esc_html_e( 'Default Value', 'owbn-archivist-toolkit' ); ?></label></th>
				<td>
					<input type="text" name="default_value" id="default_value" value="<?php echo esc_attr( $field['default_value'] ); ?>" class="regular-text">
				</td>
			</tr>

			<!-- Options JSON (for select/radio/checkbox) -->
			<tr>
				<th><label for="options_json"><?php esc_html_e( 'Options JSON', 'owbn-archivist-toolkit' ); ?></label></th>
				<td>
					<textarea name="options_json" id="options_json" rows="4" class="large-text code"><?php echo esc_textarea( $field['options_json'] ); ?></textarea>
					<p class="description">For select/radio/checkbox types. JSON object: <code>{"value": "Label", ...}</code></p>
				</td>
			</tr>

			<!-- CSV Import for Options -->
			<tr>
				<th><label for="options_csv"><?php esc_html_e( 'Import Options CSV', 'owbn-archivist-toolkit' ); ?></label></th>
				<td>
					<input type="file" name="options_csv" id="options_csv" accept=".csv,text/csv">
					<p class="description">
						Upload a CSV (2–5 columns) to populate Options JSON as a nested structure. Last column is always the leaf item.<br>
						<strong>2 cols</strong> <code>group,item</code> &rarr; <code>{"group": ["item", ...]}</code><br>
						<strong>3 cols</strong> <code>l1,l2,item</code> &rarr; <code>{"l1": {"l2": ["item", ...]}}</code><br>
						<strong>4 cols</strong> <code>l1,l2,l3,item</code> &rarr; <code>{"l1": {"l2": {"l3": ["item", ...]}}}</code><br>
						<strong>5 cols</strong> <code>l1,l2,l3,l4,item</code> &rarr; <code>{"l1": {"l2": {"l3": {"l4": ["item", ...]}}}}</code><br>
						First row is treated as a header and skipped. Replaces existing Options JSON when uploaded.
					</p>
				</td>
			</tr>

			<!-- Condition JSON -->
			<tr>
				<th><label for="condition_json"><?php esc_html_e( 'Condition JSON', 'owbn-archivist-toolkit' ); ?></label></th>
				<td>
					<textarea name="condition_json" id="condition_json" rows="3" class="large-text code"><?php echo esc_textarea( $field['condition_json'] ); ?></textarea>
					<p class="description">Show this field only when condition is met. Example: <code>{"field_key": "action_type", "value": "transfer"}</code></p>
				</td>
			</tr>

			<!-- Attributes JSON -->
			<tr>
				<th><label for="attributes_json"><?php esc_html_e( 'Attributes JSON', 'owbn-archivist-toolkit' ); ?></label></th>
				<td>
					<textarea name="attributes_json" id="attributes_json" rows="4" class="large-text code"><?php echo esc_textarea( $field['attributes_json'] ); ?></textarea>
					<p class="description">Extra configuration. Examples: <code>{"rows": 6}</code>, <code>{"roles": ["HST"], "show_role": false}</code></p>
				</td>
			</tr>

			<!-- Validation JSON -->
			<tr>
				<th><label for="validation_json"><?php esc_html_e( 'Validation JSON', 'owbn-archivist-toolkit' ); ?></label></th>
				<td>
					<textarea name="validation_json" id="validation_json" rows="3" class="large-text code"><?php echo esc_textarea( $field['validation_json'] ); ?></textarea>
					<p class="description">Custom validation rules. Example: <code>{"min_length": 3, "max_length": 255}</code></p>
				</td>
			</tr>
		</table>

		<?php submit_button( $is_edit ? __( 'Update Field', 'owbn-archivist-toolkit' ) : __( 'Add Field', 'owbn-archivist-toolkit' ), 'primary', 'oat_save_field' ); ?>
	</form>
</div>
