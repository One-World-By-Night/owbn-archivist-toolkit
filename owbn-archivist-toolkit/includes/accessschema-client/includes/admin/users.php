<?php

/** File: includes/admin/users.php
 * Text Domain: accessschema-client
 *
 * @version 2.1.1
 * @author greghacke
 * Function: Define admin users page for AccessSchema client
 */

defined( 'ABSPATH' ) || exit;

/**
 * Shared hooks — register once regardless of how many client instances load.
 * All callbacks iterate 'accessschema_registered_slugs' to cover every instance.
 */
if ( defined( 'ASC_CLIENT_USERS_HOOKS_REGISTERED' ) ) {
	return;
}
define( 'ASC_CLIENT_USERS_HOOKS_REGISTERED', true );

/**
 * Add a single column to the Users table for remote AccessSchema-client instances.
 *
 * Skips the column entirely if all registered clients are in local mode,
 * since the server plugin already provides its own ASC Roles column.
 */
add_filter(
	'manage_users_columns',
	function ( $columns ) {
		$registered = apply_filters( 'accessschema_registered_slugs', array() );
		if ( is_wp_error( $registered ) || empty( $registered ) ) {
			return $columns;
		}

		// Only add the column if at least one client is in remote mode.
		$has_remote = false;
		foreach ( $registered as $client_id => $label ) {
			if ( accessSchema_is_remote_mode( $client_id ) ) {
				$has_remote = true;
				break;
			}
		}

		if ( $has_remote ) {
			$columns['accessschema_roles'] = 'AccessSchema Roles';
		}

		return $columns;
	}
);

/**
 * Populate the AccessSchema Roles column with grouped role display.
 *
 * All clients share a single cache key, so roles are rendered once.
 * The first registered remote client is used for Flush/Refresh actions.
 */
add_filter(
	'manage_users_custom_column',
	function ( $output, $column_name, $user_id ) {
		if ( 'accessschema_roles' !== $column_name ) {
			return $output;
		}

		$registered = apply_filters( 'accessschema_registered_slugs', array() );
		if ( is_wp_error( $registered ) || empty( $registered ) ) {
			return '[No AccessSchema Instances Registered]';
		}

		// Pick the first registered remote client for action URLs.
		$action_client = null;
		foreach ( $registered as $cid => $lbl ) {
			if ( accessSchema_is_remote_mode( $cid ) ) {
				$action_client = $cid;
				break;
			}
		}
		if ( ! $action_client ) {
			$action_client = array_key_first( $registered );
		}

		// Shared cache — read once.
		$roles     = get_user_meta( $user_id, 'accessschema_cached_roles', true );
		$timestamp = get_user_meta( $user_id, 'accessschema_cached_roles_timestamp', true );

		$base_url = admin_url( 'users.php' );

		$flush_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'  => 'flush_accessschema_cache',
					'user_id' => $user_id,
					'slug'    => $action_client,
				),
				$base_url
			),
			"flush_accessschema_{$user_id}_{$action_client}"
		);

		$refresh_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'  => 'refresh_accessschema_cache',
					'user_id' => $user_id,
					'slug'    => $action_client,
				),
				$base_url
			),
			"refresh_accessschema_{$user_id}_{$action_client}"
		);

		$output = '<div class="asc-client-role-column">';

		if ( ! is_array( $roles ) || empty( $roles ) ) {
			$output .= '<span class="asc-client-no-roles">[None]</span> ';
			$output .= '<a href="' . esc_url( $refresh_url ) . '">[Request]</a>';
		} else {
			$output .= accessSchema_client_render_grouped_roles( $roles );

			$time_display = $timestamp
				? date_i18n( 'm/d/Y h:i a', intval( $timestamp ) )
				: '[Unknown]';

			$output .= '<div class="asc-client-cache-info">';
			$output .= '<span class="asc-client-timestamp">' . esc_html( $time_display ) . '</span> ';
			$output .= '<a href="' . esc_url( $flush_url ) . '">[Flush]</a> ';
			$output .= '<a href="' . esc_url( $refresh_url ) . '">[Refresh]</a>';
			$output .= '</div>';
		}

		$output .= '</div>';
		return $output;
	},
	10,
	3
);

/**
 * Render roles grouped by top-level category.
 *
 * @since 2.1.1
 *
 * @param string[] $roles Array of full role path strings.
 * @return string HTML output of grouped roles.
 */
if ( ! function_exists( 'accessSchema_client_render_grouped_roles' ) ) {
	function accessSchema_client_render_grouped_roles( $roles ) {
		// Group roles by first path segment.
		$grouped = array();
		foreach ( $roles as $role_path ) {
			$parts    = explode( '/', $role_path );
			$category = $parts[0];

			if ( count( $parts ) > 1 ) {
				$remainder = implode( '/', array_slice( $parts, 1 ) );
			} else {
				$remainder = '';
			}

			if ( ! isset( $grouped[ $category ] ) ) {
				$grouped[ $category ] = array();
			}
			$grouped[ $category ][] = array(
				'full_path' => $role_path,
				'display'   => $remainder,
			);
		}

		// Category border colors (rotate through 5).
		$colors = array( '#1565c0', '#6a1b9a', '#2e7d32', '#e65100', '#c2185b' );

		$html      = '<div class="asc-client-role-list">';
		$cat_index = 0;
		foreach ( $grouped as $category => $cat_roles ) {
			$color = $colors[ $cat_index % 5 ];

			$html .= '<div class="asc-client-role-group" style="border-left:3px solid ' . esc_attr( $color ) . ';padding-left:6px;margin-bottom:3px;">';
			$html .= '<span class="asc-client-role-category" style="color:' . esc_attr( $color ) . ';">' . esc_html( $category ) . '</span>';

			foreach ( $cat_roles as $role ) {
				if ( '' === $role['display'] ) {
					continue;
				}
				$html .= '<span class="asc-client-role-item" title="' . esc_attr( $role['full_path'] ) . '">' . esc_html( $role['display'] ) . '</span>';
			}

			$html .= '</div>';
			++$cat_index;
		}
		$html .= '</div>';

		return $html;
	}
}

