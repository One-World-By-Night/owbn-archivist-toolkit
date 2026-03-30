<?php

defined( 'ABSPATH' ) || exit;

class OAT_Page_Settings {

	/**
	 * Render the settings page with tabs.
	 */
	public static function render() {
		if ( ! OAT_Authorization::check( OAT_Constants::CAP_ARCHIVIST ) ) {
			wp_die( 'You do not have permission to view settings.' );
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';

		$tabs = array(
			'general'  => 'General',
			'rules'    => 'Regulation Rules',
			'taxonomy' => 'Creature Taxonomy',
		);

		?>
		<div class="wrap">
			<h1>Archivist Settings</h1>
			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $slug => $label ) :
					$url    = admin_url( 'admin.php?page=oat-settings&tab=' . $slug );
					$active = ( $tab === $slug ) ? ' nav-tab-active' : '';
				?>
					<a href="<?php echo esc_url( $url ); ?>" class="nav-tab<?php echo $active; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>
			<div style="margin-top:15px;">
				<?php
				switch ( $tab ) {
					case 'general':
						self::render_general();
						break;
					case 'rules':
						OAT_Page_Rules::render( true );
						break;
					case 'taxonomy':
						OAT_Page_Taxonomy::render( true );
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle settings save on admin_init.
	 */
	public static function maybe_save() {
		if ( ! isset( $_POST['oat_settings_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( $_POST['oat_settings_nonce'], 'oat_save_settings' ) ) {
			return;
		}
		if ( ! OAT_Authorization::check( OAT_Constants::CAP_ARCHIVIST ) ) {
			return;
		}

		$roles_raw = isset( $_POST['oat_character_create_roles'] ) ? sanitize_textarea_field( $_POST['oat_character_create_roles'] ) : '';
		$roles     = array_filter( array_map( 'trim', explode( "\n", $roles_raw ) ) );
		update_option( 'oat_character_create_roles', $roles );

		wp_redirect( admin_url( 'admin.php?page=oat-settings&tab=general&saved=1' ) );
		exit;
	}

	/**
	 * Render the General settings tab.
	 */
	private static function render_general() {
		$saved = isset( $_GET['saved'] ) && '1' === $_GET['saved'];
		$roles = get_option( 'oat_character_create_roles', array() );
		if ( ! is_array( $roles ) ) {
			$roles = array();
		}
		$roles_text = implode( "\n", $roles );

		if ( $saved ) {
			echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
		}
		?>
		<form method="post">
			<?php wp_nonce_field( 'oat_save_settings', 'oat_settings_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="oat_character_create_roles">Character Creation — Allowed Roles</label></th>
					<td>
						<textarea name="oat_character_create_roles" id="oat_character_create_roles" rows="8" class="large-text code"><?php echo esc_textarea( $roles_text ); ?></textarea>
						<p class="description">
							One ASC role pattern per line. Users matching any pattern can create characters.<br>
							Use <code>*</code> as a wildcard for any slug segment.<br>
							Examples: <code>coordinator/*/coordinator</code>, <code>chronicle/*/hst</code>, <code>exec/*/coordinator</code><br>
							Leave empty to allow all authenticated users (current default).
						</p>
					</td>
				</tr>
			</table>
			<?php submit_button( 'Save Settings' ); ?>
		</form>
		<?php
	}
}
