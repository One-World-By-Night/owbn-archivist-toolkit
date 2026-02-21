<?php

/** File: includes/admin/settings.php
 * Text Domain: accessschema-client
 * version 2.3.0
 *
 * @author greghacke
 * Function: Define admin settings pages for AccessSchema client (per-instance)
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register an admin menu page for each registered client instance.
 *
 * Iterates accessschema_registered_slugs so that every embedded client
 * gets its own settings page — no reliance on the ASC_PREFIX constant.
 */
add_action(
	'admin_menu',
	function () {
		$registered = apply_filters( 'accessschema_registered_slugs', array() );
		if ( empty( $registered ) ) {
			return;
		}

		foreach ( $registered as $client_id => $label ) {
			$menu_slug      = "{$client_id}_client";
			$prefix_display = strtoupper( preg_replace( '/[^A-Z0-9]/i', '', str_replace( '-', '_', $client_id ) ) );

			add_users_page(
				$label . ' accessSchema Client Settings',   // Page title
				$prefix_display . ' ASC',                   // Menu label
				'manage_options',                            // Capability
				$menu_slug,                                  // Slug
				function () use ( $client_id, $label ) {
					echo '<div class="wrap">';
					echo '<h1>' . esc_html( $label ) . ' AccessSchema Client</h1>';
					accessSchema_render_slug_settings_form( $client_id );
					accessSchema_render_slug_cache_clear( $client_id, $label );
					echo '</div>';
				}
			);
		}
	}
);
