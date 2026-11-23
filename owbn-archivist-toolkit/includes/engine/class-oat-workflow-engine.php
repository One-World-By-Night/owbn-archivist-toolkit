<?php

defined( 'ABSPATH' ) || exit;

/**
 * Central action processor — the state machine.
 *
 * All workflow actions route through process_action(), which validates
 * the request and dispatches to the appropriate action handler.
 */
class OAT_Workflow_Engine {

    /**
     * Forms where a coordinator who files for an office they hold goes straight to that office's review, without the
     * chronicle HST's Staff Review.
     */
    const COORDINATOR_OWN_OFFICE_FORMS = array( 'custom_content', 'cl_ru_request' );

    /**
     * Process any action on an entry.
     *
     * @param int    $entry_id The entry to act on.
     * @param string $action   Action type constant.
     * @param int    $user_id  Acting user.
     * @param array  $data     Action-specific data (note, meta, etc).
     * @return true|WP_Error
     */
    public static function process_action( $entry_id, $action, $user_id, $data = array() ) {
        $entry = OAT_Entry::find( $entry_id );
        if ( ! $entry ) {
            return new WP_Error( 'not_found', 'Entry not found.' );
        }

        // 2. Check terminal status (only council_override bypasses).
        if ( OAT_Constants::is_terminal_status( $entry->status ) && $action !== OAT_Constants::ACTION_COUNCIL_OVERRIDE ) {
            return new WP_Error( 'terminal', 'Entry is in terminal status.' );
        }
        if ( ! OAT_Constants::is_valid_action( $action ) ) {
            return new WP_Error( 'invalid_action', 'Unknown action type.' );
        }
        $handler = self::get_handler( $action );
        if ( ! $handler ) {
            return new WP_Error( 'no_handler', 'No handler for action.' );
        }

        return $handler::execute( $entry, $user_id, $data );
    }

    /**
     * Map action type to handler class name.
     *
     * @param string $action
     * @return string|null
     */
    private static function get_handler( $action ) {
        $map = array(
            'submit'           => 'OAT_Action_Submit',
            'approve'          => 'OAT_Action_Approve',
            'deny'             => 'OAT_Action_Deny',
            'request_changes'  => 'OAT_Action_Request_Changes',
            'cancel'           => 'OAT_Action_Cancel',
            'bump'             => 'OAT_Action_Bump',
            'reassign'         => 'OAT_Action_Reassign',
            'delegate'         => 'OAT_Action_Delegate',
            'add_coordinator'  => 'OAT_Action_Add_Coordinator',
            'approve_all'      => 'OAT_Action_Approve_All',
            'veto_line'        => 'OAT_Action_Veto_Line',
            'hold'             => 'OAT_Action_Hold',
            'resume'           => 'OAT_Action_Resume',
            'record'           => 'OAT_Action_Record',
            'auto_approve'     => 'OAT_Action_Auto_Approve',
            'auto_deny'        => 'OAT_Action_Auto_Deny',
            'council_override' => 'OAT_Action_Council_Override',
            'timer_extend'     => 'OAT_Action_Timer_Extend',
        );
        return isset( $map[ $action ] ) ? $map[ $action ] : null;
    }

    /**
     * Get step configuration for an entry's domain.
     *
     * Checks DB-driven workflow steps first (D-055), falls back to PHP domain class.
     *
     * @param object      $entry   The entry object.
     * @param string|null $step_id Step ID (defaults to current_step).
     * @return array|null Step config or null if not found.
     */
    public static function get_step_config( $entry, $step_id = null ) {
        $target = $step_id !== null ? $step_id : $entry->current_step;
        if ( class_exists( 'OAT_Workflow_Step' ) ) {
            $db_config = OAT_Workflow_Step::get_step_config( $entry->domain, $target );
            if ( $db_config !== null ) {
                return $db_config;
            }
        }
        $domain = OAT_Domain_Registry::get_php_domain( $entry->domain );
        if ( ! $domain ) {
            return null;
        }

        $template = $domain->get_workflow_template();
        foreach ( $template as $step ) {
            if ( $step['id'] === $target ) {
                return $step;
            }
        }

        return null;
    }

