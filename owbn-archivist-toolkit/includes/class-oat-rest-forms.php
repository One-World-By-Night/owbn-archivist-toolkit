<?php
/**
 * OAT REST — form definitions for external intake (support ticket forms).
 *
 * Read-only. Publishes the submit-context field definitions for the forms that
 * are allowed to be raised from outside OAT, so a consumer (support.owbn.net)
 * can render them without hard-coding — and without drifting when a form changes.
 *
 * Only the SUBMIT context is exposed. Review / resolve / escalate belong to OAT.
 *
 * @since 1.12.0
 */

defined( 'ABSPATH' ) || exit;

class OAT_REST_Forms {

	const API_NAMESPACE = 'owbn/v1';

	/**
	 * Forms that may be raised from outside OAT.
	 *
	 * ca_reporting and ca_manage_satellites were originally excluded as staff
	 * duties, but the live support queue is full of exactly those requests
	 * ("New Satellite Chronicle for ...", "Chronicle Report 2026") — the people
	 * who avoid OAT file tickets for them. Evidence beat the theory.
	 *
	 * Still excluded: governance_records (archivist/board records, no external demand).
	 */
	public static function ticketable_forms() {
		$slugs = array(
			'cl_registration', 'cl_death', 'cl_transfer', 'cl_fame_registry',
			'cl_ru_request', 'cl_learn_custom_content',
			'player_actions', 'custom_content', 'binding_agreements',
			'da_chronicle', 'da_global', 'disciplinary_actions',
			'ca_reporting', 'ca_manage_satellites',
		);
		return array_values( (array) apply_filters( 'oat_ticketable_forms', $slugs ) );
	}

