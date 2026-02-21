<?php

/** File: includes/render/admin.php
 * Text Domain: accessschema-client
 * version 2.0.4
 *
 * @author greghacke
 * Function: Renders the admin settings and user cache management interface
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'accessSchema_client_register_render_admin' ) ) {
	function accessSchema_client_register_render_admin( $client_id, $label ) {
		add_action(
			"accessschema_render_admin_{$client_id}",
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

// Register profile display for ALL clients — once only.
if ( ! defined( 'ASC_CLIENT_PROFILE_HOOKS_REGISTERED' ) ) {
	define( 'ASC_CLIENT_PROFILE_HOOKS_REGISTERED', true );

	add_action(
		'show_user_profile',
		function ( $user ) {
			$registered = apply_filters( 'accessschema_registered_slugs', array() );
			foreach ( $registered as $client_id => $label ) {
				accessSchema_render_user_cache_block( $client_id, $label, $user );
			}
		},
		15
	);

	add_action(
		'edit_user_profile',
		function ( $user ) {
			$registered = apply_filters( 'accessschema_registered_slugs', array() );
			foreach ( $registered as $client_id => $label ) {
				accessSchema_render_user_cache_block( $client_id, $label, $user );
			}
		},
		15
	);
}

if ( ! function_exists( 'accessSchema_render_slug_settings_form' ) ) {
	function accessSchema_render_slug_settings_form( $client_id ) {
		echo '<form method="post" action="options.php">';
		settings_fields( "{$client_id}_client" );
		do_settings_sections( "{$client_id}_client_settings" );
		submit_button();
		echo '</form>';
	}
}

if ( ! function_exists( 'accessSchema_render_slug_cache_clear' ) ) {
	function accessSchema_render_slug_cache_clear( $client_id, $label ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$key_roles = 'accessschema_cached_roles';
		$key_time  = 'accessschema_cached_roles_timestamp';

		echo '<hr>';
		echo '<h2>Manual Role Cache Clear</h2>';
		echo '<form method="post">';
		wp_nonce_field( "{$client_id}_clear_roles", "{$client_id}_clear_nonce" );
		echo '<input type="email" name="' . esc_attr( "clear_email_{$client_id}" ) . '" placeholder="User email" required class="regular-text" />';
		echo '<button type="submit" class="button button-secondary">Clear Cached Roles</button>';
		echo '</form>';

		if (
			! empty( $_POST[ "clear_email_{$client_id}" ] ) &&
			isset( $_POST[ "{$client_id}_clear_nonce" ] ) &&
			wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ "{$client_id}_clear_nonce" ] ) ), "{$client_id}_clear_roles" )
		) {
			$email = sanitize_email( wp_unslash( $_POST[ "clear_email_{$client_id}" ] ) );
			$user  = get_user_by( 'email', $email );

			if ( $user ) {
				delete_user_meta( $user->ID, $key_roles );
				delete_user_meta( $user->ID, $key_time );
				echo '<p><strong>Cache cleared for ' . esc_html( $user->user_email ) . '</strong></p>';
			} else {
				echo '<p><strong>No user found with that email.</strong></p>';
			}
		}
	}
}

if ( ! function_exists( 'accessSchema_render_user_cache_block' ) ) {
	function accessSchema_render_user_cache_block( $client_id, $label, $user ) {
		if ( ! current_user_can( 'list_users' ) ) {
			return;
		}

		$roles_key = 'accessschema_cached_roles';
		$time_key  = 'accessschema_cached_roles_timestamp';

		$roles        = get_user_meta( $user->ID, $roles_key, true );
		$timestamp    = get_user_meta( $user->ID, $time_key, true );
		$display_time = $timestamp ? date_i18n( 'm/d/Y h:i a', intval( $timestamp ) ) : '[Unknown]';

		$flush_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'  => 'flush_accessschema_cache',
					'user_id' => $user->ID,
					'slug'    => $client_id,
				),
				admin_url( 'users.php' )
			),
			"flush_accessschema_{$user->ID}_{$client_id}"
		);

		$refresh_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'  => 'refresh_accessschema_cache',
					'user_id' => $user->ID,
					'slug'    => $client_id,
				),
				admin_url( 'users.php' )
			),
			"refresh_accessschema_{$user->ID}_{$client_id}"
		);

		echo '<h2>AccessSchema Roles</h2>';
		echo '<table class="form-table" role="presentation">';
		echo '<tr>';
		echo '<th><label>AccessSchema Roles</label></th>';
		echo '<td>';

		echo '<strong>' . esc_html( $label ) . ' ASC (Cached):</strong>';

		if ( is_array( $roles ) && ! empty( $roles ) ) {
			echo '<ul style="margin: 8px 0 4px 20px; padding-left: 0;">';
			foreach ( $roles as $role ) {
				echo '<li style="list-style: disc;">' . esc_html( $role ) . '</li>';
			}
			echo '</ul>';
			echo '<p style="margin-top: 0;"><strong>Cached:</strong> ' . esc_html( $display_time );
			echo ' <a href="' . esc_url( $flush_url ) . '" style="margin-left:8px;">[Flush]</a>';
			echo ' <a href="' . esc_url( $refresh_url ) . '" style="margin-left:4px;">[Refresh]</a></p>';
		} else {
			echo '<p style="margin-left: 20px;">[None] <a href="' . esc_url( $refresh_url ) . '" style="margin-left:8px;">[Request]</a></p>';
		}

		echo '</td>';
		echo '</tr>';
		echo '</table>';
	}
}

// Admin actions for cache management are handled in includes/admin/users.php
// via admin_init — no duplicate hooks needed here.