    /**
     * Resolve the next step from the current step's routing config.
     *
     * @param object $entry     The entry object.
     * @param string $from_step Step ID to route from.
     * @param string $action    Action that triggered the routing ('approve', 'deny', 'request_changes').
     * @return string|null Next step ID, or null for terminal.
     */
    public static function get_next_step( $entry, $from_step, $action ) {
        $step_config = self::get_step_config( $entry, $from_step );
        if ( ! $step_config ) {
            return null;
        }

        $route_key = 'on_' . $action;
        $next      = isset( $step_config[ $route_key ] ) ? $step_config[ $route_key ] : null;

        // An approval passes over steps that don't apply to this request; when none is left, the request is finished.
        if ( 'approve' === $action ) {
            $seen = array( $from_step );
            while ( null !== $next && ! in_array( $next, $seen, true ) ) {
                $next_config = self::get_step_config( $entry, $next );
                if ( ! $next_config || self::step_applies( $entry, $next, $next_config ) ) {
                    break;
                }
                $seen[] = $next;
                $next   = isset( $next_config['on_approve'] ) ? $next_config['on_approve'] : null;
            }
        }

        return $next;
    }

    /**
     * Advance an entry to a new step.
     *
     * Updates current_step, assigns users, evaluates conditions (skip if false),
     * and starts timer if configured.
     *
     * @param object $entry   The entry object.
     * @param string $step_id Target step ID.
     * @return void
     */
    public static function advance_to_step( $entry, $step_id ) {
        $step_config = self::get_step_config( $entry, $step_id );
        if ( ! $step_config ) {
            return;
        }

        // A step that doesn't apply to this request is skipped: on to its on_approve step.
        if ( ! self::step_applies( $entry, $step_id, $step_config ) ) {
            $next = isset( $step_config['on_approve'] ) ? $step_config['on_approve'] : null;
            if ( $next !== null && $next !== $step_id ) {
                self::advance_to_step( $entry, $next );
            }
            // Terminal if on_approve is null or same step (prevents infinite recursion).
            return;
        }

        // Cancel any existing active timer before transitioning.
        $existing_timer = OAT_Timer::active_for_entry( (int) $entry->id );
        if ( $existing_timer ) {
            OAT_Timer::cancel( (int) $existing_timer->id );
        }

        // Update entry's current step.
        OAT_Entry::update( (int) $entry->id, array( 'current_step' => $step_id ) );

        // Clear stale assignees from previous visits to this step.
        OAT_Assignee::clear_step( (int) $entry->id, $step_id );

        // Assign users at this step.
        if ( ! empty( $step_config['assignee_role'] ) ) {
            $user_ids = self::resolve_assignees( $entry, $step_config['assignee_role'] );
            foreach ( $user_ids as $uid ) {
                OAT_Assignee::assign( (int) $entry->id, (int) $uid, $step_id );
            }
            if ( ! empty( $user_ids ) ) {
                /**
                 * Fires when people are assigned to the step a request has reached.
                 *
                 * @param int    $entry_id Request id.
                 * @param string $step_id  Step id.
                 * @param int[]  $user_ids Assigned users.
                 */
                do_action( 'oat_entry_step_assigned', (int) $entry->id, $step_id, array_map( 'intval', $user_ids ) );
            }
        }

        // Start timer if configured.
        if ( ! empty( $step_config['timer'] ) ) {
            $timer_config = $step_config['timer'];
            OAT_Timer::create( array(
                'entry_id'      => (int) $entry->id,
                'step'          => $step_id,
                'auto_action'   => isset( $timer_config['auto_action'] ) ? $timer_config['auto_action'] : 'auto_deny',
                'bump_required' => isset( $timer_config['bump_required'] ) ? (int) $timer_config['bump_required'] : 0,
                'started_at'    => time(),
                'expires_at'    => time() + ( isset( $timer_config['duration'] ) ? (int) $timer_config['duration'] : 1209600 ),
            ) );
        }
    }

