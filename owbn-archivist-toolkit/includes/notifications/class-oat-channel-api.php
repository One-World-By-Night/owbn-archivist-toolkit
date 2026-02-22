<?php

defined( 'ABSPATH' ) || exit;

class OAT_Channel_API implements OAT_Notification_Channel {

    /**
     * @return string
     */
    public function get_channel_id() {
        return OAT_Constants::CHANNEL_API;
    }

    /**
     * Send notification to external REST endpoint (stubbed for v1).
     *
     * Logs the event but does not send — no external endpoint configured in v1.
     *
     * @param int    $user_id
     * @param string $event_type
     * @param array  $context
     * @return bool
     */
    public function send( $user_id, $event_type, $context ) {
        // v1 stub: no external endpoint. Just return true to indicate "handled."
        // In v2 this will POST to the configured API endpoint via wp_remote_post().
        return true;
    }
}
