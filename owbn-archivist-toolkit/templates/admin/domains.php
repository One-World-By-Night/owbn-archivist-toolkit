<?php defined( 'ABSPATH' ) || exit; ?>
<?php if ( empty( $embedded ) ) : ?><div class="wrap">
	<h1>Domains</h1>
<?php endif; ?>

	<?php settings_errors( 'oat_domains' ); ?>

	<?php if ( 'added' === $message ) : ?>
		<div class="notice notice-success is-dismissible"><p>Domain added successfully.</p></div>
	<?php elseif ( 'updated' === $message ) : ?>
		<div class="notice notice-success is-dismissible"><p>Domain updated.</p></div>
	<?php elseif ( 'deleted' === $message ) : ?>
		<div class="notice notice-success is-dismissible"><p>Domain deleted.</p></div>
	<?php elseif ( 'activated' === $message ) : ?>
		<div class="notice notice-success is-dismissible"><p>Domain activated.</p></div>
	<?php elseif ( 'deactivated' === $message ) : ?>
		<div class="notice notice-success is-dismissible"><p>Domain deactivated.</p></div>
	<?php endif; ?>

	<div class="tablenav top">
		<div class="alignright">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=oat-domains&action=add' ) ); ?>" class="button button-primary">
				Add Domain
			</a>
		</div>
	</div>

	<?php $list_table->display(); ?>
<?php if ( empty( $embedded ) ) : ?></div><?php endif; ?>
