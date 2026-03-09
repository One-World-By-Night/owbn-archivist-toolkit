<?php
/**
 * OAT Registry REST API.
 *
 * User-facing endpoints for registry visibility.
 * Uses oat/v1 namespace with cookie/nonce authentication.
 */

defined( 'ABSPATH' ) || exit;

class OAT_REST_Registry {

    const API_NAMESPACE = 'oat/v1';

    public static function register_routes() {
        // Scoped registry for current user.
        register_rest_route( self::API_NAMESPACE, '/registry', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'get_registry' ),
            'permission_callback' => array( __CLASS__, 'check_logged_in' ),
            'args'                => array(
                'chronicle' => array(
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'genre' => array(
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ) );

        // Registry entries for one character.
        register_rest_route( self::API_NAMESPACE, '/registry/character/(?P<id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'get_character_registry' ),
            'permission_callback' => array( __CLASS__, 'check_logged_in' ),
            'args'                => array(
                'id' => array(
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ),
            ),
        ) );

        // Public registry fields for one character.
        register_rest_route( self::API_NAMESPACE, '/registry/public/(?P<character_id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'get_public_registry' ),
            'permission_callback' => array( __CLASS__, 'check_logged_in' ),
            'args'                => array(
                'character_id' => array(
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ),
            ),
        ) );