    /**
     * Whether a step applies to a request: its condition (if any) holds, and a Coordinator step has an office to
     * assign (one without would assign nobody).
     *
     * @param object     $entry       The entry object.
     * @param string     $step_id     Step ID.
     * @param array|null $step_config The step's configuration, when already loaded.
     * @return bool
     */
    public static function step_applies( $entry, $step_id, $step_config = null ) {
        if ( null === $step_config ) {
            $step_config = self::get_step_config( $entry, $step_id );
        }
        if ( ! $step_config ) {
            return false;
        }
        if ( ! empty( $step_config['condition'] ) && ! self::evaluate_condition( $step_config['condition'], (int) $entry->id ) ) {
            return false;
        }
        if ( self::coordinator_files_for_own_office( $entry, $step_id, $step_config ) ) {
            return false;
        }
        if ( self::office_npc_filed_by_its_office( $entry, $step_config ) ) {
            return false;
        }
        if ( ! empty( $step_config['assignee_role'] )
            && false !== strpos( $step_config['assignee_role'], '{coordinator_genre}' )
            && '' === self::derive_coordinator_office( $entry ) ) {
            return false;
        }
        // A step for the teacher's chronicle has nobody to ask when none was typed, and the request's own chronicle has already reviewed it.
        if ( ! empty( $step_config['assignee_role'] ) && false !== strpos( $step_config['assignee_role'], '{teacher_chronicle}' ) ) {
            $teacher = class_exists( 'OAT_Entry_Meta' ) ? strtolower( trim( (string) OAT_Entry_Meta::get( (int) $entry->id, 'teacher_chronicle' ) ) ) : '';
            if ( '' === $teacher || strtolower( (string) ( $entry->chronicle_slug ?? '' ) ) === $teacher ) {
                return false;
            }
        }
        return true;
    }

