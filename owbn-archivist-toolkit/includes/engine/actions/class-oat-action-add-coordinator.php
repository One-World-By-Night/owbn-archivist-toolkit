<?php

defined( 'ABSPATH' ) || exit;

/**
 * A coordinator reviewing a request (or the Archivist) asks other coordinator offices to approve it too, when it reaches into
 * their genre. The offices join the request's named coordinators; when the request is at a Coordinator step their coordinators
 * are assigned and emailed now, and each office must approve before the request moves on.
 */
class OAT_Action_Add_Coordinator {
    public static function execute( $entry, $user_id, $data = array() ) {
        if ( empty( $data['note'] ) ) {
            return new WP_Error( 'missing_note', 'A note is required: say why these coordinators are needed.' );
        }
        $raw   = isset( $data['coordinators'] ) ? $data['coordinators'] : '';
        $slugs = is_array( $raw ) ? $raw : explode( ',', (string) $raw );
        $known = OAT_Workflow_Engine::coordinator_slugs();
        $wanted = array();
        foreach ( $slugs as $slug ) {
            $slug = strtolower( trim( (string) $slug ) );
            if ( '' !== $slug && isset( $known[ $slug ] ) ) {
                $wanted[ $slug ] = true;
            }
        }
        if ( ! $wanted ) {
            return new WP_Error( 'missing_coordinators', 'Choose at least one coordinator.' );
        }
        $new = array_values( array_diff( array_keys( $wanted ), OAT_Workflow_Engine::coordinator_offices( $entry ) ) );
        if ( ! $new ) {
            return new WP_Error( 'already_involved', 'Those coordinators are already on this request.' );
        }

        $entry_id = (int) $entry->id;
        $merged   = array_values( array_unique( array_merge( OAT_Workflow_Engine::involved_coordinators( $entry ), $new ) ) );
        OAT_Entry_Meta::set( $entry_id, 'involved_coordinators', wp_json_encode( $merged ) );
        if ( '1' !== (string) OAT_Entry_Meta::get( $entry_id, 'requires_coord' ) ) {
            OAT_Entry_Meta::set( $entry_id, 'requires_coord', '1' );
        }
        if ( '' === (string) OAT_Entry_Meta::get( $entry_id, 'regulation_level' ) ) {
            OAT_Entry_Meta::set( $entry_id, 'regulation_level', 'coordinator_approval' );
        }
        if ( class_exists( 'OAT_Entry_Coordinator' ) ) {
            foreach ( $new as $slug ) {
                OAT_Entry_Coordinator::add( $entry_id, $slug, 'added' );
            }
        }

        // At a Coordinator step the new offices' coordinators are asked now; earlier, they are asked when the step arrives.
        $step        = $entry->current_step;
        $step_config = OAT_Workflow_Engine::get_step_config( $entry );
        $assigned    = array();
        $nobody      = array();
        if ( $step_config && ! empty( $step_config['assignee_role'] ) && false !== strpos( $step_config['assignee_role'], '{coordinator_genre}' ) ) {
            $already = array();
            foreach ( OAT_Assignee::for_entry_step( $entry_id, $step ) as $a ) {
                $already[ (int) $a->user_id ] = true;
            }
            foreach ( $new as $slug ) {
                $holders = OAT_Workflow_Engine::resolve_assignees( $entry, 'Coordinator/' . $slug . '/Coordinator' );
                if ( ! $holders ) {
                    $nobody[] = $slug;
                }
                foreach ( $holders as $uid ) {
                    if ( isset( $already[ (int) $uid ] ) ) {
                        continue;
                    }
                    $already[ (int) $uid ] = true;
                    OAT_Assignee::assign( $entry_id, (int) $uid, $step );
                    $assigned[] = (int) $uid;
                }
            }
            if ( $assigned ) {
                do_action( 'oat_entry_step_assigned', $entry_id, $step, array_values( array_unique( $assigned ) ) );
            }
        }

        $titles = array();
        foreach ( $new as $slug ) {
            $title    = function_exists( 'owc_entity_get_title' ) ? owc_entity_get_title( 'coordinator', $slug ) : '';
            $titles[] = '' !== $title ? $title : $slug;
        }
        $line = 'Added ' . implode( ', ', $titles ) . ': ' . $data['note'];
        if ( $nobody ) {
            $line .= ' (nobody holds ' . implode( ', ', $nobody ) . ' yet; the Archivist can approve for them.)';
        }
        OAT_Timeline::append( array(
            'entry_id'        => $entry_id,
            'action_type'     => OAT_Constants::ACTION_ADD_COORDINATOR,
            'actor_id'        => $user_id,
            'step'            => $step,
            'visibility_tier' => OAT_Constants::TIER_COORDINATOR,
            'note'            => $line,
        ) );

        return true;
    }
}
