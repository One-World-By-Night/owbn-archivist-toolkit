<?php

defined( 'ABSPATH' ) || exit;

class OAT_Page_Toolbox {

	/**
	 * Render the Toolbox landing page — links to Domains, Forms, Workflow Steps, Form Fields.
	 */
	public static function render() {
		if ( ! OAT_Authorization::check( OAT_Constants::CAP_ADMIN ) ) {
			wp_die( 'You do not have permission to access the toolbox.' );
		}

		$tabs = array(
			'oat-domains'        => 'Domains',
			'oat-forms'          => 'Forms',
			'oat-workflow-steps' => 'Workflow Steps',
			'oat-form-fields'    => 'Form Fields',
		);

		// If a tab param is passed, redirect to the corresponding page.
		if ( ! empty( $_GET['tab'] ) ) {
			$target = sanitize_text_field( $_GET['tab'] );
			$map    = array(
				'domains' => 'oat-domains',
				'forms'   => 'oat-forms',
				'steps'   => 'oat-workflow-steps',
				'fields'  => 'oat-form-fields',
			);
			if ( isset( $map[ $target ] ) ) {
				wp_redirect( admin_url( 'admin.php?page=' . $map[ $target ] ) );
				exit;
			}
		}

		?>
		<div class="wrap">
			<h1>OAT Toolbox</h1>
			<p>Manage the underlying OAT schema — domains, forms, workflow steps, and form fields.</p>
			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $slug => $label ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $slug ) ); ?>" class="nav-tab">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>
		</div>
		<?php
	}
}
