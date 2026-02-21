<?php

/** File: includes/core/client-api.php
 * Text Domain: accessschema-client
 * version 2.4.0
 *
 * @author greghacke
 * Function: This file contains the core client API functions for AccessSchema.
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'as_client_option_key' ) ) {
	function as_client_option_key( $client_id, $key ) {
		return "{$client_id}_accessschema_{$key}";
	}
}

if ( ! function_exists( 'accessSchema_is_remote_mode' ) ) {
	function accessSchema_is_remote_mode( $client_id ) {
		return 'remote' === get_option( as_client_option_key( $client_id, 'mode' ), 'remote' );
	}
}

if ( ! function_exists( 'accessSchema_client_get_remote_url' ) ) {
	function accessSchema_client_get_remote_url( $client_id ) {
		$url = trim( get_option( "{$client_id}_accessschema_client_url" ) );
		// Strip REST API path if stored in legacy format (pre-2.1.x).
		$url = preg_replace( '#/wp-json/access-schema/v1/?.*$#', '', $url );
		return rtrim( $url, '/' );
	}
}

if ( ! function_exists( 'accessSchema_client_get_remote_key' ) ) {
	function accessSchema_client_get_remote_key( $client_id ) {
		return trim( get_option( "{$client_id}_accessschema_client_key" ) );
	}
}

if ( ! function_exists( 'accessSchema_client_remote_post' ) ) {
	/**
	 * Send a POST request to the AccessSchema API endpoint.
	 *
	 * @param string $client_id     The unique plugin slug.
	 * @param string $endpoint The API endpoint path (e.g., 'roles', 'grant', 'revoke').
	 * @param array  $body     JSON body parameters.
	 * @return array|WP_Error  Response array or error.
	 */
	function accessSchema_client_remote_post( $client_id, $endpoint, array $body ) {
		if ( ! is_string( $client_id ) ) {
			error_log( '[AccessSchema Client] FATAL: Non-string slug in remote_post: ' . esc_html( wp_json_encode( $client_id ) ) );
			return new WP_Error( 'invalid_slug', 'Plugin slug must be a string' );
		}

		$url_base = accessSchema_client_get_remote_url( $client_id );
		$key      = accessSchema_client_get_remote_key( $client_id );

		if ( ! $url_base || ! $key ) {
			error_log( '[AccessSchema Client] ERROR: Remote URL or API key is not set for slug: ' . esc_html( $client_id ) );
			return new WP_Error( 'config_error', 'Remote URL or API key is not set for plugin: ' . esc_html( $client_id ) );
		}

		$url = trailingslashit( $url_base ) . 'wp-json/access-schema/v1/' . ltrim( $endpoint, '/' );

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
					'x-api-key'    => $key,
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( '[AccessSchema Client] HTTP POST ERROR: ' . $response->get_error_message() );
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$data   = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data ) ) {
			error_log( '[AccessSchema Client] ERROR: Invalid JSON response for slug ' . esc_html( $client_id ) );
			return new WP_Error( 'api_response_invalid', 'Invalid JSON from API.', array( 'slug' => $client_id ) );
		}

		if ( 200 !== $status && 201 !== $status ) {
			error_log( '[AccessSchema Client] ERROR: API returned HTTP ' . $status . ' for slug ' . esc_html( $client_id ) );
			return new WP_Error(
				'api_error',
				'Remote API returned HTTP ' . $status,
				array(
					'slug' => $client_id,
					'data' => $data,
				)
			);
		}

		return $data;
	}
}

if ( ! function_exists( 'accessSchema_client_remote_get' ) ) {
	/**
	 * Send a GET request to the AccessSchema API endpoint.
	 *
	 * @param string $client_id The unique plugin slug.
	 * @param string $endpoint  The API endpoint path (e.g., 'roles/all').
	 * @return array|WP_Error   Response array or error.
	 */
	function accessSchema_client_remote_get( $client_id, $endpoint ) {
		if ( ! is_string( $client_id ) ) {
			return new WP_Error( 'invalid_slug', 'Plugin slug must be a string' );
		}

		$url_base = accessSchema_client_get_remote_url( $client_id );
		$key      = accessSchema_client_get_remote_key( $client_id );

		if ( ! $url_base || ! $key ) {
			return new WP_Error( 'config_error', 'Remote URL or API key is not set' );
		}

		$url = trailingslashit( $url_base ) . 'wp-json/access-schema/v1/' . ltrim( $endpoint, '/' );

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'x-api-key' => $key,
				),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$data   = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'api_response_invalid', 'Invalid JSON from API' );
		}

		if ( 200 !== $status ) {
			return new WP_Error( 'api_error', 'Remote API returned HTTP ' . $status );
		}

		return $data;
	}
}

