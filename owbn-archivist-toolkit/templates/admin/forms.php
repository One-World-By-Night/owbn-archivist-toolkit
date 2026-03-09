<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap">
	<h1>Forms</h1>

	<?php settings_errors( 'oat_forms' ); ?>

	<?php if ( 'added' === $message ) : ?>
		<div class="notice notice-success is-dismissible"><p>Form added successfully.</p></div>
	<?php elseif ( 'updated' === $message ) : ?>
		<div class="notice notice-success is-dismissible"><p>Form updated.</p></div>
	<?php elseif ( 'deleted' === $message ) : ?>
		<div class="notice notice-success is-dismissible"><p>Form deleted.</p></div>
	<?php elseif ( 'activated' === $message ) : ?>
		<div class="notice notice-success is-dismissible"><p>Form activated.</p></div>
	<?php elseif ( 'deactivated' === $message ) : ?>
		<div class="notice notice-success is-dismissible"><p>Form deactivated.</p></div>
	<?php endif; ?>

	<div class="tablenav top">
		<div class="alignright">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=oat-forms&action=add' ) ); ?>" class="button button-primary">
				Add Form
			</a>
		</div>
	</div>

	<?php $list_table->display(); ?>
</div>