    /**
     * Whether this is the chronicle HST's Staff Review of a request its own filer, a Coordinator, owns: the filer holds
     * the Coordinator role of an office that owns the request, on a form in COORDINATOR_OWN_OFFICE_FORMS.
     *
     * @param object $entry       The entry object.
     * @param string $step_id     Step ID.
     * @param array  $step_config The step's configuration.
     * @return bool
     */
    public static function coordinator_files_for_own_office( $entry, $step_id, $step_config ) {
        if ( 'staff_review' !== $step_id || empty( $entry->form_slug ) || ! in_array( $entry->form_slug, self::COORDINATOR_OWN_OFFICE_FORMS, true ) ) {
            return false;
        }
        $role = isset( $step_config['assignee_role'] ) ? strtolower( (string) $step_config['assignee_role'] ) : '';
        if ( 0 !== strpos( $role, 'chronicle/{chronicle_slug}/hst' ) ) {
            return false;
        }
        $filer = isset( $entry->originator_id ) ? (int) $entry->originator_id : 0;
        if ( $filer <= 0 ) {
            return false;
        }
        foreach ( self::coordinator_offices( $entry ) as $office ) {
            if ( in_array( $filer, OAT_Authorization::get_users_with_role( 'Coordinator/' . $office . '/Coordinator' ), true ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * The coordinator office that owns the character a request is about, when that character is an office NPC and the
     * request names no chronicle. Such a request has no chronicle staff to review it; its owning office does.
     *
     * @param object $entry The entry object.
     * @return string An office slug, or '' when this is not such a request.
     */
    public static function chronicle_less_npc_office( $entry ) {
        if ( ! empty( $entry->chronicle_slug ) ) {
            return '';
        }
        $character = self::character_for_entry( $entry );
        if ( ! $character || 'coordinator' !== strtolower( trim( (string) ( $character->npc_type ?? '' ) ) ) ) {
            return '';
        }
        $office = strtolower( trim( (string) ( $character->npc_coordinator ?? '' ) ) );
        return preg_match( '/^[a-z0-9][a-z0-9-]*$/', $office ) ? $office : '';
    }

    /**
     * Whether a role pattern is a chronicle's staff, as the Staff Review step has.
     *
     * @param string $role_pattern Role path pattern.
     * @return bool
     */
    private static function is_chronicle_staff_pattern( $role_pattern ) {
        return 0 === strpos( strtolower( ltrim( (string) $role_pattern ) ), 'chronicle/{chronicle_slug}/' );
    }

    /**
     * Whether this is the Staff Review of a request about an office NPC that the office's own Coordinator or
     * Sub-Coordinator filed: nobody reviews their own request.
     *
     * @param object $entry       The entry object.
     * @param array  $step_config The step's configuration.
     * @return bool
     */
    public static function office_npc_filed_by_its_office( $entry, $step_config ) {
        $pattern = isset( $step_config['assignee_role'] ) ? (string) $step_config['assignee_role'] : '';
        if ( ! self::is_chronicle_staff_pattern( $pattern ) ) {
            return false;
        }
        $office = self::chronicle_less_npc_office( $entry );
        $filer  = isset( $entry->originator_id ) ? (int) $entry->originator_id : 0;
        if ( '' === $office || $filer <= 0 ) {
            return false;
        }
        return in_array( $filer, array_map( 'intval', OAT_Authorization::get_users_with_role( 'Coordinator/' . $office . '/Coordinator|Coordinator/' . $office . '/Sub-Coordinator' ) ), true );
    }

    /**
     * Evaluate a conditional routing condition against entry meta.
     *
     * @param array $condition Condition spec with 'meta_key', 'operator', 'value'.
     * @param int   $entry_id
     * @return bool
     */
    public static function evaluate_condition( $condition, $entry_id ) {
        $value = OAT_Entry_Meta::get( $entry_id, $condition['meta_key'] );

        switch ( $condition['operator'] ) {
            case '=':
                return $value === $condition['value'];
            case '!=':
                return $value !== $condition['value'];
            case '>':
                return (int) $value > (int) $condition['value'];
            case '<':
                return (int) $value < (int) $condition['value'];
            case 'in':
                return in_array( $value, (array) $condition['value'], true );
            case 'starts_with':
                return strpos( $value, $condition['value'] ) === 0;
            case 'ends_with':
                return substr( $value, -strlen( $condition['value'] ) ) === $condition['value'];
            default:
                return false;
        }
    }

    /**
     * Resolve assignees from a role pattern using accessSchema.
     *
     * Replaces placeholders like {chronicle_slug} and {coordinator_genre}
     * with values from the entry, then queries for matching users.
     *
     * @param object $entry        The entry object.
     * @param string $role_pattern Role path pattern (e.g., 'oat/{chronicle_slug}/reviewer').
     * @return array User IDs.
     */

    /**
     * Known coordinator office slugs, cached per request.
     *
     * @return array slug => true
     */
    public static function coordinator_slugs() {
        static $slugs = null;
        if ( null !== $slugs ) {
            return $slugs;
        }
        $slugs = array();
        if ( function_exists( 'owc_get_coordinators' ) ) {
            foreach ( (array) owc_get_coordinators() as $c ) {
                if ( ! empty( $c['slug'] ) ) {
                    $slugs[ strtolower( $c['slug'] ) ] = true;
                }
            }
        }
        return $slugs;
    }

    /**
     * The coordinator office that owns this request, first of coordinator_offices().
     *
     * @return string A coordinator slug, or '' when no office owns this request.
     */
    public static function derive_coordinator_office( $entry ) {
        $offices = self::coordinator_offices( $entry );
        return $offices ? $offices[0] : '';
    }

    /**
     * Every coordinator office that owns this request.
     *
     * The offices named by the request's selected regulation rules when those rules need a
     * coordinator; otherwise the single office of the entry or its character.
     *
     * @return string[] Coordinator slugs, empty when no office owns this request.
     */
    public static function coordinator_offices( $entry ) {
        $offices = array_values( array_unique( array_merge( self::rule_coordinator_offices( $entry ), self::involved_coordinators( $entry ) ) ) );
        if ( $offices ) {
            return $offices;
        }
        $one = self::derive_office_from_entry( $entry );
        return '' === $one ? array() : array( $one );
    }

    /**
     * The coordinator offices the filer (or a coordinator on the request) named as having to approve it.
     *
     * @param object $entry The entry object.
     * @return string[] Coordinator slugs, only real offices.
     */
    public static function involved_coordinators( $entry ) {
        if ( empty( $entry->id ) || ! class_exists( 'OAT_Entry_Meta' ) ) {
            return array();
        }
        $raw  = OAT_Entry_Meta::get( (int) $entry->id, 'involved_coordinators' );
        $list = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
        if ( ! is_array( $list ) ) {
            $list = is_string( $raw ) ? array_filter( array_map( 'trim', explode( ',', $raw ) ) ) : array();
        }
        $known = self::coordinator_slugs();
        $out   = array();
        foreach ( $list as $slug ) {
            $slug = strtolower( trim( (string) $slug ) );
            if ( '' !== $slug && isset( $known[ $slug ] ) ) {
                $out[ $slug ] = true;
            }
        }
        return array_keys( $out );
    }

    /**
     * The coordinator offices controlling the regulation rules selected on this request, for the rules whose level
     * needs a coordinator (approval, disallowed, council vote at the character's PC or NPC level).
     *
     * @return string[] Coordinator slugs in rule order, only real offices.
     */
    public static function rule_coordinator_offices( $entry ) {
        global $wpdb;
        if ( empty( $entry->id ) ) {
            return array();
        }
        $er   = $wpdb->prefix . 'oat_entry_rules';
        $rr   = $wpdb->prefix . 'oat_regulation_rules';
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT rr.controlling_coordinator, rr.pc_level, rr.npc_level FROM {$er} er
             JOIN {$rr} rr ON rr.id = er.rule_id
             WHERE er.entry_id = %d AND rr.controlling_coordinator <> '' ORDER BY er.id",
            (int) $entry->id
        ) );
        if ( ! $rows ) {
            return array();
        }
        $pc_npc = class_exists( 'OAT_Entry_Meta' ) ? OAT_Entry_Meta::get( (int) $entry->id, 'pc_npc' ) : '';
        $needs  = array( 'coordinator_approval' => true, 'disallowed' => true, 'council_vote' => true );
        $slugs  = self::coordinator_slugs();
        $offices = array();
        foreach ( $rows as $row ) {
            $level = ( 'npc' === $pc_npc && ! empty( $row->npc_level ) ) ? $row->npc_level : $row->pc_level;
            if ( ! isset( $needs[ $level ] ) ) {
                continue;
            }
            $tokens = class_exists( 'OAT_Coordinator_Backfill' )
                ? OAT_Coordinator_Backfill::tokenize_authority( $row->controlling_coordinator )
                : array( strtolower( trim( (string) $row->controlling_coordinator ) ) );
            $named = false;
            foreach ( $tokens as $token ) {
                $token = str_replace( ' ', '-', $token );
                if ( isset( $slugs[ $token ] ) ) {
                    $offices[ $token ] = true;
                    $named             = true;
                }
            }
            // A value that names no office and asks for the sect coordinator ("Appropriate Vampire Sect Coordinator")
            // means the character's sect office. A value that names an office keeps just that office.
            if ( ! $named && self::asks_for_sect_office( $row->controlling_coordinator ) ) {
                $sect = self::derive_sect_office( $entry );
                if ( '' !== $sect ) {
                    $offices[ $sect ] = true;
                }
            }
        }
        return array_keys( $offices );
    }

    /**
     * Whether a rule's coordinator wording asks for the sect coordinator (and not the clan's).
     *
     * @param string $raw The rule's controlling_coordinator text.
     * @return bool
     */
    public static function asks_for_sect_office( $raw ) {
        $s = strtolower( (string) $raw );
        return (bool) preg_match( '/\bsect\b/', $s ) && false !== strpos( $s, 'coordinator' ) && ! preg_match( '/\bclan\b/', $s );
    }

    /**
     * The coordinator office of the sect a vampire character belongs to (Camarilla, Sabbat or Anarch).
     *
     * @param object $entry The entry object.
     * @return string A coordinator slug, or '' when the character has no sect with an office.
     */
    public static function derive_sect_office( $entry ) {
        $ch = self::character_for_entry( $entry );
        if ( ! $ch || 'vampire' !== strtolower( trim( (string) $ch->creature_genre ) ) ) {
            return '';
        }
        $sect    = strtolower( trim( preg_replace( '/\s+/', ' ', (string) $ch->creature_sub_type ) ) );
        $by_sect = array( 'camarilla' => 'camarilla', 'sabbat' => 'sabbat', 'anarch' => 'anarch' );
        $slugs   = self::coordinator_slugs();
        return isset( $by_sect[ $sect ] ) && isset( $slugs[ $by_sect[ $sect ] ] ) ? $by_sect[ $sect ] : '';
    }

    /**
     * Work out which coordinator office owns this entry or its character.
     *
     * The entry's coordinator_genre is free text and has historically been filled
     * with clan and creature names ("baali", "ravnos antitribu", "red talon") that
     * are not coordinator slugs, leaving requests with nobody assigned. So: trust
     * the value when it IS an office, otherwise derive it from the character record,
     * which carries genre, clan and sect.
     *
     * @return string A coordinator slug, or '' when no office owns this character.
     */
    public static function derive_office_from_entry( $entry ) {
        $slugs = self::coordinator_slugs();
        $norm  = function ( $v ) {
            return strtolower( trim( preg_replace( '/\s+/', ' ', (string) $v ) ) );
        };

        // 1. The stored value, when it is already a real office.
        $given = isset( $entry->coordinator_genre ) ? $norm( $entry->coordinator_genre ) : '';
        if ( '' !== $given && isset( $slugs[ $given ] ) ) {
            return $given;
        }

        // 2. Otherwise derive from the character.
        $ch = self::character_for_entry( $entry );
        if ( ! $ch ) {
            return '';
        }
        $genre = $norm( isset( $ch->creature_genre ) ? $ch->creature_genre : '' );
        $type  = $norm( isset( $ch->creature_type ) ? $ch->creature_type : '' );
        $sect  = $norm( isset( $ch->creature_sub_type ) ? $ch->creature_sub_type : '' );

        // Non-vampire genres map straight to their office.
        $by_genre = array(
            'changing breeds' => 'changing-breeds',
            'wraith'          => 'wraith',
            'mage'            => 'mage',
            'changeling'      => 'changeling',
            'demon'           => 'demon',
            'kuei-jin'        => 'kuei-jin',
            'mummy'           => 'mummy',
            'hunter'          => 'hunter',
        );
        if ( isset( $by_genre[ $genre ] ) && isset( $slugs[ $by_genre[ $genre ] ] ) ) {
            return $by_genre[ $genre ];
        }

        if ( 'vampire' === $genre ) {
            // Lineages whose parent office is unambiguous.
            $alias = array(
                'baali'                => 'demon',
                'samedi'               => 'giovanni',
                'gargoyle'             => 'tremere',
                'serpent of the light' => 'setite',
                'serpents of the light'=> 'setite',
                'harbinger of skulls'  => 'giovanni',
                'harbingers of skulls' => 'giovanni',
                'kiasyd'               => 'lasombra',
                'true brujah'          => 'brujah',
                'pander'               => 'sabbat',
            );
            if ( isset( $alias[ $type ] ) && isset( $slugs[ $alias[ $type ] ] ) ) {
                return $alias[ $type ];
            }

            // Clan office, ignoring antitribu / bracketed / "old clan" variants.
            $base = trim( preg_replace( '/\b(antitribu|anti-tribu)\b/', '', $type ) );
            $base = trim( preg_replace( '/\(.*?\)/', '', $base ) );
            $base = trim( preg_replace( '/^old clan\s+/', '', $base ) );
            $base = trim( preg_replace( '/\s+/', ' ', $base ) );
            if ( '' !== $base && isset( $slugs[ $base ] ) ) {
                return $base;
            }

            // Sect, for clans with no office of their own (Caitiff and friends).
            $by_sect = array( 'camarilla' => 'camarilla', 'sabbat' => 'sabbat', 'anarch' => 'anarch' );
            if ( isset( $by_sect[ $sect ] ) && isset( $slugs[ $by_sect[ $sect ] ] ) ) {
                return $by_sect[ $sect ];
            }
        }

        return '';
    }

    /** @return object|null */
    protected static function character_for_entry( $entry ) {
        global $wpdb;
        $table = $wpdb->prefix . 'oat_characters';
        if ( ! empty( $entry->character_id ) ) {
            $ch = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $entry->character_id ) );
            if ( $ch ) {
                return $ch;
            }
        }
        if ( class_exists( 'OAT_Entry_Meta' ) && isset( $entry->id ) ) {
            $uuid = OAT_Entry_Meta::get( (int) $entry->id, 'character_name' );
            if ( $uuid ) {
                return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE uuid = %s", $uuid ) );
            }
        }
        return null;
    }