	public static function register_routes() {
		register_rest_route(
			self::API_NAMESPACE,
			'/oat/forms',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_forms' ),
				'permission_callback' => array( 'OAT_REST', 'check_permission' ),
				'args'                => array(
					'form' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
						'default'           => '',
					),
				),
			)
		);
	}

	/**
	 * GET /owbn/v1/oat/forms[?form=slug]
	 */
	public static function get_forms( $request ) {
		global $wpdb;

		$only    = (string) $request->get_param( 'form' );
		$allowed = self::ticketable_forms();

		if ( '' !== $only ) {
			if ( ! in_array( $only, $allowed, true ) ) {
				return new WP_Error( 'oat_form_not_available', 'That form is not available for external intake.', array( 'status' => 404 ) );
			}
			$allowed = array( $only );
		}

		$p       = $wpdb->prefix;
		$domains = array();
		foreach ( (array) $wpdb->get_results( "SELECT slug, label, archivist_mode FROM {$p}oat_domains WHERE active = 1", ARRAY_A ) as $d ) {
			$domains[ $d['slug'] ] = $d;
		}

		$placeholders = implode( ',', array_fill( 0, count( $allowed ), '%s' ) );
		$forms        = $wpdb->get_results(
			$wpdb->prepare( "SELECT slug, label, description FROM {$p}oat_forms WHERE active = 1 AND slug IN ($placeholders) ORDER BY sort_order, label", $allowed ),
			ARRAY_A
		);

		$out = array();
		foreach ( (array) $forms as $form ) {
			$domain = self::domain_for( $form['slug'], $domains );
			$out[]  = array(
				'slug'         => $form['slug'],
				'label'        => $form['label'],
				'description'  => (string) $form['description'],
				'domain'       => $domain,
				'domain_label' => isset( $domains[ $domain ] ) ? $domains[ $domain ]['label'] : $domain,
				'fields'       => self::submit_fields( $form['slug'], $domain ),
			);
		}

		$payload = array( 'forms' => $out, 'generated_at' => time() );
		// Lets a consumer cache and cheaply detect a change without diffing.
		$payload['version'] = md5( wp_json_encode( $out ) );

		return rest_ensure_response( $payload );
	}

	/**
	 * The fields table keys domain_slug inconsistently — some rows carry the real
	 * domain, some repeat the form slug. Prefer the row whose domain_slug is a real
	 * domain, and keep one row per field_key.
	 */
	/** Domain for a form slug, resolved the same way the listing does. */
	public static function form_domain( $form_slug ) {
		global $wpdb;
		$p       = $wpdb->prefix;
		$domains = array();
		foreach ( (array) $wpdb->get_results( "SELECT slug FROM {$p}oat_domains WHERE active = 1", ARRAY_A ) as $d ) {
			$domains[ $d['slug'] ] = $d;
		}
		return self::domain_for( $form_slug, $domains );
	}

	/** Public accessor so the intake endpoint validates against the same definitions. */
	public static function fields_for( $form_slug ) {
		return self::submit_fields( $form_slug, self::form_domain( $form_slug ) );
	}

	public static function submit_fields( $form_slug, $domain ) {
		global $wpdb;
		$p = $wpdb->prefix;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT domain_slug, field_key, field_type, label, required, sort_order,
				        options_json, placeholder, help_text, default_value, condition_json, attributes_json
				   FROM {$p}oat_form_fields
				  WHERE form_slug = %s AND context = 'submit' AND active = 1
				  ORDER BY sort_order ASC, id ASC",
				$form_slug
			),
			ARRAY_A
		);

		$best = array();
		foreach ( (array) $rows as $r ) {
			$key = $r['field_key'];
			if ( isset( $best[ $key ] ) && $best[ $key ]['domain_slug'] === $domain ) {
				continue; // already have the authoritative row
			}
			if ( ! isset( $best[ $key ] ) || $r['domain_slug'] === $domain ) {
				$best[ $key ] = $r;
			}
		}

		$fields = array();
		foreach ( $best as $r ) {
			$fields[] = array(
				'key'        => $r['field_key'],
				'type'       => $r['field_type'],
				'label'      => $r['label'],
				'required'   => ( '1' === (string) $r['required'] ),
				'help'       => (string) $r['help_text'],
				'placeholder'=> (string) $r['placeholder'],
				'default'    => (string) $r['default_value'],
				'sort_order' => (int) $r['sort_order'],
				'options'    => self::normalize_options( $r['options_json'] ),
				'condition'  => self::decode( $r['condition_json'] ),
				'attributes' => self::decode( $r['attributes_json'] ),
			);
		}

		usort( $fields, function ( $a, $b ) {
			return $a['sort_order'] <=> $b['sort_order'];
		} );

		return $fields;
	}

	/**
	 * options_json appears as a flat map, a list, or a grouped map of lists.
	 * Normalise all three to a list of {value,label,group}.
	 */
	protected static function normalize_options( $json ) {
		$raw = self::decode( $json );
		if ( empty( $raw ) ) {
			return array();
		}
		$out = array();
		if ( is_array( $raw ) && array_values( $raw ) === $raw ) {
			foreach ( $raw as $v ) {
				if ( is_array( $v ) ) {
					$out[] = array(
						'value' => (string) ( $v['value'] ?? $v['label'] ?? '' ),
						'label' => (string) ( $v['label'] ?? $v['value'] ?? '' ),
						'group' => '',
					);
				} else {
					$out[] = array( 'value' => (string) $v, 'label' => (string) $v, 'group' => '' );
				}
			}
			return $out;
		}
		foreach ( (array) $raw as $k => $v ) {
			if ( is_array( $v ) ) { // grouped: group => [items]
				foreach ( $v as $ik => $iv ) {
					$val = is_array( $iv ) ? ( $iv['value'] ?? '' ) : ( is_string( $ik ) ? $ik : $iv );
					$lab = is_array( $iv ) ? ( $iv['label'] ?? $val ) : $iv;
					$out[] = array( 'value' => (string) $val, 'label' => (string) $lab, 'group' => (string) $k );
				}
			} else {
				$out[] = array( 'value' => (string) $k, 'label' => (string) $v, 'group' => '' );
			}
		}
		return $out;
	}

	protected static function decode( $json ) {
		if ( empty( $json ) ) {
			return null;
		}
		$d = json_decode( (string) $json, true );
		return ( JSON_ERROR_NONE === json_last_error() ) ? $d : null;
	}

	/** ca_* -> chronicle_actions, cl_* -> character_lifecycle, da_* -> disciplinary_actions. */
	protected static function domain_for( $form_slug, $domains ) {
		$map = array( 'ca_' => 'chronicle_actions', 'cl_' => 'character_lifecycle', 'da_' => 'disciplinary_actions' );
		foreach ( $map as $prefix => $domain ) {
			if ( 0 === strpos( $form_slug, $prefix ) ) {
				return $domain;
			}
		}
		return isset( $domains[ $form_slug ] ) ? $form_slug : '';
	}
}