if ( ! function_exists( 'accessSchema_client_remote_get_roles_by_email' ) ) {
	function accessSchema_client_remote_get_roles_by_email( $email, $client_id ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			error_log( '[AccessSchema Client] No user found with email: ' . sanitize_email( $email ) );
		}

		$is_remote = accessSchema_is_remote_mode( $client_id );

		if ( ! $is_remote ) {
			if ( ! $user ) {
				return new WP_Error( 'user_not_found', 'User not found.', array( 'status' => 404 ) );
			}

			return accessSchema_client_local_post( 'roles', array( 'email' => sanitize_email( $email ) ) );
		}

		// Check cache first.
		if ( $user ) {
			$cache_key = 'accessschema_cached_roles';
			$cached    = get_user_meta( $user->ID, $cache_key, true );

			if ( is_array( $cached ) && ! empty( $cached ) ) {
				return array( 'roles' => $cached );
			}
		}

		error_log( '[AccessSchema Client] No cache — requesting remote roles for ' . sanitize_email( $email ) );

		$response = accessSchema_client_remote_post( $client_id, 'roles', array( 'email' => sanitize_email( $email ) ) );

		if (
			! is_wp_error( $response ) &&
			is_array( $response ) &&
			isset( $response['roles'] ) &&
			is_array( $response['roles'] ) &&
			$user
		) {
			update_user_meta( $user->ID, 'accessschema_cached_roles', $response['roles'] );
			update_user_meta( $user->ID, 'accessschema_cached_roles_timestamp', time() );
		} elseif ( is_wp_error( $response ) ) {
			error_log( '[AccessSchema Client] Failed to retrieve roles remotely: ' . $response->get_error_message() );
		}

		return $response;
	}
}

if ( ! function_exists( 'accessSchema_client_remote_grant_role' ) ) {
	function accessSchema_client_remote_grant_role( $email, $role_path, $client_id ) {
		$user = get_user_by( 'email', $email );

		$payload = array(
			'email'     => sanitize_email( $email ),
			'role_path' => sanitize_text_field( $role_path ),
		);

		$result = accessSchema_is_remote_mode( $client_id )
			? accessSchema_client_remote_post( $client_id, 'grant', $payload )
			: accessSchema_client_local_post( 'grant', $payload );

		if ( $user ) {
			delete_user_meta( $user->ID, 'accessschema_cached_roles' );
			delete_user_meta( $user->ID, 'accessschema_cached_roles_timestamp' );
		}

		return $result;
	}
}

if ( ! function_exists( 'accessSchema_client_remote_revoke_role' ) ) {
	function accessSchema_client_remote_revoke_role( $email, $role_path, $client_id ) {
		$user = get_user_by( 'email', $email );

		$payload = array(
			'email'     => sanitize_email( $email ),
			'role_path' => sanitize_text_field( $role_path ),
		);

		$result = accessSchema_is_remote_mode( $client_id )
			? accessSchema_client_remote_post( $client_id, 'revoke', $payload )
			: accessSchema_client_local_post( 'revoke', $payload );

		if ( $user ) {
			delete_user_meta( $user->ID, 'accessschema_cached_roles' );
			delete_user_meta( $user->ID, 'accessschema_cached_roles_timestamp' );
		}

		return $result;
	}
}

if ( ! function_exists( 'accessSchema_client_get_all_roles' ) ) {
	/**
	 * Get all roles from the AccessSchema server.
	 *
	 * @param string $client_id The unique plugin slug.
	 * @return array|WP_Error   Array with 'total', 'roles', and 'hierarchy' or error.
	 */
	function accessSchema_client_get_all_roles( $client_id ) {
		if ( ! is_string( $client_id ) || '' === trim( $client_id ) ) {
			return new WP_Error( 'invalid_slug', 'Plugin slug must be a non-empty string' );
		}

		if ( accessSchema_is_remote_mode( $client_id ) ) {
			return accessSchema_client_remote_get( $client_id, 'roles/all' );
		}

		$request  = new WP_REST_Request( 'GET', '/access-schema/v1/roles/all' );
		$response = rest_do_request( $request );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response->get_data();
	}
}

