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
     * Process any action on an entry.
     *
     * @param int    $entry_id The entry to act on.
     * @param string $action   Action type constant.
     * @param int    $user_id  Acting user.
     * @param array  $data     Action-specific data (note, meta, etc).
     * @return true|WP_Error
     */
    public static function process_action( $entry_id, $action, $user_id, $data = array() ) {
        // 1. Load entry.
        $entry = OAT_Entry::find( $entry_id );
        if ( ! $entry ) {
            return new WP_Error( 'not_found', 'Entry not found.' );
        }

        // 2. Check terminal status (only council_override bypasses).
        if ( OAT_Constants::is_terminal_status( $entry->status ) && $action !== OAT_Constants::ACTION_COUNCIL_OVERRIDE ) {
            return new WP_Error( 'terminal', 'Entry is in terminal status.' );
        }

        // 3. Validate action type.
        if ( ! OAT_Constants::is_valid_action( $action ) ) {
            return new WP_Error( 'invalid_action', 'Unknown action type.' );
        }

        // 4. Dispatch to action handler.
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

        // 1. Try DB-driven workflow steps first (D-055).
        if ( class_exists( 'OAT_Workflow_Step' ) ) {
            $db_config = OAT_Workflow_Step::get_step_config( $entry->domain, $target );
            if ( $db_config !== null ) {
                return $db_config;
            }
        }

        // 2. Fall back to PHP domain class.
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
        return isset( $step_config[ $route_key ] ) ? $step_config[ $route_key ] : null;
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

        // Evaluate condition — if false, skip to the next step.
        if ( ! empty( $step_config['condition'] ) ) {
            if ( ! self::evaluate_condition( $step_config['condition'], (int) $entry->id ) ) {
                $next = isset( $step_config['on_approve'] ) ? $step_config['on_approve'] : null;
                if ( $next !== null && $next !== $step_id ) {
                    self::advance_to_step( $entry, $next );
                    return;
                }
                // Terminal if on_approve is null or same step (prevents infinite recursion).
                return;
            }
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
    public static function resolve_assignees( $entry, $role_pattern ) {
        $replacements = array(
            '{chronicle_slug}'    => isset( $entry->chronicle_slug ) ? $entry->chronicle_slug : '',
            '{coordinator_genre}' => isset( $entry->coordinator_genre ) ? $entry->coordinator_genre : '',
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

        // BA-003: Direct user assignment via User/<user_id> pattern.
        if ( strpos( $role, 'User/' ) === 0 ) {
            $uid = (int) substr( $role, 5 );
            return $uid > 0 ? array( $uid ) : array();
        }

        // Query accessSchema for users with this role.
        return OAT_Authorization::get_users_with_role( $role );
    }
}
