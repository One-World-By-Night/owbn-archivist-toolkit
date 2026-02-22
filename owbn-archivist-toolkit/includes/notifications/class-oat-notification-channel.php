<?php

defined( 'ABSPATH' ) || exit;

/**
 * Notification channel interface.
 *
 * Each channel (email, dashboard, API) implements this to provide delivery.
 */
interface OAT_Notification_Channel {

    /**
     * Channel identifier: 'email', 'dashboard', 'api'.
     *
     * @return string
     */
    public function get_channel_id();

    /**
     * Send a notification.
     *
     * @param int    $user_id    Recipient WP user ID.
     * @param string $event_type Notification event.
     * @param array  $context    Event context (entry, action, actor, etc).
     * @return bool Success.
     */
    public function send( $user_id, $event_type, $context );
}
