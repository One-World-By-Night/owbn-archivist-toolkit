<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap">
	<h1><?php esc_html_e( 'R&U Distribution Details', 'owbn-archivist-toolkit' ); ?></h1>

	<?php
	// Build scope_roles query string fragment for preserving across links.
	$scope_qs = '';
	if ( ! empty( $selected_roles ) ) {
		foreach ( $selected_roles as $sr ) {
			$scope_qs .= '&scope_roles[]=' . urlencode( $sr );
		}
	}
	?>

	<?php // ─── Role Scope Selector (non-global users with 2+ roles) ─── ?>
	<?php if ( ! empty( $scope ) && empty( $scope['is_global'] ) && count( $scope['roles'] ) > 1 ) : ?>
		<div style="background:#f0f6fc;border:1px solid #c3c4c7;border-radius:4px;padding:10px 15px;margin-bottom:15px;display:flex;align-items:center;flex-wrap:wrap;gap:10px;">
			<strong style="margin-right:5px;">Viewing as:</strong>
			<form method="get" style="display:flex;align-items:center;flex-wrap:wrap;gap:10px;margin:0;">
				<input type="hidden" name="page" value="oat-reports">
				<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">
				<input type="hidden" name="pc_npc" value="<?php echo esc_attr( $pc_npc ); ?>">
				<?php if ( $status ) : ?>
					<input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>">
				<?php endif; ?>
				<?php foreach ( $scope['roles'] as $role_path ) :
					$checked = empty( $selected_roles ) || in_array( $role_path, $selected_roles, true );
					$label   = OAT_Page_Reports::role_label( $role_path );
				?>
					<label style="display:inline-flex;align-items:center;gap:4px;cursor:pointer;">
						<input type="checkbox" name="scope_roles[]" value="<?php echo esc_attr( $role_path ); ?>"<?php echo $checked ? ' checked' : ''; ?>>
						<?php echo esc_html( $label ); ?>
					</label>
				<?php endforeach; ?>
				<?php submit_button( __( 'Apply', 'owbn-archivist-toolkit' ), 'secondary', 'apply_scope', false, array( 'style' => 'margin:0;' ) ); ?>
			</form>
		</div>
	<?php endif; ?>

	<div style="display:flex;align-items:center;gap:15px;margin-bottom:10px;">
		<label><strong>View:</strong></label>
		<?php
		$view_options = array( 'pc' => 'PCs', 'npc' => 'NPCs', 'all' => 'All' );
		foreach ( $view_options as $val => $label_text ) :
			$url     = admin_url( 'admin.php?page=oat-reports&tab=' . $tab . '&pc_npc=' . $val . $scope_qs );
			$current = ( $pc_npc === $val );
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
			$url    = admin_url( 'admin.php?page=oat-reports&tab=' . $slug . '&pc_npc=' . $pc_npc . $scope_qs );
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
			<?php // Preserve scope_roles in the filter form. ?>
			<?php if ( ! empty( $selected_roles ) ) : ?>
				<?php foreach ( $selected_roles as $sr ) : ?>
					<input type="hidden" name="scope_roles[]" value="<?php echo esc_attr( $sr ); ?>">
				<?php endforeach; ?>
			<?php endif; ?>

			<?php if ( $show_status_filter && 'active' !== $tab ) : ?>
				<div class="alignleft actions">
					<select name="status">
						<option value="">All Statuses</option>
						<?php
						$statuses = OAT_Report_Query::get_statuses( $pc_npc, $scope );
						foreach ( $statuses as $s ) :
							$selected_attr = ( $status === $s ) ? ' selected' : '';
						?>
							<option value="<?php echo esc_attr( $s ); ?>"<?php echo $selected_attr; ?>>
								<?php echo esc_html( ucfirst( $s ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<?php submit_button( __( 'Filter', 'owbn-archivist-toolkit' ), '', 'filter_action', false ); ?>
					<?php if ( $status ) :
						$clear_url = admin_url( 'admin.php?page=oat-reports&tab=' . $tab . '&pc_npc=' . $pc_npc . $scope_qs );
					?>
						<a href="<?php echo esc_url( $clear_url ); ?>" class="button">Clear</a>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div class="alignright" style="margin-bottom:10px;">
				<?php
				$export_url = admin_url( 'admin.php?page=oat-reports&tab=' . $tab . '&export_csv=1&pc_npc=' . $pc_npc . $scope_qs );
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
