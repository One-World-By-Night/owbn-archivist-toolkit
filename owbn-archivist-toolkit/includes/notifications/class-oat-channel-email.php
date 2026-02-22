<?php

defined( 'ABSPATH' ) || exit;

class OAT_Channel_Email implements OAT_Notification_Channel {

    /**
     * @return string
     */
    public function get_channel_id() {
        return OAT_Constants::CHANNEL_EMAIL;
    }

    /**
     * Send notification via wp_mail().
     *
     * @param int    $user_id
     * @param string $event_type
     * @param array  $context
     * @return bool
     */
    public function send( $user_id, $event_type, $context ) {
        $user = get_userdata( $user_id );
        if ( ! $user || empty( $user->user_email ) ) {
            return false;
        }

        $subject = $this->build_subject( $event_type, $context );
        $body    = $this->build_body( $event_type, $context );

        return wp_mail( $user->user_email, $subject, $body );
    }

    /**
     * @param string $event_type
     * @param array  $context
     * @return string
     */
    private function build_subject( $event_type, $context ) {
        $domain   = isset( $context['domain'] ) ? $context['domain'] : 'OAT';
        $entry_id = isset( $context['entry_id'] ) ? $context['entry_id'] : '';
        $label    = str_replace( '_', ' ', $event_type );
        $label    = ucfirst( $label );

        return sprintf( '[OAT] %s — %s #%s', $label, $domain, $entry_id );
    }

    /**
     * @param string $event_type
     * @param array  $context
     * @return string
     */
    private function build_body( $event_type, $context ) {
        $lines = array();

        $lines[] = sprintf( 'Event: %s', str_replace( '_', ' ', $event_type ) );

        if ( isset( $context['entry_id'] ) ) {
            $lines[] = sprintf( 'Entry: #%d', $context['entry_id'] );
        }
        if ( isset( $context['domain'] ) ) {
            $lines[] = sprintf( 'Domain: %s', $context['domain'] );
        }
        if ( isset( $context['actor_name'] ) ) {
            $lines[] = sprintf( 'Action by: %s', $context['actor_name'] );
        }
        if ( isset( $context['step'] ) ) {
            $lines[] = sprintf( 'Step: %s', $context['step'] );
        }
        if ( ! empty( $context['note'] ) ) {
            $excerpt = substr( $context['note'], 0, 500 );
            $lines[] = '';
            $lines[] = 'Note:';
            $lines[] = $excerpt;
        }

        $lines[] = '';
        $lines[] = 'This is an automated notification from the OWbN Archivist Toolkit.';

        return implode( "\n", $lines );
    }
}