if ( ! function_exists( 'accessSchema_refresh_roles_for_user' ) ) {
	/**
	 * Refresh cached roles for a user from the AccessSchema server.
	 *
	 * Supports both remote (REST API) and local (direct function call) modes.
	 *
	 * @since 2.0.0
	 *
	 * @param WP_User $user      The WordPress user object.
	 * @param string  $client_id The client instance identifier.
	 * @return array|WP_Error The roles response array, or WP_Error on failure.
	 */
	function accessSchema_refresh_roles_for_user( $user, $client_id ) {
		if ( ! ( $user instanceof WP_User ) ) {
			return new WP_Error( 'invalid_user', 'User object is invalid.' );
		}

		$email = $user->user_email;

		if ( accessSchema_is_remote_mode( $client_id ) ) {
			$response = accessSchema_client_remote_get_roles_by_email( $email, $client_id );
		} else {
			$response = accessSchema_client_local_post( 'roles', array( 'email' => $email ) );
		}

		if (
			! is_wp_error( $response ) &&
			isset( $response['roles'] ) &&
			is_array( $response['roles'] )
		) {
			update_user_meta( $user->ID, 'accessschema_cached_roles', $response['roles'] );
			update_user_meta( $user->ID, 'accessschema_cached_roles_timestamp', time() );
			return $response;
		}

		return new WP_Error( 'refresh_failed', 'Could not refresh roles.' );
	}
}

if ( ! function_exists( 'accessSchema_client_remote_check_access' ) ) {
	/**
	 * Check if the given user email has access to a specific role path in a plugin slug.
	 *
	 * @param string $email            The user's email address.
	 * @param string $role_path        The role path to check (e.g., "Chronicle/KONY/HST").
	 * @param string $client_id        The plugin slug (e.g., 'owbn_board').
	 * @param bool   $include_children Whether to check subroles.
	 *
	 * @return bool|WP_Error True if access granted, false if not, or WP_Error on failure.
	 */
	function accessSchema_client_remote_check_access( $email, $role_path, $client_id, $include_children = true ) {
		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', 'Invalid email address.' );
		}

		if ( ! is_string( $role_path ) || '' === trim( $role_path ) ) {
			return new WP_Error( 'invalid_role_path', 'Role path must be a non-empty string.' );
		}

		if ( ! is_string( $client_id ) || '' === trim( $client_id ) ) {
			return new WP_Error( 'invalid_slug', 'Plugin slug must be a non-empty string.' );
		}

		$payload = array(
			'email'            => $email,
			'role_path'        => sanitize_text_field( $role_path ),
			'include_children' => (bool) $include_children,
		);

		if ( ! function_exists( 'accessSchema_is_remote_mode' ) ) {
			return new WP_Error( 'missing_dependency', 'accessSchema_is_remote_mode() is not available.' );
		}

		if ( ! function_exists( 'accessSchema_client_local_post' ) || ! function_exists( 'accessSchema_client_remote_post' ) ) {
			return new WP_Error( 'missing_dependency', 'Required AccessSchema client functions are not available.' );
		}

		$data = accessSchema_is_remote_mode( $client_id )
			? accessSchema_client_remote_post( $client_id, 'check', $payload )
			: accessSchema_client_local_post( 'check', $payload );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		if ( ! is_array( $data ) || ! array_key_exists( 'granted', $data ) ) {
			return new WP_Error( 'invalid_response', 'Invalid response from access check.' );
		}

		return (bool) $data['granted'];
	}
}

if ( ! function_exists( 'asc_hook_user_has_cap_filter' ) ) {
	/**
	 * Map WordPress capabilities to AccessSchema roles, or allow group-level access.
	 *
	 * Iterates ALL registered client instances so that capabilities granted
	 * by any instance are honoured — no reliance on the ASC_PREFIX constant.
	 *
	 * @param array    $allcaps All capabilities for the user.
	 * @param string[] $caps    Requested capabilities.
	 * @param array    $args    [0] => requested cap, [1] => object_id (optional), etc.
	 * @param WP_User  $user    WP_User object.
	 * @return array            Modified capabilities.
	 */
	function asc_hook_user_has_cap_filter( $allcaps, $caps, $args, $user ) {
		$requested_cap = $caps[0] ?? null;
		if ( ! $requested_cap || ! $user instanceof WP_User ) {
			return $allcaps;
		}

		$email = $user->user_email;
		if ( ! is_email( $email ) ) {
			return $allcaps;
		}

		$registered = apply_filters( 'accessschema_registered_slugs', array() );
		if ( empty( $registered ) ) {
			return $allcaps;
		}

		foreach ( $registered as $client_id => $label ) {
			$mode = get_option( "{$client_id}_accessschema_mode", 'remote' );
			if ( 'none' === $mode ) {
				continue;
			}

			// Group-level check for asc_has_access_to_group.
			if ( 'asc_has_access_to_group' === $requested_cap ) {
				$group_path = $args[0] ?? null;
				if ( ! $group_path ) {
					continue;
				}

				$roles_data = ( 'local' === $mode )
					? accessSchema_client_local_get_roles_by_email( $email, $client_id )
					: accessSchema_client_remote_get_roles_by_email( $email, $client_id );

				$roles = $roles_data['roles'] ?? array();

				$has_access = in_array( $group_path, $roles, true ) ||
								! empty( preg_grep( '#^' . preg_quote( $group_path, '#' ) . '/#', $roles ) );

				if ( $has_access ) {
					$allcaps[ $requested_cap ] = true;
					return $allcaps;
				}
				continue;
			}

			// Mapped capability check.
			$role_map = get_option( "{$client_id}_capability_map", array() );
			if ( empty( $role_map[ $requested_cap ] ) ) {
				continue;
			}

			foreach ( (array) $role_map[ $requested_cap ] as $raw_path ) {
				$role_path = asc_expand_role_path( $raw_path );

				$granted = ( 'local' === $mode )
					? accessSchema_client_local_check_access( $email, $role_path, $client_id )
					: accessSchema_client_remote_check_access( $email, $role_path, $client_id, true );

				if ( is_wp_error( $granted ) ) {
					continue;
				}

				if ( true === $granted ) {
					$allcaps[ $requested_cap ] = true;
					return $allcaps;
				}
			}
		}

		return $allcaps;
	}
	// Register the filter once — inside the guard so duplicate loads skip it.
	add_filter( 'user_has_cap', 'asc_hook_user_has_cap_filter', 10, 4 );
}

