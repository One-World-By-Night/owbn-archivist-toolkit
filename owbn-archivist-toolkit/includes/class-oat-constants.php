<?php
/**
 * OAT Constants.
 *
 * All enum-like values used across OAT tables and logic.
 * Source of truth: SCHEMA-DESIGN.md Enums / Constants section.
 */

defined( 'ABSPATH' ) || exit;

class OAT_Constants {

    // ── Entry Statuses (oat_entries.status) ──────────────────────────

    const STATUS_PENDING       = 'pending';
    const STATUS_HELD          = 'held';
    const STATUS_APPROVED      = 'approved';
    const STATUS_DENIED        = 'denied';
    const STATUS_CANCELLED     = 'cancelled';
    const STATUS_AUTO_APPROVED = 'auto_approved';
    const STATUS_AUTO_DENIED   = 'auto_denied';

    // ── Action Types (oat_timeline.action_type) ─────────────────────

    // Core (6).
    const ACTION_SUBMIT          = 'submit';
    const ACTION_APPROVE         = 'approve';
    const ACTION_DENY            = 'deny';
    const ACTION_REQUEST_CHANGES = 'request_changes';
    const ACTION_CANCEL          = 'cancel';
    const ACTION_BUMP            = 'bump';

    // Routing (3).
    const ACTION_REASSIGN = 'reassign';
    const ACTION_DELEGATE = 'delegate';
    const ACTION_HOLD     = 'hold';

    // System (6).
    const ACTION_RESUME           = 'resume';
    const ACTION_RECORD           = 'record';
    const ACTION_AUTO_APPROVE     = 'auto_approve';
    const ACTION_AUTO_DENY        = 'auto_deny';
    const ACTION_COUNCIL_OVERRIDE = 'council_override';
    const ACTION_TIMER_EXTEND     = 'timer_extend';

    // ── Assignee Statuses (oat_assignees.status) ────────────────────

    const ASSIGNEE_PENDING    = 'pending';
    const ASSIGNEE_APPROVED   = 'approved';
    const ASSIGNEE_DENIED     = 'denied';
    const ASSIGNEE_REASSIGNED = 'reassigned';
    const ASSIGNEE_DELEGATED  = 'delegated';

    // ── Timer Statuses (oat_timers.status) ──────────────────────────

    const TIMER_ACTIVE    = 'active';
    const TIMER_PAUSED    = 'paused';
    const TIMER_FIRED     = 'fired';
    const TIMER_CANCELLED = 'cancelled';

    // ── Notification Statuses (oat_notifications.status) ────────────

    const NOTIFICATION_PENDING = 'pending';
    const NOTIFICATION_SENT    = 'sent';
    const NOTIFICATION_FAILED  = 'failed';

    // ── Notification Channels (oat_notifications.channel) ───────────

    const CHANNEL_EMAIL     = 'email';
    const CHANNEL_DASHBOARD = 'dashboard';
    const CHANNEL_API       = 'api';

    // ── Visibility Tiers (oat_timeline.visibility_tier) ─────────────

    const TIER_STAFF       = 'staff';
    const TIER_COORDINATOR = 'coordinator';
    const TIER_ARCHIVIST   = 'archivist';

    // ── Regulation Levels (oat_regulation_rules.pc_level / npc_level) ─

    const REGULATION_COORDINATOR_APPROVAL = 'coordinator_approval';
    const REGULATION_COORDINATOR_NOTIFY   = 'coordinator_notify';
    const REGULATION_DISALLOWED           = 'disallowed';
    const REGULATION_COUNCIL_VOTE         = 'council_vote';
    // NULL = unregulated (not stored as a constant).

    // ── WordPress Capabilities (D-048) ──────────────────────────────

    const CAP_SUBMIT         = 'oat_submit';
    const CAP_REVIEW         = 'oat_review';
    const CAP_COORD_REVIEW   = 'oat_coord_review';
    const CAP_MANAGE_RULES   = 'oat_manage_rules';
    const CAP_ARCHIVIST      = 'oat_archivist';
    const CAP_EXEC_OVERSIGHT = 'oat_exec_oversight';
    const CAP_ADMIN          = 'oat_admin';

    // ── Validation Helpers ──────────────────────────────────────────

    /**
     * @param string $status
     * @return bool
     */
    public static function is_valid_status( $status ) {
        return in_array( $status, array(
            self::STATUS_PENDING,
            self::STATUS_HELD,
            self::STATUS_APPROVED,
            self::STATUS_DENIED,
            self::STATUS_CANCELLED,
            self::STATUS_AUTO_APPROVED,
            self::STATUS_AUTO_DENIED,
        ), true );
    }

    /**
     * @param string $status
     * @return bool
     */
    public static function is_terminal_status( $status ) {
        return in_array( $status, array(
            self::STATUS_APPROVED,
            self::STATUS_DENIED,
            self::STATUS_CANCELLED,
            self::STATUS_AUTO_APPROVED,
            self::STATUS_AUTO_DENIED,
        ), true );
    }

    /**
     * @param string $action
     * @return bool
     */
    public static function is_valid_action( $action ) {
        return in_array( $action, array(
            self::ACTION_SUBMIT,
            self::ACTION_APPROVE,
            self::ACTION_DENY,
            self::ACTION_REQUEST_CHANGES,
            self::ACTION_CANCEL,
            self::ACTION_BUMP,
            self::ACTION_REASSIGN,
            self::ACTION_DELEGATE,
            self::ACTION_HOLD,
            self::ACTION_RESUME,
            self::ACTION_RECORD,
            self::ACTION_AUTO_APPROVE,
            self::ACTION_AUTO_DENY,
            self::ACTION_COUNCIL_OVERRIDE,
            self::ACTION_TIMER_EXTEND,
        ), true );
    }

    /**
     * @param string $tier
     * @return bool
     */
    public static function is_valid_tier( $tier ) {
        return in_array( $tier, array(
            self::TIER_STAFF,
            self::TIER_COORDINATOR,
            self::TIER_ARCHIVIST,
        ), true );
    }

    /**
     * @param string $level
     * @return bool
     */
    public static function is_valid_regulation_level( $level ) {
        return in_array( $level, array(
            self::REGULATION_COORDINATOR_APPROVAL,
            self::REGULATION_COORDINATOR_NOTIFY,
            self::REGULATION_DISALLOWED,
            self::REGULATION_COUNCIL_VOTE,
        ), true );
    }

    /**
     * @return array
     */
    public static function get_capabilities() {
        return array(
            self::CAP_SUBMIT,
            self::CAP_REVIEW,
            self::CAP_COORD_REVIEW,
            self::CAP_MANAGE_RULES,
            self::CAP_ARCHIVIST,
            self::CAP_EXEC_OVERSIGHT,
            self::CAP_ADMIN,
        );
    }
}
