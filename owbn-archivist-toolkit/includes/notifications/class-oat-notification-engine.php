<?php

defined( 'ABSPATH' ) || exit;

class OAT_Notification_Engine {

    /**
     * Notification event constants.
     */
    const EVENT_SUBMITTED         = 'submitted';
    const EVENT_APPROVED_AT_STEP  = 'approved_at_step';
    const EVENT_DENIED            = 'denied';
    const EVENT_CHANGES_REQUESTED = 'changes_requested';
    const EVENT_RECORDED          = 'recorded';
    const EVENT_CANCELLED         = 'cancelled';
    const EVENT_TIMER_WARNING     = 'timer_warning';
    const EVENT_TIMER_FIRED       = 'timer_fired';
    const EVENT_HELD              = 'held';
    const EVENT_RESUMED           = 'resumed';
    const EVENT_COUNCIL_OVERRIDE  = 'council_override';

    /**
     * Cached channel instances.
     *
     * @var array|null
     */
    private static $channels = null;

    /**
     * High-level method called by action handlers.
     *
     * Resolves recipients from the event map + watchers, then dispatches
     * through all active channels.
     *
     * @param int    $entry_id
     * @param string $event_type
     * @param array  $extra_context Additional context merged into the base context.
     * @return void
     */
    public static function fire_event( $entry_id, $event_type, $extra_context = array() ) {
        $entry = OAT_Entry::find( $entry_id );
        if ( ! $entry ) {
            return;
        }

        $context = array_merge( array(
            'entry_id' => (int) $entry->id,
            'domain'   => $entry->domain,
            'step'     => $entry->current_step,
            'status'   => $entry->status,
        ), $extra_context );

        $recipient_ids = self::resolve_recipients( $entry_id, $event_type, $entry );
        if ( empty( $recipient_ids ) ) {
            return;
        }

        self::dispatch( $entry_id, $event_type, $recipient_ids, $context );
    }

    /**
     * Dispatch a notification to all channels for each recipient.
     *
     * @param int    $entry_id
     * @param string $event_type
     * @param array  $recipient_ids
     * @param array  $context
     * @return void
     */
    public static function dispatch( $entry_id, $event_type, $recipient_ids, $context ) {
        $channels = self::get_channels();

        foreach ( $recipient_ids as $user_id ) {
            $user_id = (int) $user_id;
            if ( $user_id <= 0 ) {
                continue;
            }

            foreach ( $channels as $channel ) {
                $success = $channel->send( $user_id, $event_type, $context );

                // Dashboard channel handles its own logging; skip duplicate for dashboard.
                if ( $channel->get_channel_id() === OAT_Constants::CHANNEL_DASHBOARD ) {
                    continue;
                }

                // Log to notification table for non-dashboard channels.
                $notif_id = OAT_Notification::create( array(
                    'entry_id'   => $entry_id,
                    'user_id'    => $user_id,
                    'channel'    => $channel->get_channel_id(),
                    'event_type' => $event_type,
                ) );

                if ( $notif_id > 0 ) {
                    if ( $success ) {
                        OAT_Notification::mark_sent( $notif_id );
                    } else {
                        OAT_Notification::mark_failed( $notif_id );
                    }
                }
            }
        }
    }

    /**
     * Get active channel instances for v1.
     *
     * @return array Array of OAT_Notification_Channel instances.
     */
    public static function get_channels() {
        if ( self::$channels === null ) {
            self::$channels = array(
                new OAT_Channel_Email(),
                new OAT_Channel_Dashboard(),
            );

            /**
             * Filter active notification channels.
             *
             * @param array $channels Array of OAT_Notification_Channel instances.
             */
            self::$channels = apply_filters( 'oat_notification_channels', self::$channels );
        }

        return self::$channels;
    }

    /**
     * Resolve recipients for an event type.
     *
     * Merges canned recipients (from event map) with watchers, deduplicates.
     *
     * @param int    $entry_id
     * @param string $event_type
     * @param object $entry
     * @return array Flat array of unique user IDs.
     */
    public static function resolve_recipients( $entry_id, $event_type, $entry ) {
        $canned   = self::get_canned_recipients( $entry_id, $event_type, $entry );
        $watchers = OAT_Watcher::user_ids_for_entry( $entry_id );
        $merged   = array_merge( $canned, $watchers );

        // Deduplicate and reindex.
        $unique = array_values( array_unique( array_map( 'intval', $merged ) ) );

        // Remove zeros.
        return array_filter( $unique, function ( $id ) {
            return $id > 0;
        } );
    }

    /**
     * Get canned recipients based on the event-to-recipient map.
     *
     * @param int    $entry_id
     * @param string $event_type
     * @param object $entry
     * @return array Flat array of user IDs.
     */
    private static function get_canned_recipients( $entry_id, $event_type, $entry ) {
        $ids = array();

        switch ( $event_type ) {

            case self::EVENT_SUBMITTED:
                // Assignees at current step.
                $assignees = OAT_Assignee::for_entry_step( $entry_id, $entry->current_step );
                foreach ( $assignees as $a ) {
                    $ids[] = (int) $a->user_id;
                }
                break;

            case self::EVENT_APPROVED_AT_STEP:
                // Next step assignees (current_step already advanced when this fires).
                $assignees = OAT_Assignee::for_entry_step( $entry_id, $entry->current_step );
                foreach ( $assignees as $a ) {
                    $ids[] = (int) $a->user_id;
                }
                break;

            case self::EVENT_DENIED:
                // Originator.
                $ids[] = (int) $entry->originator_id;
                break;

            case self::EVENT_CHANGES_REQUESTED:
                // Person who must revise — originator or previous step assignees.
                $ids[] = (int) $entry->originator_id;
                break;

            case self::EVENT_RECORDED:
                // Originator.
                $ids[] = (int) $entry->originator_id;
                break;

            case self::EVENT_CANCELLED:
                // Current assignees.
                $assignees = OAT_Assignee::for_entry_step( $entry_id, $entry->current_step );
                foreach ( $assignees as $a ) {
                    $ids[] = (int) $a->user_id;
                }
                break;

            case self::EVENT_TIMER_WARNING:
                // Current assignee(s).
                $assignees = OAT_Assignee::for_entry_step( $entry_id, $entry->current_step );
                foreach ( $assignees as $a ) {
                    $ids[] = (int) $a->user_id;
                }
                break;

            case self::EVENT_TIMER_FIRED:
                // Assignee + originator.
                $ids[] = (int) $entry->originator_id;
                $assignees = OAT_Assignee::for_entry_step( $entry_id, $entry->current_step );
                foreach ( $assignees as $a ) {
                    $ids[] = (int) $a->user_id;
                }
                break;

            case self::EVENT_HELD:
                // Both parties — originator + current assignees.
                $ids[] = (int) $entry->originator_id;
                $assignees = OAT_Assignee::for_entry_step( $entry_id, $entry->current_step );
                foreach ( $assignees as $a ) {
                    $ids[] = (int) $a->user_id;
                }
                break;

            case self::EVENT_RESUMED:
                // Assignee.
                $assignees = OAT_Assignee::for_entry_step( $entry_id, $entry->current_step );
                foreach ( $assignees as $a ) {
                    $ids[] = (int) $a->user_id;
                }
                break;

            case self::EVENT_COUNCIL_OVERRIDE:
                // Originator + watchers (watchers merged later).
                $ids[] = (int) $entry->originator_id;
                break;
        }

        return $ids;
    }

    /**
     * Reset cached channels (for testing).
     *
     * @return void
     */
    public static function reset() {
        self::$channels = null;
    }
}
