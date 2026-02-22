<?php

defined( 'ABSPATH' ) || exit;

class OAT_Page_Inbox {

    /**
     * Render the inbox page.
     *
     * @return void
     */
    public static function render() {
        $user_id = get_current_user_id();

        // Filters.
        $domain_filter = isset( $_GET['domain'] ) ? sanitize_text_field( $_GET['domain'] ) : '';

        // Get pending assignments.
        $assignments = OAT_Assignee::inbox( $user_id );

        // Filter by domain if set.
        if ( $domain_filter ) {
            $assignments = array_filter( $assignments, function ( $a ) use ( $domain_filter ) {
                return isset( $a->domain ) && $a->domain === $domain_filter;
            } );
        }

        // Get watched entries.
        $watched = OAT_Watcher::for_user( $user_id );

        // Domain list for filter.
        $domains = OAT_Domain_Registry::get_all();

        include OAT_PLUGIN_DIR . 'templates/admin/inbox.php';
    }
}