if ( ! function_exists( 'asc_expand_role_path' ) ) {
	/**
	 * Expand dynamic role path placeholders like `$slug`.
	 */
	function asc_expand_role_path( $raw_path ) {
		$slug = get_query_var( 'slug' ) ? get_query_var( 'slug' ) : '';
		return str_replace( '$slug', sanitize_key( $slug ), $raw_path );
	}
}


if ( ! function_exists( 'accessSchema_client_local_post' ) ) {
	/**
	 * Call a server API function directly in local mode.
	 *
	 * Bypasses REST API transport by calling the server callback function
	 * with a WP_REST_Request containing the body as JSON params.
	 *
	 * @since 2.0.0
	 *
	 * @param string $endpoint The API endpoint name (roles, grant, revoke, check).
	 * @param array  $body     The request body parameters.
	 * @return array|WP_Error The response data array, or WP_Error on failure.
	 */
	function accessSchema_client_local_post( $endpoint, array $body ) {
		$function_map = array(
			'roles'  => 'accessSchema_api_get_roles',
			'grant'  => 'accessSchema_api_grant_role',
			'revoke' => 'accessSchema_api_revoke_role',
			'check'  => 'accessSchema_api_check_permission',
		);

		if ( ! isset( $function_map[ $endpoint ] ) ) {
			return new WP_Error( 'invalid_local_endpoint', 'Unrecognized local endpoint.' );
		}

		if ( ! function_exists( $function_map[ $endpoint ] ) ) {
			return new WP_Error( 'missing_server_function', 'AccessSchema server plugin is not active.' );
		}

		$request = new WP_REST_Request( 'POST', '/access-schema/v1/' . ltrim( $endpoint, '/' ) );
		// Set as JSON body so server functions can use get_json_params().
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $body ) );

		$response = call_user_func( $function_map[ $endpoint ], $request );
		return ( $response instanceof WP_Error ) ? $response : $response->get_data();
	}
}

if ( ! function_exists( 'accessSchema_client_local_get_roles_by_email' ) ) {
	/**
	 * Get user roles via local server function call.
	 *
	 * @since 2.1.1
	 *
	 * @param string $email     The user email address.
	 * @param string $client_id The client instance identifier.
	 * @return array The response with 'roles' key, or empty array on failure.
	 */
	function accessSchema_client_local_get_roles_by_email( $email, $client_id ) {
		$result = accessSchema_client_local_post( 'roles', array( 'email' => $email ) );
		if ( is_wp_error( $result ) ) {
			return array( 'roles' => array() );
		}
		return $result;
	}
}

if ( ! function_exists( 'accessSchema_client_local_check_access' ) ) {
	/**
	 * Check user access to a role path via local server function call.
	 *
	 * @since 2.1.1
	 *
	 * @param string $email     The user email address.
	 * @param string $role_path The role path to check.
	 * @param string $client_id The client instance identifier.
	 * @return bool Whether the user has access.
	 */
	function accessSchema_client_local_check_access( $email, $role_path, $client_id ) {
		$result = accessSchema_client_local_post(
			'check',
			array(
				'email'            => $email,
				'role_path'        => $role_path,
				'include_children' => true,
			)
		);
		if ( is_wp_error( $result ) ) {
			return false;
		}
		return ! empty( $result['has_access'] );
	}
}

do_action( 'accessSchema_client_ready' );
