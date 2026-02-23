<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap">
	<h1>Form Fields</h1>

	<?php settings_errors( 'oat_form_fields' ); ?>

	<?php if ( 'added' === $message ) : ?>
		<div class="notice notice-success is-dismissible"><p>Field added successfully.</p></div>
	<?php elseif ( 'updated' === $message ) : ?>
		<div class="notice notice-success is-dismissible"><p>Field updated.</p></div>
	<?php elseif ( 'deleted' === $message ) : ?>
		<div class="notice notice-success is-dismissible"><p>Field deleted.</p></div>
	<?php elseif ( 'activated' === $message ) : ?>
		<div class="notice notice-success is-dismissible"><p>Field activated.</p></div>
	<?php elseif ( 'deactivated' === $message ) : ?>
		<div class="notice notice-success is-dismissible"><p>Field deactivated.</p></div>
	<?php endif; ?>

	<?php
	// Domain selector.
	$domains        = OAT_Domain_Registry::get_all();
	$current_domain = isset( $_GET['domain'] ) ? sanitize_text_field( $_GET['domain'] ) : '';
	?>

	<form method="get">
		<input type="hidden" name="page" value="oat-form-fields">

		<div class="tablenav top">
			<div class="alignleft actions">
				<select name="domain" onchange="this.form.submit();">
					<option value="">-- Select Domain --</option>
					<?php foreach ( $domains as $d ) : ?>
						<option value="<?php echo esc_attr( $d['slug'] ); ?>"<?php selected( $current_domain, $d['slug'] ); ?>>
							<?php echo esc_html( $d['label'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<?php if ( '' !== $current_domain ) : ?>
				<div class="alignright">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=oat-form-fields&action=add&domain=' . urlencode( $current_domain ) ) ); ?>" class="button button-primary">
						Add Field
					</a>
				</div>
			<?php endif; ?>
		</div>
	</form>

	<?php if ( '' !== $current_domain ) : ?>
		<form method="get">
			<input type="hidden" name="page" value="oat-form-fields">
			<input type="hidden" name="domain" value="<?php echo esc_attr( $current_domain ); ?>">
			<?php $list_table->display(); ?>
		</form>
	<?php endif; ?>
</div>
