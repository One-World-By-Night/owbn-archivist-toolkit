<?php
/**
 * OAT Record Snapshot
 *
 * Captures a human-readable JSON snapshot of an entry upon final approval.
 * Resolves all IDs to display names, maps field keys to labels, and
 * stores the result as _oat_record_snapshot entry meta.
 *
 * @package OAT
 */

defined( 'ABSPATH' ) || exit;

class OAT_Record_Snapshot {

	/**
	 * Wire up the hook.
	 */
	public static function init() {
		add_action( 'oat_entry_approved', array( __CLASS__, 'capture' ), 10, 3 );
	}

	/**
	 * Capture a snapshot of the approved entry.
	 *
	 * @param int   $entry_id Entry ID.
	 * @param int   $user_id  Approving user ID.
	 * @param array $data     Action data.
	 */
	public static function capture( $entry_id, $user_id, $data ) {
		$entry = OAT_Entry::get( (int) $entry_id );
		if ( ! $entry ) {
			return;
		}

		$snapshot = array(
			'entry_id'   => (int) $entry->id,
			'domain'     => $entry->domain,
			'status'     => $entry->status,
			'captured_at' => current_time( 'mysql' ),
		);

		// Originator.
		$originator = get_userdata( (int) $entry->originator_id );
		$snapshot['originator'] = $originator
			? array( 'id' => $originator->ID, 'name' => $originator->display_name, 'email' => $originator->user_email )
			: array( 'id' => (int) $entry->originator_id, 'name' => '', 'email' => '' );

		// Domain label.
		$snapshot['domain_label'] = OAT_Domain_Registry::get_label( $entry->domain ) ?: $entry->domain;

		// Chronicle.
		if ( ! empty( $entry->chronicle_slug ) ) {
			$title = function_exists( 'owc_entity_get_title' )
				? owc_entity_get_title( 'chronicle', $entry->chronicle_slug )
				: '';
			$snapshot['chronicle'] = array(
				'slug'  => $entry->chronicle_slug,
				'title' => $title ?: $entry->chronicle_slug,
			);
		}

		// Coordinator.
		if ( ! empty( $entry->coordinator_genre ) ) {
			$title = function_exists( 'owc_entity_get_title' )
				? owc_entity_get_title( 'coordinator', $entry->coordinator_genre )
				: '';
			$snapshot['coordinator'] = array(
				'genre' => $entry->coordinator_genre,
				'title' => $title ?: $entry->coordinator_genre,
			);
		}

		// Entry meta with field labels.
		$meta_rows = OAT_Entry_Meta::get_all( (int) $entry->id );
		$fields    = OAT_Form_Field::get_fields( $entry->domain, 'submit' );
		$label_map = array();
		if ( is_array( $fields ) ) {
			foreach ( $fields as $f ) {
				$key = is_object( $f ) ? $f->field_key : ( isset( $f['field_key'] ) ? $f['field_key'] : '' );
				$lbl = is_object( $f ) ? $f->label : ( isset( $f['label'] ) ? $f['label'] : '' );
				if ( $key ) {
					$label_map[ $key ] = $lbl;
				}
			}
		}

		$snapshot['fields'] = array();
		if ( is_array( $meta_rows ) ) {
			foreach ( $meta_rows as $m ) {
				$key = is_object( $m ) ? $m->meta_key : $m['meta_key'];
				$val = is_object( $m ) ? $m->meta_value : $m['meta_value'];
				if ( strpos( $key, '_oat_' ) === 0 ) {
					continue; // Skip internal meta.
				}
				$snapshot['fields'][] = array(
					'key'   => $key,
					'label' => isset( $label_map[ $key ] ) ? $label_map[ $key ] : $key,
					'value' => $val,
				);
			}
		}

		// Timeline with resolved actor names.
		$timeline = OAT_Timeline::for_entry( (int) $entry->id );
		$snapshot['timeline'] = array();
		if ( is_array( $timeline ) ) {
			foreach ( $timeline as $event ) {
				$actor_id   = is_object( $event ) ? $event->actor_id : ( isset( $event['actor_id'] ) ? $event['actor_id'] : 0 );
				$actor_name = '';
				if ( $actor_id ) {
					$actor_user = get_userdata( (int) $actor_id );
					$actor_name = $actor_user ? $actor_user->display_name : '#' . $actor_id;
				} else {
					$actor_name = 'System';
				}

				$snapshot['timeline'][] = array(
					'action'     => is_object( $event ) ? $event->action_type : ( isset( $event['action_type'] ) ? $event['action_type'] : '' ),
					'actor'      => $actor_name,
					'step'       => is_object( $event ) ? $event->step : ( isset( $event['step'] ) ? $event['step'] : '' ),
					'note'       => is_object( $event ) ? $event->note : ( isset( $event['note'] ) ? $event['note'] : '' ),
					'created_at' => is_object( $event ) ? $event->created_at : ( isset( $event['created_at'] ) ? $event['created_at'] : '' ),
				);
			}
		}

		// Store snapshot.
		OAT_Entry_Meta::set( (int) $entry->id, '_oat_record_snapshot', wp_json_encode( $snapshot ) );
	}
}
