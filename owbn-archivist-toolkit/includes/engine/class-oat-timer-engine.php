<?php

defined( 'ABSPATH' ) || exit;

/**
 * Timer lifecycle management.
 *
 * Creates, tracks, pauses, resumes, and fires timers.
 * BBP is a timer configuration, not special-case code.
 */
class OAT_Timer_Engine {

    /**
     * Start a new timer for an entry at a given step.
     *
     * @param int    $entry_id
     * @param string $step
     * @param array  $config Keys: 'auto_action', 'duration', 'bump_required' (optional).
     * @return int Timer ID.
     */
    public static function start_timer( $entry_id, $step, $config ) {
        $now = time();
        return OAT_Timer::create( array(
            'entry_id'      => $entry_id,
            'step'          => $step,
            'auto_action'   => $config['auto_action'],
            'bump_required' => isset( $config['bump_required'] ) ? (int) $config['bump_required'] : 0,
            'started_at'    => $now,
            'expires_at'    => $now + (int) $config['duration'],
        ) );
    }

    /**
     * Cancel the active timer for an entry, if one exists.
     *
     * @param int $entry_id
     * @return void
     */
    public static function cancel_active( $entry_id ) {
        $timer = OAT_Timer::active_for_entry( $entry_id );
        if ( $timer ) {
            OAT_Timer::cancel( (int) $timer->id );
        }
    }

    /**
     * Pause the active timer for an entry, if one exists.
     *
     * @param int $entry_id
     * @return void
     */
    public static function pause_active( $entry_id ) {
        $timer = OAT_Timer::active_for_entry( $entry_id );
        if ( $timer ) {
            OAT_Timer::pause( (int) $timer->id );
        }
    }

    /**
     * Resume a paused timer for an entry.
     * Calculates remaining seconds from (expires_at - paused_at).
     *
     * @param int $entry_id
     * @return void
     */
    public static function resume_active( $entry_id ) {
        $timers = OAT_Timer::for_entry( $entry_id );
        foreach ( $timers as $timer ) {
            if ( $timer->status === OAT_Constants::TIMER_PAUSED ) {
                $remaining = max( 0, (int) $timer->expires_at - (int) $timer->paused_at );
                if ( $remaining > 0 ) {
                    OAT_Timer::resume( (int) $timer->id, $remaining );
                }
                break;
            }
        }
    }

    /**
     * Record a bump on the active timer.
     *
     * @param int $entry_id
     * @param int $user_id
     * @return int New bump count, or 0 if no active timer.
     */
    public static function record_bump( $entry_id, $user_id ) {
        $timer = OAT_Timer::active_for_entry( $entry_id );
        if ( ! $timer ) {
            return 0;
        }
        return OAT_Timer::increment_bump( (int) $timer->id );
    }

    /**
     * Check if an entry is eligible for BBP auto-approve.
     *
     * Three independent conditions (D-045):
     *   1. Timer expired (time elapsed)
     *   2. Bump count >= bump_required
     *   3. No elevation on linked rules (D-046)
     *
     * @param int $entry_id
     * @return bool
     */
    public static function check_bbp_eligible( $entry_id ) {
        $timer = OAT_Timer::active_for_entry( $entry_id );
        if ( ! $timer || $timer->auto_action !== 'auto_approve' ) {
            return false;
        }

        // Condition 1: time elapsed.
        if ( time() < (int) $timer->expires_at ) {
            return false;
        }

        // Condition 2: enough bumps.
        if ( (int) $timer->bump_count < (int) $timer->bump_required ) {
            return false;
        }

        // Condition 3: no elevation.
        if ( OAT_Entry_Rule::has_elevation( $entry_id ) ) {
            return false;
        }

        return true;
    }

    /**
     * Invoke BBP auto-approve for an entry. Submitter-initiated (D-045).
     *
     * @param int $entry_id
     * @param int $user_id The submitter invoking auto-approve.
     * @return true|WP_Error
     */
    public static function invoke_auto_approve( $entry_id, $user_id ) {
        if ( ! self::check_bbp_eligible( $entry_id ) ) {
            return new WP_Error( 'not_eligible', 'Entry is not eligible for BBP auto-approve.' );
        }

        return OAT_Workflow_Engine::process_action(
            $entry_id,
            'auto_approve',
            $user_id,
            array( 'note' => 'BBP auto-approve invoked by submitter.' )
        );
    }

    /**
     * Process all expired timers. Called by WP-Cron or admin page load.
     *
     * Auto-deny fires automatically. Auto-approve is NOT fired automatically
     * (submitter-initiated per D-045) — timer is just marked as fired.
     *
     * @return int Count of timers processed.
     */
    public static function process_expired_timers() {
        $expired = OAT_Timer::expired();
        $count   = 0;

        foreach ( $expired as $timer ) {
            if ( $timer->auto_action === 'auto_deny' ) {
                OAT_Workflow_Engine::process_action(
                    (int) $timer->entry_id,
                    'auto_deny',
                    0,
                    array( 'note' => 'Timer expired — auto-denied.' )
                );
                $count++;
            }

            // Mark timer as fired (for both auto_deny and auto_approve).
            OAT_Timer::fire( (int) $timer->id );
        }

        return $count;
    }

    /**
     * Extend the active timer for an entry.
     *
     * @param int $entry_id
     * @param int $seconds Additional seconds to add.
     * @param int $user_id User performing the extension.
     * @return true|WP_Error
     */
    public static function extend( $entry_id, $seconds, $user_id ) {
        return OAT_Workflow_Engine::process_action(
            $entry_id,
            'timer_extend',
            $user_id,
            array(
                'note'               => 'Timer extended.',
                'additional_seconds' => $seconds,
            )
        );
    }
}