    public static function resolve_assignees( $entry, $role_pattern ) {
        $role = self::expand_role_pattern( $entry, $role_pattern );

        // BA-003: Direct user assignment via User/<user_id> pattern.
        if ( strpos( $role, 'User/' ) === 0 ) {
            $uid = (int) substr( $role, 5 );
            return $uid > 0 ? array( $uid ) : array();
        }

        $users = OAT_Authorization::get_users_with_role( $role );

        // A chronicle with no HST: its CM reviews, else its Staff.
        if ( ! $users && preg_match( '#^chronicle/([^/|]+)/hst$#i', $role, $m ) ) {
            foreach ( array( 'CM', 'Staff' ) as $fallback ) {
                $users = OAT_Authorization::get_users_with_role( 'Chronicle/' . $m[1] . '/' . $fallback );
                if ( $users ) {
                    break;
                }
            }
        }

        return $users;
    }

    /**
     * A step's role pattern with this request's values filled in: its chronicle, its coordinator office or offices (several
     * joined with |), and any {meta_key} token from its fields.
     *
     * @param object $entry        The entry object.
     * @param string $role_pattern Role path pattern (e.g. 'Coordinator/{coordinator_genre}/Coordinator').
     * @return string The role path, or several joined with |.
     */
    public static function expand_role_pattern( $entry, $role_pattern ) {
        // A request about an office NPC, with no chronicle, is reviewed by the office that owns the NPC.
        if ( self::is_chronicle_staff_pattern( $role_pattern ) ) {
            $office = self::chronicle_less_npc_office( $entry );
            if ( '' !== $office ) {
                return 'Coordinator/' . $office . '/Coordinator|Coordinator/' . $office . '/Sub-Coordinator';
            }
        }

        $replacements = array(
            '{chronicle_slug}'    => isset( $entry->chronicle_slug ) ? $entry->chronicle_slug : '',
            '{coordinator_genre}' => self::derive_coordinator_office( $entry ),
        );

        // Resolve any remaining {meta_key} tokens from entry meta (CL-007).
        if ( preg_match_all( '/\{([a-z_]+)\}/', $role_pattern, $matches ) ) {
            foreach ( $matches[1] as $key ) {
                $placeholder = '{' . $key . '}';
                if ( ! isset( $replacements[ $placeholder ] ) ) {
                    $meta_val = '';
                    if ( class_exists( 'OAT_Entry_Meta' ) && isset( $entry->id ) ) {
                        $meta_val = OAT_Entry_Meta::get( $entry->id, $key );
                    }
                    $replacements[ $placeholder ] = $meta_val ? $meta_val : '';
                }
            }
        }

        $role = str_replace( array_keys( $replacements ), array_values( $replacements ), $role_pattern );

        // A request owned by several coordinator offices is assigned to each of them.
        if ( false !== strpos( $role_pattern, '{coordinator_genre}' ) ) {
            $offices = self::coordinator_offices( $entry );
            if ( count( $offices ) > 1 ) {
                $roles = array();
                foreach ( $offices as $office ) {
                    $roles[] = str_replace( array_keys( $replacements ), array_values( array_merge( $replacements, array( '{coordinator_genre}' => $office ) ) ), $role_pattern );
                }
                $role = implode( '|', array_unique( $roles ) );
            }
        }

        return $role;
    }
}
