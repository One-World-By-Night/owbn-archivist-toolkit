<?php

defined( 'ABSPATH' ) || exit;

class OAT_Page_Toolbox {

	/**
	 * Render the Toolbox page — Domains, Forms, Workflow Steps, Form Fields as inline tabs.
	 */
	public static function render() {
		if ( ! OAT_Authorization::check( OAT_Constants::CAP_ADMIN ) ) {
			wp_die( 'You do not have permission to access the toolbox.' );
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'domains';

		$tabs = array(
			'domains' => 'Domains',
			'forms'   => 'Forms',
			'steps'   => 'Workflow Steps',
			'fields'  => 'Form Fields',
		);

		?>
		<div class="wrap">
			<h1>Archivist Toolbox</h1>
			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $slug => $label ) :
					$url    = admin_url( 'admin.php?page=oat-toolbox&tab=' . $slug );
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
					case 'domains':
						OAT_Page_Domains::render( true );
						break;
					case 'forms':
						OAT_Page_Forms::render( true );
						break;
					case 'steps':
						OAT_Page_Workflow_Steps::render( true );
						break;
					case 'fields':
						OAT_Page_Form_Fields::render( true );
						break;
				}
				?>
			</div>
		</div>
		<?php
	}
}
