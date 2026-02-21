<?php

/** File: includes/hooks/cache.php
 * Text Domain: accessschema-client
 * version 2.4.0
 *
 * @author greghacke
 * Function: Cache user roles on login
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_login',
	function ( $user_login, $user ) {
		if ( ! is_a( $user, 'WP_User' ) ) {
			return;
		}

		$registered_slugs = apply_filters( 'accessschema_registered_slugs', array() );

		if ( ! is_array( $registered_slugs ) ) {
			return;
		}

		// Use the first available registered client to refresh roles.
		// All clients share the same AccessSchema server, so roles are
		// stored under a shared (non-prefixed) cache key.
		foreach ( $registered_slugs as $client_id => $label ) {
			$result = apply_filters( 'accessschema_client_refresh_roles', null, $user, $client_id );

			if ( is_array( $result ) && isset( $result['roles'] ) ) {
				update_user_meta( $user->ID, 'accessschema_cached_roles', $result['roles'] );
				update_user_meta( $user->ID, 'accessschema_cached_roles_timestamp', time() );
				break; // Shared cache — one successful refresh is enough.
			}
		}
	},
	10,
	2
);
