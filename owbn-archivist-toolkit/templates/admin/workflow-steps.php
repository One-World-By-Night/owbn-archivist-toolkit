<?php defined( 'ABSPATH' ) || exit; ?>
<?php if ( empty( $embedded ) ) : ?><div class="wrap">
	<h1><?php esc_html_e( 'Workflow Steps', 'owbn-archivist-toolkit' ); ?></h1>
<?php endif; ?>

	<?php settings_errors( 'oat_workflow_steps' ); ?>

	<?php if ( 'added' === $message ) : ?>
		<div class="notice notice-success is-dismissible"><p>Step added successfully.</p></div>
	<?php elseif ( 'updated' === $message ) : ?>
		<div class="notice notice-success is-dismissible"><p>Step updated.</p></div>
	<?php elseif ( 'deleted' === $message ) : ?>
		<div class="notice notice-success is-dismissible"><p>Step deleted.</p></div>
	<?php elseif ( 'activated' === $message ) : ?>
		<div class="notice notice-success is-dismissible"><p>Step activated.</p></div>
	<?php elseif ( 'deactivated' === $message ) : ?>
		<div class="notice notice-success is-dismissible"><p>Step deactivated.</p></div>
	<?php endif; ?>

	<form method="get">
		<input type="hidden" name="page" value="oat-workflow-steps">

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
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=oat-workflow-steps&action=add&domain=' . urlencode( $current_domain ) ) ); ?>" class="button button-primary">
						Add Step
					</a>
				</div>
			<?php endif; ?>
		</div>
	</form>

	<?php if ( '' !== $current_domain && $list_table ) : ?>
		<?php $list_table->display(); ?>
	<?php endif; ?>
<?php if ( empty( $embedded ) ) : ?></div><?php endif; ?>
