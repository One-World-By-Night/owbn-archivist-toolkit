<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap">
	<h1>R&U Distribution Details</h1>

	<div style="display:flex;align-items:center;gap:15px;margin-bottom:10px;">
		<label><strong>View:</strong></label>
		<?php
		$view_options = array( 'pc' => 'PCs', 'npc' => 'NPCs', 'all' => 'All' );
		foreach ( $view_options as $val => $label_text ) :
			$url     = admin_url( 'admin.php?page=oat-reports&tab=' . $tab . '&pc_npc=' . $val );
			$current = ( $pc_npc === $val );
			$style   = $current ? 'font-weight:bold;text-decoration:none;' : '';
		?>
			<?php if ( $current ) : ?>
				<strong><?php echo esc_html( $label_text ); ?></strong>
			<?php else : ?>
				<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label_text ); ?></a>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>

	<nav class="nav-tab-wrapper">
		<?php foreach ( $tabs as $slug => $label ) :
			$url    = admin_url( 'admin.php?page=oat-reports&tab=' . $slug . '&pc_npc=' . $pc_npc );
			$active = ( $tab === $slug ) ? ' nav-tab-active' : '';
		?>
			<a href="<?php echo esc_url( $url ); ?>" class="nav-tab<?php echo $active; ?>">
				<?php echo esc_html( $label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<div style="margin-top:15px;">
		<?php
		// Status filter (for tabs that support it).
		$show_status_filter = in_array( $tab, array( 'all-pcs', 'by-region', 'by-chronicle', 'by-player', 'by-type' ), true );
		?>

		<form method="get">
			<input type="hidden" name="page" value="oat-reports">
			<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">
			<input type="hidden" name="pc_npc" value="<?php echo esc_attr( $pc_npc ); ?>">

			<?php if ( $show_status_filter && 'active' !== $tab ) : ?>
				<div class="alignleft actions">
					<select name="status">
						<option value="">All Statuses</option>
						<?php
						$statuses = OAT_Report_Query::get_statuses();
						foreach ( $statuses as $s ) :
							$selected = ( $status === $s ) ? ' selected' : '';
						?>
							<option value="<?php echo esc_attr( $s ); ?>"<?php echo $selected; ?>>
								<?php echo esc_html( ucfirst( $s ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<?php submit_button( 'Filter', '', 'filter_action', false ); ?>
					<?php if ( $status ) :
						$clear_url = admin_url( 'admin.php?page=oat-reports&tab=' . $tab . '&pc_npc=' . $pc_npc );
					?>
						<a href="<?php echo esc_url( $clear_url ); ?>" class="button">Clear</a>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div class="alignright" style="margin-bottom:10px;">
				<?php
				$export_url = admin_url( 'admin.php?page=oat-reports&tab=' . $tab . '&export_csv=1&pc_npc=' . $pc_npc );
				if ( $status ) {
					$export_url .= '&status=' . urlencode( $status );
				}
				?>
				<a href="<?php echo esc_url( $export_url ); ?>" class="button">Export CSV</a>
			</div>

			<?php if ( $list_table ) : ?>
				<?php $list_table->display(); ?>
			<?php endif; ?>
		</form>
	</div>
</div>
