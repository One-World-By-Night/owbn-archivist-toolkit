<?php

/** File: includes/shortcodes/detail.php
 * Text Domain: accessschema-client
 * version 2.0.4
 *
 * @author greghacke
 * Function: Define shortcodes for accessSchema client to handle user access based on roles or patterns.
 */

defined( 'ABSPATH' ) || exit;


add_action(
	'init',
	function () {
		$client_ids = apply_filters( 'accessschema_registered_slugs', array() );

		foreach ( $client_ids as $client_id => $label ) {
			add_shortcode(
				"access_schema_{$client_id}",
				function ( $atts, $content = null ) use ( $client_id ) {
					if ( ! is_user_logged_in() ) {
						return '';
					}

					$user = wp_get_current_user();

					$atts = shortcode_atts(
						array(
							'role'     => '',       // Single role path (exact or pattern)
							'any'      => '',       // Comma-separated list of paths/patterns
							'wildcard' => 'false',  // true/false for wildcard/glob mode
							'fallback' => '',       // Optional fallback if user doesn't match
						),
						$atts,
						"access_schema_{$client_id}"
					);

					$wildcard = filter_var( $atts['wildcard'], FILTER_VALIDATE_BOOLEAN );
					$email    = $user->user_email;

					// === Handle 'any' multiple patterns ===
					if ( ! empty( $atts['any'] ) ) {
						$patterns = array_map( 'trim', explode( ',', $atts['any'] ) );
						if ( accessSchema_client_remote_user_matches_any( $email, $patterns, $client_id ) ) {
							return do_shortcode( $content );
						}
						return $atts['fallback'] ?? '';
					}

					// === Handle single role ===
					$role = trim( $atts['role'] );
					if ( ! $role ) {
						return '';
					}

					if ( $wildcard ) {
						if ( accessSchema_client_roles_match_pattern_from_email( $email, $role, $client_id ) ) {
							return do_shortcode( $content );
						}
					} else {
						$granted = accessSchema_client_remote_check_access( $email, $role, $client_id, false );
						if ( ! is_wp_error( $granted ) && $granted ) {
							return do_shortcode( $content );
						}
					}

					return $atts['fallback'] ?? '';
				}
			);
		}
	}
);