/**
 * Handle flush and refresh actions scoped per-plugin instance.
 */
add_action(
	'admin_init',
	function () {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified via check_admin_referer in each action branch below
		if (
		isset( $_GET['action'], $_GET['user_id'], $_GET['slug'] ) &&
		current_user_can( 'manage_options' )
		) {
			$user_id   = intval( wp_unslash( $_GET['user_id'] ) );
			$action    = sanitize_key( wp_unslash( $_GET['action'] ) );
			$client_id = sanitize_key( wp_unslash( $_GET['slug'] ) );

			$registered = apply_filters( 'accessschema_registered_slugs', array() );
			if ( ! isset( $registered[ $client_id ] ) ) {
				return;
			}

			$cache_key     = 'accessschema_cached_roles';
			$timestamp_key = 'accessschema_cached_roles_timestamp';

			if ( 'flush_accessschema_cache' === $action ) {
				check_admin_referer( "flush_accessschema_{$user_id}_{$client_id}" );

				delete_user_meta( $user_id, $cache_key );
				delete_user_meta( $user_id, $timestamp_key );

				wp_safe_redirect( add_query_arg( array( 'message' => 'accessschema_cache_flushed' ), admin_url( 'users.php' ) ) );
				exit;
			}

			if ( 'refresh_accessschema_cache' === $action ) {
				check_admin_referer( "refresh_accessschema_{$user_id}_{$client_id}" );

				$user = get_user_by( 'ID', $user_id );
				if ( $user ) {
					$result = apply_filters( 'accessschema_client_refresh_roles', null, $user, $client_id );

					if ( is_array( $result ) && isset( $result['roles'] ) ) {
						update_user_meta( $user_id, $cache_key, $result['roles'] );
						update_user_meta( $user_id, $timestamp_key, time() );

						wp_safe_redirect( add_query_arg( array( 'message' => 'accessschema_cache_refreshed' ), admin_url( 'users.php' ) ) );
						exit;
					}
				}

				wp_safe_redirect( add_query_arg( array( 'message' => 'accessschema_cache_failed' ), admin_url( 'users.php' ) ) );
				exit;
			}
		}
	}
);

/**
 * Show admin notices after flush or refresh.
 */
add_action(
	'admin_notices',
	function () {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display of admin notice based on query parameter
		if ( ! isset( $_GET['message'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display of admin notice based on query parameter
		$message = sanitize_text_field( wp_unslash( $_GET['message'] ) );
		$notice = '';
		switch ( $message ) {
			case 'accessschema_cache_flushed':
				$notice = 'AccessSchema role cache flushed.';
				break;
			case 'accessschema_cache_refreshed':
				$notice = 'AccessSchema role cache refreshed.';
				break;
			case 'accessschema_cache_failed':
				$notice = 'Failed to refresh AccessSchema roles. Check plugin hook or API response.';
				break;
		}

		if ( $notice ) {
			echo '<div class="notice notice-info is-dismissible"><p>' . esc_html( $notice ) . '</p></div>';
		}
	}
);

/**
 * Enqueue inline styles for the AccessSchema client roles column on Users page.
 *
 * Only loads when at least one client is in remote mode.
 *
 * @since 2.1.1
 */
add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( 'users.php' !== $hook ) {
			return;
		}

		// Skip if all clients are local (server plugin handles the display).
		$registered = apply_filters( 'accessschema_registered_slugs', array() );
		$has_remote = false;
		if ( ! is_wp_error( $registered ) && ! empty( $registered ) ) {
			foreach ( $registered as $client_id => $label ) {
				if ( accessSchema_is_remote_mode( $client_id ) ) {
					$has_remote = true;
					break;
				}
			}
		}
		if ( ! $has_remote ) {
			return;
		}

		$css = '
			.column-accessschema_roles { width: 280px; }
			.asc-client-role-column { font-size: 12px; }
			.asc-client-role-list { display: flex; flex-direction: column; gap: 3px; }
			.asc-client-role-category {
				display: block; font-size: 11px; font-weight: 600;
				text-transform: uppercase; letter-spacing: 0.3px; line-height: 1.6;
			}
			.asc-client-role-item {
				display: block; font-family: monospace; font-size: 12px;
				line-height: 1.4; padding: 0 0 0 4px; color: #50575e; cursor: default;
			}
			.asc-client-cache-info {
				margin-top: 4px; padding-top: 3px; border-top: 1px solid #f0f0f1;
				font-size: 11px; color: #646970;
			}
			.asc-client-cache-info a { font-size: 11px; }
			.asc-client-no-roles { color: #646970; font-style: italic; font-size: 12px; }
		';

		if ( ! wp_style_is( 'asc-client-users-table', 'registered' ) ) {
			wp_register_style( 'asc-client-users-table', false );
			wp_add_inline_style( 'asc-client-users-table', $css );
		}
		wp_enqueue_style( 'asc-client-users-table' );
	}
);
