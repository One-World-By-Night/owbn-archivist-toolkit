<?php

/** File: includes/core/client-init.php
 * Text Domain: accessschema-client
 * version 2.4.0
 *
 * @author greghacke
 * Function: Register this client instance and set up per-instance hooks.
 */

defined( 'ABSPATH' ) || exit;

// Use instance variables inherited from accessSchema-client.php.
// Fall back to constants for backward compatibility with old-style prefix.php.
if ( ! isset( $asc_instance_prefix ) || empty( $asc_instance_prefix ) ) {
	if ( defined( 'ASC_PREFIX' ) ) {
		$asc_instance_prefix = ASC_PREFIX;
	} else {
		error_log( '[AS] ERROR: $asc_instance_prefix not set and ASC_PREFIX not defined.' );
		return;
	}
}
if ( ! isset( $asc_instance_label ) || empty( $asc_instance_label ) ) {
	$asc_instance_label = defined( 'ASC_LABEL' ) ? ASC_LABEL : $asc_instance_prefix;
}

// === Build client ID and label ===
$client_id = strtolower( str_replace( '_', '-', $asc_instance_prefix ) );
$label     = ucwords( strtolower( str_replace( '_', ' ', $asc_instance_prefix ) ) );

// === Register this instance ===
if ( ! function_exists( 'accessSchema_register_client_plugin' ) ) {
function accessSchema_register_client_plugin( $client_id, $label ) {
	add_filter(
		'accessschema_registered_slugs',
		function ( $client_ids ) use ( $client_id, $label ) {
			if ( ! isset( $client_ids[ $client_id ] ) ) {
				$client_ids[ $client_id ] = $label;
			}
			return $client_ids;
		}
	);

	add_filter(
		'accessschema_client_refresh_roles',
		function ( $result, $user, $filter_slug ) use ( $client_id ) {
			if ( ! is_string( $filter_slug ) ) {
				error_log( '[AS] WARN: Non-string slug encountered in refresh_roles: ' . print_r( $filter_slug, true ) );
				return $result;
			}
			if ( $client_id !== $filter_slug ) {
				return $result;
			}
			return accessSchema_refresh_roles_for_user( $user, $client_id );
		},
		10,
		3
	);

	// Provide role data to utility functions via filter.
	add_filter(
		'accessschema_get_roles_for_slug',
		function ( $result, $email, $filter_slug ) use ( $client_id ) {
			if ( $client_id !== $filter_slug ) {
				return $result;
			}
			return accessSchema_client_remote_get_roles_by_email( $email, $client_id );
		},
		10,
		3
	);
}
}

accessSchema_register_client_plugin( $client_id, $label );

if ( function_exists( 'accessSchema_client_register_render_admin' ) ) {
	accessSchema_client_register_render_admin( $client_id, $label );
}
