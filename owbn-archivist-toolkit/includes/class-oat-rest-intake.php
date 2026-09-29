<?php
/**
 * OAT REST — external intake.
 *
 * Creates a real OAT request from a system outside the toolkit (support.owbn.net
 * tickets, for people who will not use OAT directly).
 *
 * Two rules this endpoint exists to enforce:
 *
 *  1. The requester is resolved BY EMAIL. Support, archivist and sso each have
 *     their own user id space; passing an id across sites lands on whoever holds
 *     that number. That conflation produced wrong Council Members and ten bogus
 *     role grants in Sept 2026. Never accept a numeric user id here.
 *
 *  2. Submission runs through OAT_Action_Submit::execute() — the same path the
 *     toolkit's own form uses — so a promoted ticket is indistinguishable from a
 *     native submission: same validation, same rules engine, same routing,
 *     same notifications.
 *
 * @since 1.13.0
 */

defined( 'ABSPATH' ) || exit;

class OAT_REST_Intake {

	const API_NAMESPACE = 'owbn/v1';
	const SOURCE_KEY    = '_source_key';

	public static function register_routes() {
		register_rest_route(
			self::API_NAMESPACE,
			'/oat/entries',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'create_entry' ),
				'permission_callback' => array( 'OAT_REST', 'check_permission' ),
				'args'                => array(
					'form_slug' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
					'requester_email' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
					),
					'fields' => array(
						'required' => true,
						'type'     => 'object',
					),
					'source' => array(
						'required' => false,
						'type'     => 'object',
						'default'  => array(),
					),
					'idempotency_key' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'default'           => '',
					),
					'note' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
						'default'           => '',
					),
				),
			)
		);
	}

	public static function create_entry( $request ) {
		global $wpdb;

		$form_slug = (string) $request->get_param( 'form_slug' );
		$email     = (string) $request->get_param( 'requester_email' );
		$fields_in = (array) $request->get_param( 'fields' );
		$source    = (array) $request->get_param( 'source' );
		$idem      = (string) $request->get_param( 'idempotency_key' );
		$note      = (string) $request->get_param( 'note' );

		// 1. Only forms cleared for external intake.
		if ( ! in_array( $form_slug, OAT_REST_Forms::ticketable_forms(), true ) ) {
			return new WP_Error( 'oat_form_not_available', 'That form cannot be raised from outside the toolkit.', array( 'status' => 400 ) );
		}

		// 2. Idempotency — a retried or double-clicked promote returns the first entry.
		if ( '' !== $idem ) {
			$existing = self::find_by_source_key( $idem );
			if ( $existing ) {
				return rest_ensure_response( self::describe( $existing, false ) );
			}
		}

		if ( '' === $email || ! is_email( $email ) ) {
			return new WP_Error( 'oat_bad_requester', 'A valid requester_email is required.', array( 'status' => 400 ) );
		}

		// 3. Resolve the requester BY EMAIL. 0 when they have no local account.
		$user    = get_user_by( 'email', $email );
		$user_id = $user ? (int) $user->ID : 0;

		// 4. Whitelist to the form's own field definitions, then validate required
		//    only for fields whose condition is actually satisfied.
		$defs = OAT_REST_Forms::fields_for( $form_slug );
		if ( empty( $defs ) ) {
			return new WP_Error( 'oat_form_empty', 'That form has no submit fields defined.', array( 'status' => 500 ) );
		}

		$values  = array();
		$allowed = array();
		foreach ( $defs as $d ) {
			$allowed[ $d['key'] ] = $d;
		}
		foreach ( $fields_in as $k => $v ) {
			$k = (string) $k;
			if ( ! isset( $allowed[ $k ] ) ) {
				continue; // ignore anything not on this form — never let a caller set internal meta
			}
			$values[ $k ] = self::sanitize_value( $allowed[ $k ], $v );
		}

		// Apply field defaults for anything the caller did not send. Several forms
		// carry hidden fields with defaults (cl_registration's action_type is
		// "registration"); the toolkit's own UI posts them, an external caller
		// would not, and the domain validator rejects the entry without them.
		foreach ( $defs as $d ) {
			if ( isset( $values[ $d['key'] ] ) && '' !== $values[ $d['key'] ] ) {
				continue;
			}
			if ( isset( $d['default'] ) && '' !== $d['default'] ) {
				$values[ $d['key'] ] = self::sanitize_value( $d, $d['default'] );
			}
		}

		$missing = array();
		foreach ( $defs as $d ) {
			if ( empty( $d['required'] ) ) {
				continue;
			}
			if ( in_array( $d['type'], array( 'heading', 'notice' ), true ) ) {
				continue;
			}
			if ( ! self::field_applies( $d, $values ) ) {
				continue; // conditional field that is not in play
			}
			if ( ! isset( $values[ $d['key'] ] ) || '' === trim( (string) $values[ $d['key'] ] ) ) {
				$missing[] = $d['key'];
			}
		}
		if ( $missing ) {
			return new WP_Error(
				'oat_missing_fields',
				'Required fields are missing: ' . implode( ', ', $missing ),
				array( 'status' => 422, 'missing' => $missing )
			);
		}

		// 5. Create the entry at the submit step.
		$entry_id = OAT_Entry::create( array(
			'domain'            => OAT_REST_Forms::form_domain( $form_slug ),
			'form_slug'         => $form_slug,
			'status'            => OAT_Constants::STATUS_PENDING,
			'current_step'      => 'submit',
			'originator_id'     => $user_id,
			// Manage Satellites names its chronicle field parent_chronicle; keep the
			// entry associated with the chronicle either way.
			'chronicle_slug'    => isset( $values['chronicle_slug'] ) ? $values['chronicle_slug']
				: ( isset( $values['parent_chronicle'] ) ? $values['parent_chronicle'] : '' ),
			'coordinator_genre' => isset( $values['coordinator_genre'] ) ? $values['coordinator_genre'] : '',
		) );
		if ( ! $entry_id ) {
			return new WP_Error( 'oat_create_failed', 'Could not create the request.', array( 'status' => 500 ) );
		}

		// 6. Field values, then provenance.
		foreach ( $values as $k => $v ) {
			OAT_Entry_Meta::set( $entry_id, $k, $v );
		}
		OAT_Entry_Meta::set( $entry_id, '_source_system', isset( $source['system'] ) ? sanitize_key( $source['system'] ) : 'external' );
		OAT_Entry_Meta::set( $entry_id, '_source_ticket_id', isset( $source['ticket_id'] ) ? (string) absint( $source['ticket_id'] ) : '' );
		OAT_Entry_Meta::set( $entry_id, '_source_ticket_url', isset( $source['ticket_url'] ) ? esc_url_raw( $source['ticket_url'] ) : '' );
		OAT_Entry_Meta::set( $entry_id, '_source_requester_email', $email );
		if ( '' !== $idem ) {
			OAT_Entry_Meta::set( $entry_id, self::SOURCE_KEY, $idem );
		}

		// 7. Same submission path as the toolkit's own form.
		$entry = OAT_Entry::find( $entry_id );
		if ( ! $entry ) {
			return new WP_Error( 'oat_create_failed', 'Request created but could not be loaded.', array( 'status' => 500 ) );
		}

		// A promoted ticket always walks the full chain. Without this, a requester who
		// happens to be a super user would have their ticket fast-tracked and
		// self-approved without review.
		add_filter( 'oat_allow_fast_track', '__return_false', 99 );
		$result = OAT_Action_Submit::execute( $entry, $user_id, array( 'meta' => $values, 'note' => $note ) );
		remove_filter( 'oat_allow_fast_track', '__return_false', 99 );
		if ( is_wp_error( $result ) ) {
			// Roll back rather than leave a half-created request in the queue.
			OAT_Entry::delete_cascade( $entry_id );
			return new WP_Error(
				'oat_rejected',
				$result->get_error_message(),
				array( 'status' => 422, 'rejected_by' => $result->get_error_code() )
			);
		}

		$entry = OAT_Entry::find( $entry_id );
		return rest_ensure_response( self::describe( $entry, true ) );
	}

	protected static function describe( $entry, $created ) {
		return array(
			'created'      => (bool) $created,
			'entry_id'     => (int) $entry->id,
			'status'       => $entry->status,
			'current_step' => $entry->current_step,
			'domain'       => $entry->domain,
			'form_slug'    => $entry->form_slug,
			'entry_url'    => admin_url( 'admin.php?page=owc-oat-entry&entry_id=' . (int) $entry->id ),
		);
	}

	/** @return object|null */
	protected static function find_by_source_key( $key ) {
		global $wpdb;
		$p  = $wpdb->prefix;
		$id = $wpdb->get_var( $wpdb->prepare(
			"SELECT entry_id FROM {$p}oat_entry_meta WHERE meta_key = %s AND meta_value = %s ORDER BY id ASC LIMIT 1",
			self::SOURCE_KEY,
			$key
		) );
		return $id ? OAT_Entry::find( (int) $id ) : null;
	}

	/** Mirrors the client-side rule so both sides agree on what "required" means. */
	protected static function field_applies( $def, $values ) {
		$vw = isset( $def['attributes']['visible_when'] ) ? $def['attributes']['visible_when'] : null;
		if ( is_array( $vw ) ) {
			foreach ( $vw as $key => $allowed ) {
				$got = isset( $values[ $key ] ) ? (string) $values[ $key ] : '';
				if ( ! in_array( $got, array_map( 'strval', (array) $allowed ), true ) ) {
					return false;
				}
			}
		}

		$c = isset( $def['condition'] ) ? $def['condition'] : null;
		if ( is_array( $c ) ) {
			$key = isset( $c['field_key'] ) ? $c['field_key'] : ( isset( $c['meta_key'] ) ? $c['meta_key'] : '' );
			if ( '' !== $key ) {
				$got = isset( $values[ $key ] ) ? (string) $values[ $key ] : '';
				$op  = isset( $c['operator'] ) ? $c['operator'] : '=';
				$val = isset( $c['value'] ) ? $c['value'] : '';
				if ( '!=' === $op ) {
					return $got !== (string) $val;
				}
				if ( 'in' === $op ) {
					return in_array( $got, array_map( 'strval', (array) $val ), true );
				}
				return $got === (string) $val;
			}
		}
		return true;
	}

	protected static function sanitize_value( $def, $value ) {
		if ( is_array( $value ) ) {
			$value = implode( ', ', array_map( 'strval', $value ) );
		}
		$value = (string) $value;
		switch ( $def['type'] ) {
			case 'htmlarea':
				return wp_kses_post( $value );
			case 'textarea':
				return sanitize_textarea_field( $value );
			case 'number':
				return preg_replace( '/[^0-9.\-]/', '', $value );
			default:
				return sanitize_text_field( $value );
		}
	}
}
