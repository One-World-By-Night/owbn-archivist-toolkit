<?php

defined( 'ABSPATH' ) || exit;

class OAT_Page_Entry {

    /**
     * Render the entry detail page.
     *
     * @return void
     */
    public static function render() {
        $entry_id = isset( $_GET['entry_id'] ) ? absint( $_GET['entry_id'] ) : 0;
        if ( ! $entry_id ) {
            echo '<div class="wrap"><h1>Entry Not Found</h1></div>';
            return;
        }

        // Handle POST action.
        if ( $_SERVER['REQUEST_METHOD'] === 'POST' && ! empty( $_POST['oat_action'] ) ) {
            self::handle_action( $entry_id );
        }

        $entry = OAT_Entry::find( $entry_id );
        if ( ! $entry ) {
            echo '<div class="wrap"><h1>Entry Not Found</h1></div>';
            return;
        }

        $user_id = get_current_user_id();

        // Load related data.
        $domain    = OAT_Domain_Registry::get( $entry->domain );
        $meta      = OAT_Entry_Meta::all( $entry_id );
        $assignees = OAT_Assignee::for_entry( $entry_id );
        $watchers  = OAT_Watcher::for_entry( $entry_id );
        $rules     = OAT_Entry_Rule::for_entry( $entry_id );

        // Determine visibility tier.
        $tier = self::get_user_tier( $user_id );
        $timeline = OAT_Timeline::for_entry( $entry_id, $tier );

        // Determine available actions.
        $actions = self::get_available_actions( $entry, $user_id, $assignees );

        // BBP eligibility.
        $bbp_eligible = false;
        if ( (int) $entry->originator_id === $user_id ) {
            $bbp_eligible = OAT_Timer_Engine::check_bbp_eligible( $entry_id );
        }

        // Active timer info.
        $timer = OAT_Timer::active_for_entry( $entry_id );

        include OAT_PLUGIN_DIR . 'templates/admin/entry-detail.php';
    }

    /**
     * Handle a POST action on the entry.
     *
     * @param int $entry_id
     * @return void
     */
    private static function handle_action( $entry_id ) {
        check_admin_referer( 'oat_entry_action' );

        if ( ! current_user_can( OAT_Constants::CAP_SUBMIT ) ) {
            add_settings_error( 'oat_entry', 'unauthorized', 'You do not have permission.', 'error' );
            return;
        }

        $action  = sanitize_text_field( $_POST['oat_action'] );
        $note    = isset( $_POST['oat_note'] ) ? sanitize_textarea_field( $_POST['oat_note'] ) : '';
        $user_id = get_current_user_id();

        // Validate action is available to this user on this entry.
        $entry     = OAT_Entry::find( $entry_id );
        $assignees = $entry ? OAT_Assignee::for_entry( $entry_id ) : array();
        $available = $entry ? self::get_available_actions( $entry, $user_id, $assignees ) : array();
        if ( ! in_array( $action, $available, true ) ) {
            add_settings_error( 'oat_entry', 'invalid_action', 'This action is not available.', 'error' );
            return;
        }

        $data = array( 'note' => $note );

        // Additional data for specific actions.
        if ( $action === 'council_override' && ! empty( $_POST['vote_reference'] ) ) {
            $data['vote_reference'] = sanitize_text_field( $_POST['vote_reference'] );
        }
        if ( $action === 'timer_extend' && ! empty( $_POST['additional_seconds'] ) ) {
            $data['additional_seconds'] = absint( $_POST['additional_seconds'] );
        }
        if ( $action === 'reassign' && ! empty( $_POST['new_user_id'] ) ) {
            $data['target_user_id'] = absint( $_POST['new_user_id'] );
        }
        if ( $action === 'delegate' && ! empty( $_POST['delegate_user_id'] ) ) {
            $data['target_user_id'] = absint( $_POST['delegate_user_id'] );
        }

        $result = OAT_Workflow_Engine::process_action( $entry_id, $action, $user_id, $data );

        if ( is_wp_error( $result ) ) {
            add_settings_error( 'oat_entry', 'action_error', $result->get_error_message(), 'error' );
        } else {
            add_settings_error( 'oat_entry', 'action_success', 'Action completed successfully.', 'success' );
        }
    }

    /**
     * Determine user's visibility tier.
     *
     * @param int $user_id
     * @return string
     */
    private static function get_user_tier( $user_id ) {
        if ( OAT_Authorization::check( OAT_Constants::CAP_ARCHIVIST ) ) {
            return OAT_Constants::TIER_ARCHIVIST;
        }
        if ( OAT_Authorization::check( OAT_Constants::CAP_COORD_REVIEW ) ) {
            return OAT_Constants::TIER_COORDINATOR;
        }
        return OAT_Constants::TIER_STAFF;
    }

    /**
     * Get available actions for current user on this entry.
     *
     * @param object $entry
     * @param int    $user_id
     * @param array  $assignees
     * @return array
     */
    public static function get_available_actions( $entry, $user_id, $assignees ) {
        $actions = array();

        if ( OAT_Constants::is_terminal_status( $entry->status ) ) {
            // Only council_override on terminal denied entries.
            if ( $entry->status === OAT_Constants::STATUS_DENIED && OAT_Authorization::check( OAT_Constants::CAP_EXEC_OVERSIGHT ) ) {
                $actions[] = 'council_override';
            }
            return $actions;
        }

        // Is user a current-step assignee?
        $is_assignee = false;
        foreach ( $assignees as $a ) {
            if ( (int) $a->user_id === $user_id && $a->step === $entry->current_step && $a->status === 'pending' ) {
                $is_assignee = true;
                break;
            }
        }

        if ( $is_assignee ) {
            $actions[] = 'approve';
            $actions[] = 'deny';
            $actions[] = 'request_changes';
            $actions[] = 'reassign';
            $actions[] = 'delegate';
        }

        // Originator actions.
        if ( (int) $entry->originator_id === $user_id ) {
            $actions[] = 'cancel';
            $actions[] = 'bump';
        }

        // Exec oversight.
        if ( OAT_Authorization::check( OAT_Constants::CAP_EXEC_OVERSIGHT ) ) {
            $actions[] = 'hold';
            $actions[] = 'timer_extend';
        }

        // Resume (if held).
        if ( $entry->status === OAT_Constants::STATUS_HELD ) {
            $actions[] = 'resume';
        }

        // Record (archivist step).
        if ( $is_assignee && OAT_Authorization::check( OAT_Constants::CAP_ARCHIVIST ) ) {
            $actions[] = 'record';
        }

        return array_unique( $actions );
    }
}
