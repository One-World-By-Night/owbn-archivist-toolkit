<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap">
	<h1><?php echo esc_html( $title ); ?></h1>

	<?php settings_errors( 'oat_forms' ); ?>

	<p>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=oat-forms' ) ); ?>">&larr; Back to Forms</a>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=oat-forms' ) ); ?>">
		<?php wp_nonce_field( 'oat_save_form' ); ?>
		<input type="hidden" name="form_id" value="<?php echo (int) $form['id']; ?>">

		<table class="form-table">
			<tr>
				<th><label for="slug">Slug</label></th>
				<td>
					<?php if ( $is_edit ) : ?>
						<input type="text" id="slug" value="<?php echo esc_attr( $form['slug'] ); ?>" class="regular-text" readonly disabled>
						<input type="hidden" name="slug" value="<?php echo esc_attr( $form['slug'] ); ?>">
						<p class="description">Slug cannot be changed after creation.</p>
					<?php else : ?>
						<input type="text" name="slug" id="slug" value="<?php echo esc_attr( $form['slug'] ); ?>" class="regular-text" required pattern="[a-z0-9_-]+" title="Lowercase letters, numbers, hyphens, and underscores only.">
						<p class="description">Unique identifier (e.g., <code>join_chronicle</code>). Cannot be changed later.</p>
					<?php endif; ?>
				</td>
			</tr>

			<tr>
				<th><label for="label">Label</label></th>
				<td>
					<input type="text" name="label" id="label" value="<?php echo esc_attr( $form['label'] ); ?>" class="regular-text" required>
				</td>
			</tr>

			<tr>
				<th><label for="description">Description</label></th>
				<td>
					<textarea name="description" id="description" rows="3" class="large-text"><?php echo esc_textarea( $form['description'] ?? '' ); ?></textarea>
					<p class="description">Optional help text shown to submitters when selecting this form.</p>
				</td>
			</tr>

			<tr>
				<th><label for="sort_order">Sort Order</label></th>
				<td>
					<input type="number" name="sort_order" id="sort_order" value="<?php echo (int) ( $form['sort_order'] ?? 0 ); ?>" class="small-text" min="0" step="1">
				</td>
			</tr>

			<tr>
				<th>Active</th>
				<td>
					<label>
						<input type="checkbox" name="active" value="1"<?php checked( $form['active'] ); ?>>
						Inactive forms are hidden from the submission UI.
					</label>
				</td>
			</tr>

			<tr>
				<th>Assigned Domains</th>
				<td>
					<?php if ( ! empty( $all_domains ) ) : ?>
						<fieldset>
							<?php foreach ( $all_domains as $d ) : ?>
								<label style="display:block;margin-bottom:4px;">
									<input type="checkbox" name="domain_ids[]" value="<?php echo (int) $d->id; ?>"
										<?php checked( in_array( (int) $d->id, $assigned_domain_ids, true ) ); ?>>
									<?php echo esc_html( $d->label ); ?>
									<span style="color:#999;">(<?php echo esc_html( $d->slug ); ?>)</span>
								</label>
							<?php endforeach; ?>
						</fieldset>
						<p class="description">Select which domains this form is available in.</p>
					<?php else : ?>
						<p class="description">No domains exist yet. Create domains first.</p>
					<?php endif; ?>
				</td>
			</tr>
		</table>

		<?php submit_button( $is_edit ? 'Update Form' : 'Add Form', 'primary', 'oat_save_form' ); ?>
	</form>

	<?php if ( $is_edit ) : ?>
		<hr>
		<h2>Quick Links</h2>
		<ul>
			<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=oat-form-fields&form_slug=' . urlencode( $form['slug'] ) ) ); ?>">Manage Form Fields</a></li>
		</ul>
	<?php endif; ?>
</div>
