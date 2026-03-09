<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap">
	<h1><?php echo esc_html( $title ); ?></h1>

	<?php settings_errors( 'oat_domains' ); ?>

	<p>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=oat-domains' ) ); ?>">&larr; Back to Domains</a>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=oat-domains' ) ); ?>">
		<?php wp_nonce_field( 'oat_save_domain' ); ?>
		<input type="hidden" name="domain_id" value="<?php echo (int) $domain['id']; ?>">

		<table class="form-table">
			<!-- Slug -->
			<tr>
				<th><label for="slug">Slug</label></th>
				<td>
					<?php if ( $is_edit ) : ?>
						<input type="text" id="slug" value="<?php echo esc_attr( $domain['slug'] ); ?>" class="regular-text" readonly disabled>
						<input type="hidden" name="slug" value="<?php echo esc_attr( $domain['slug'] ); ?>">
						<p class="description">Slug cannot be changed after creation.</p>
					<?php else : ?>
						<input type="text" name="slug" id="slug" value="<?php echo esc_attr( $domain['slug'] ); ?>" class="regular-text" required pattern="[a-z0-9_]+" title="Lowercase letters, numbers, and underscores only.">
						<p class="description">Unique identifier (e.g., <code>chronicle_reporting</code>). Cannot be changed later.</p>
					<?php endif; ?>
				</td>
			</tr>

			<!-- Label -->
			<tr>
				<th><label for="label">Label</label></th>
				<td>
					<input type="text" name="label" id="label" value="<?php echo esc_attr( $domain['label'] ); ?>" class="regular-text" required>
					<p class="description">Display name shown in the UI.</p>
				</td>
			</tr>

			<!-- Archivist Mode -->
			<tr>
				<th><label for="archivist_mode">Archivist Mode</label></th>
				<td>
					<select name="archivist_mode" id="archivist_mode">
						<option value="manual"<?php selected( $domain['archivist_mode'], 'manual' ); ?>>Manual</option>
						<option value="auto"<?php selected( $domain['archivist_mode'], 'auto' ); ?>>Auto</option>
					</select>
					<p class="description"><strong>Manual:</strong> Archivist must review and record. <strong>Auto:</strong> Archivist step auto-records on final approval.</p>
				</td>
			</tr>

			<!-- Sort Order -->
			<tr>
				<th><label for="sort_order">Sort Order</label></th>
				<td>
					<input type="number" name="sort_order" id="sort_order" value="<?php echo (int) ( $domain['sort_order'] ?? 0 ); ?>" class="small-text" min="0" step="1">
					<p class="description">Lower numbers appear first in dropdowns. Domains with the same sort order are sorted alphabetically.</p>
				</td>
			</tr>

			<!-- Active -->
			<tr>
				<th>Active</th>
				<td>
					<label>
						<input type="checkbox" name="active" value="1"<?php checked( $domain['active'] ); ?>>
						Inactive domains are hidden from the submission UI.
					</label>
				</td>
			</tr>
		</table>

		<?php submit_button( $is_edit ? 'Update Domain' : 'Add Domain', 'primary', 'oat_save_domain' ); ?>
	</form>

	<?php if ( $is_edit ) : ?>
		<hr>
		<h2>Quick Links</h2>
		<ul>
			<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=oat-workflow-steps&domain=' . urlencode( $domain['slug'] ) ) ); ?>">Manage Workflow Steps</a></li>
			<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=oat-form-fields&domain=' . urlencode( $domain['slug'] ) ) ); ?>">Manage Form Fields</a></li>
		</ul>
	<?php endif; ?>
</div>