        // Create an explicit grant.
        register_rest_route( self::API_NAMESPACE, '/registry/grant', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'create_grant' ),
            'permission_callback' => array( __CLASS__, 'check_staff_or_archivist' ),
            'args'                => array(
                'character_id' => array(
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'grant_type' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'grant_value' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'expires_at' => array(
                    'required'          => false,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                    'default'           => 0,
                ),
            ),
        ) );

        // Expire a grant.
        register_rest_route( self::API_NAMESPACE, '/registry/grant/(?P<id>\d+)', array(
            'methods'             => 'DELETE',
            'callback'            => array( __CLASS__, 'expire_grant' ),
            'permission_callback' => array( __CLASS__, 'check_staff_or_archivist' ),
            'args'                => array(
                'id' => array(
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ),
            ),
        ) );
    }

    /**
     * Permission: logged-in user.
     */
    public static function check_logged_in() {
        return is_user_logged_in();
    }

    /**
     * Permission: staff (HST/CM) or archivist.
     */
    public static function check_staff_or_archivist() {
        $roles = OAT_Authorization::get_character_search_roles();
        return in_array( 'staff', $roles, true ) || in_array( 'archivist', $roles, true );
    }

    /**
     * GET /oat/v1/registry
     *
     * Optional filters: ?chronicle=slug or ?genre=slug
     */
    public static function get_registry( $request ) {
        $user_id   = get_current_user_id();
        $chronicle_param = $request->get_param( 'chronicle' );
        $genre_param     = $request->get_param( 'genre' );

        // Parse comma-separated values.
        $chronicles = $chronicle_param ? array_filter( array_map( 'trim', explode( ',', $chronicle_param ) ) ) : array();
        $genres     = $genre_param ? array_filter( array_map( 'trim', explode( ',', $genre_param ) ) ) : array();

        // If filters are provided, query each and merge (deduplicated by character ID).
        if ( $chronicles || $genres ) {
            $characters = array();

            foreach ( $chronicles as $slug ) {
                if ( ! self::can_view_chronicle( $slug ) ) {
                    continue; // Skip unauthorized chronicles silently.
                }
                foreach ( OAT_Registry::get_characters_for_chronicle( $slug ) as $c ) {
                    $characters[ $c->id ] = $c;
                }
            }

            foreach ( $genres as $genre ) {
                if ( ! self::can_view_genre( $genre ) ) {
                    continue; // Skip unauthorized genres silently.
                }
                foreach ( OAT_Registry::get_characters_for_coordinator( $genre ) as $c ) {
                    $characters[ $c->id ] = $c;
                }
            }

            return self::characters_response( array_values( $characters ) );
        }

        // Default: full scoped registry (merged across all viewer roles).
        $characters = OAT_Registry::get_scoped_registry( $user_id );
        return new WP_REST_Response( array(
            'characters' => self::format_characters( $characters ),
            'count'      => count( $characters ),
        ), 200 );
    }

    /**
     * GET /oat/v1/registry/character/{id}
     */
    public static function get_character_registry( $request ) {
        $character_id = (int) $request->get_param( 'id' );
        $character    = OAT_Character::find( $character_id );

        if ( ! $character ) {
            return new WP_Error( 'not_found', 'Character not found.', array( 'status' => 404 ) );
        }

        // Check access: viewer must have a grant for this character, or be archivist, or own the character.
        if ( ! self::can_view_character( $character ) ) {
            return OAT_Authorization::denied();
        }

        $entries      = OAT_Registry::get_registry_entries( $character_id );
        $grants       = OAT_Registry_Access::find_by_character( $character_id );
        $viewer_role  = OAT_Authorization::get_character_search_roles()[0];
        $viewer_genres = self::get_viewer_genres();

        $entries_data = array();
        foreach ( $entries as $entry ) {
            $timeline = OAT_Timeline::for_entry( (int) $entry->id );
            $filtered = OAT_Registry::filter_timeline_for_viewer( $timeline, $viewer_role, $entry, $viewer_genres );
            $meta     = OAT_Entry_Meta::get_all( (int) $entry->id );

            $entries_data[] = array(
                'id'                => (int) $entry->id,
                'domain'            => $entry->domain,
                'form_slug'         => $entry->form_slug,
                'status'            => $entry->status,
                'coordinator_genre' => $entry->coordinator_genre,
                'created_at'        => (int) $entry->created_at,
                'meta'              => self::format_meta( $meta ),
                'timeline'          => self::format_timeline( $filtered ),
            );
        }

        return new WP_REST_Response( array(
            'character' => self::format_character( $character ),
            'grants'    => self::format_grants( $grants ),
            'entries'   => $entries_data,
        ), 200 );
    }

    /**
     * GET /oat/v1/registry/public/{character_id}
     */
    public static function get_public_registry( $request ) {
        $character_id = (int) $request->get_param( 'character_id' );
        $character    = OAT_Character::find( $character_id );

        if ( ! $character ) {
            return new WP_Error( 'not_found', 'Character not found.', array( 'status' => 404 ) );
        }

        $data = OAT_Registry::get_public_registry( $character_id );

        return new WP_REST_Response( array(
            'character_id'   => $character_id,
            'character_name' => $character->character_name,
            'public_fields'  => $data,
        ), 200 );
    }

    /**
     * POST /oat/v1/registry/grant
     */
    public static function create_grant( $request ) {
        $character_id = (int) $request->get_param( 'character_id' );
        $grant_type   = $request->get_param( 'grant_type' );
        $grant_value  = $request->get_param( 'grant_value' );
        $expires_at   = $request->get_param( 'expires_at' );

        if ( ! in_array( $grant_type, array( 'chronicle', 'coordinator' ), true ) ) {
            return new WP_Error( 'invalid_grant_type', 'Grant type must be "chronicle" or "coordinator".', array( 'status' => 400 ) );
        }

        $character = OAT_Character::find( $character_id );
        if ( ! $character ) {
            return new WP_Error( 'not_found', 'Character not found.', array( 'status' => 404 ) );
        }

        // Staff can only grant on their own chronicle's characters.
        if ( ! self::can_manage_grants( $character ) ) {
            return OAT_Authorization::denied( 'You can only manage grants for characters in your chronicle.' );
        }

        $grant_id = OAT_Registry_Access::ensure_grant(
            $character_id,
            $grant_type,
            $grant_value,
            get_current_user_id()
        );

        // Set expiry if provided.
        if ( $expires_at && $grant_id ) {
            $grant = OAT_Registry_Access::find( $grant_id );
            if ( $grant && ! $grant->expires_at ) {
                global $wpdb;
                $wpdb->update(
                    $wpdb->prefix . 'oat_registry_access',
                    array( 'expires_at' => $expires_at ),
                    array( 'id' => $grant_id ),
                    array( '%d' ),
                    array( '%d' )
                );
            }
        }

        return new WP_REST_Response( array(
            'success'  => true,
            'grant_id' => $grant_id,
        ), 201 );
    }

    /**
     * DELETE /oat/v1/registry/grant/{id}
     */
    public static function expire_grant( $request ) {
        $grant_id = (int) $request->get_param( 'id' );
        $grant    = OAT_Registry_Access::find( $grant_id );

        if ( ! $grant ) {
            return new WP_Error( 'not_found', 'Grant not found.', array( 'status' => 404 ) );
        }

        $character = OAT_Character::find( (int) $grant->character_id );
        if ( ! self::can_manage_grants( $character ) ) {
            return OAT_Authorization::denied( 'You can only manage grants for characters in your chronicle.' );
        }

        OAT_Registry_Access::expire( $grant_id );

        return new WP_REST_Response( array( 'success' => true ), 200 );
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private static function can_view_chronicle( $chronicle_slug ) {
        $roles = OAT_Authorization::get_character_search_roles();
        if ( in_array( 'archivist', $roles, true ) ) {
            return true;
        }
        if ( in_array( 'staff', $roles, true ) ) {
            $slugs = self::get_viewer_chronicle_slugs();
            return in_array( $chronicle_slug, $slugs, true );
        }
        return false;
    }

    private static function can_view_genre( $genre ) {
        $roles = OAT_Authorization::get_character_search_roles();
        if ( in_array( 'archivist', $roles, true ) ) {
            return true;
        }
        if ( in_array( 'coordinator', $roles, true ) ) {
            $genres = self::get_viewer_genres();
            return in_array( $genre, $genres, true );
        }
        return false;
    }

    private static function can_view_character( $character ) {
        $roles   = OAT_Authorization::get_character_search_roles();
        $user_id = get_current_user_id();

        if ( in_array( 'archivist', $roles, true ) ) {
            return true;
        }

        // Player owns the character.
        if ( (int) $character->wp_user_id === $user_id ) {
            return true;
        }

        // Staff — character has an active chronicle grant matching viewer's chronicles.
        if ( in_array( 'staff', $roles, true ) ) {
            $slugs = self::get_viewer_chronicle_slugs();
            foreach ( $slugs as $slug ) {
                if ( OAT_Registry_Access::has_access( (int) $character->id, 'chronicle', $slug ) ) {
                    return true;
                }
            }
        }

        // Coordinator — character has an active coordinator grant matching viewer's genres.
        if ( in_array( 'coordinator', $roles, true ) ) {
            $genres = self::get_viewer_genres();
            foreach ( $genres as $genre ) {
                if ( OAT_Registry_Access::has_access( (int) $character->id, 'coordinator', $genre ) ) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function can_manage_grants( $character ) {
        if ( ! $character ) {
            return false;
        }
        $roles = OAT_Authorization::get_character_search_roles();

        // Archivist can manage any character's grants.
        if ( in_array( 'archivist', $roles, true ) ) {
            return true;
        }

        // Staff can only manage characters in their chronicle.
        if ( in_array( 'staff', $roles, true ) ) {
            $slugs = self::get_viewer_chronicle_slugs();
            return in_array( $character->chronicle_slug, $slugs, true );
        }

        return false;
    }

    private static function get_viewer_chronicle_slugs() {
        $asc_roles = OAT_Authorization::get_user_roles();
        $slugs = array();
        foreach ( $asc_roles as $role ) {
            if ( preg_match( '#^chronicle/([^/]+)/(hst|staff|cm|ast)#i', $role, $m ) ) {
                $slugs[] = $m[1];
            }
        }
        return array_unique( $slugs );
    }

    private static function get_viewer_genres() {
        $asc_roles = OAT_Authorization::get_user_roles();
        $genres = array();
        foreach ( $asc_roles as $role ) {
            if ( preg_match( '#^coordinator/([^/]+)/(coordinator|sub-coordinator)$#i', $role, $m ) ) {
                $genres[] = $m[1];
            }
        }
        return array_unique( $genres );
    }

    private static function characters_response( $characters ) {
        foreach ( $characters as $char ) {
            if ( ! isset( $char->entry_counts ) ) {
                $char->entry_counts = OAT_Registry::get_entry_counts_by_domain( (int) $char->id );
            }
        }
        return new WP_REST_Response( array(
            'characters' => self::format_characters( $characters ),
            'count'      => count( $characters ),
        ), 200 );
    }

    private static function format_characters( $characters ) {
        return array_map( array( __CLASS__, 'format_character' ), $characters );
    }

    private static function format_character( $c ) {
        $user_id = get_current_user_id();
        return array(
            'id'             => (int) $c->id,
            'uuid'           => $c->uuid,
            'character_name' => $c->character_name,
            'chronicle_slug' => $c->chronicle_slug,
            'pc_npc'         => $c->pc_npc,
            'creature_type'  => $c->creature_type,
            'status'         => $c->status,
            'entry_counts'   => isset( $c->entry_counts ) ? $c->entry_counts : array(),
            'is_owner'       => $user_id && (int) $c->wp_user_id === $user_id,
        );
    }

    private static function format_grants( $grants ) {
        return array_map( function( $g ) {
            return array(
                'id'           => (int) $g->id,
                'grant_type'   => $g->grant_type,
                'grant_value'  => $g->grant_value,
                'granted_by'   => $g->granted_by ? (int) $g->granted_by : null,
                'starts_at'    => $g->starts_at ? (int) $g->starts_at : null,
                'expires_at'   => $g->expires_at ? (int) $g->expires_at : null,
                'created_at'   => (int) $g->created_at,
            );
        }, $grants );
    }

    private static function format_meta( $meta ) {
        $data = array();
        foreach ( $meta as $m ) {
            $data[ $m->meta_key ] = $m->meta_value;
        }
        return $data;
    }

    private static function format_timeline( $events ) {
        return array_map( function( $e ) {
            return array(
                'id'              => (int) $e->id,
                'action_type'     => $e->action_type,
                'user_id'         => $e->user_id ? (int) $e->user_id : null,
                'step'            => $e->step,
                'visibility_tier' => $e->visibility_tier,
                'note'            => $e->note,
                'created_at'      => (int) $e->created_at,
            );
        }, $events );
    }
}
