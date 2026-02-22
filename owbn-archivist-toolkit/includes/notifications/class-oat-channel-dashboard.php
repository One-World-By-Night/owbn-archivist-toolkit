<?php

defined( 'ABSPATH' ) || exit;

class OAT_Channel_Dashboard implements OAT_Notification_Channel {

    /**
     * @return string
     */
    public function get_channel_id() {
        return OAT_Constants::CHANNEL_DASHBOARD;
    }

    /**
     * Send notification by inserting to oat_notifications table.
     *
     * The DB write IS the delivery — dashboard notifications are read from the table.
     *
     * @param int    $user_id
     * @param string $event_type
     * @param array  $context
     * @return bool
     */
    public function send( $user_id, $event_type, $context ) {
        $entry_id = isset( $context['entry_id'] ) ? (int) $context['entry_id'] : 0;
        if ( ! $entry_id ) {
            return false;
        }

        $id = OAT_Notification::create( array(
            'entry_id'   => $entry_id,
            'user_id'    => $user_id,
            'channel'    => OAT_Constants::CHANNEL_DASHBOARD,
            'event_type' => $event_type,
        ) );

        if ( $id > 0 ) {
            OAT_Notification::mark_sent( $id );
            return true;
        }

        return false;
    }
}
