<?php

defined( 'ABSPATH' ) || exit;

/**
 * Archivist > Account Sync: what the daily www account sync did last, its history, and the accounts it could not create.
 */
class OAT_Page_Account_Sync {

    public static function render() {
        if ( ! current_user_can( OAT_Constants::CAP_ADMIN ) ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'owbn-archivist-toolkit' ) );
        }
        global $wpdb;
        $report  = get_option( OAT_Account_Sync::REPORT, array() );
        $history = array_reverse( (array) get_option( OAT_Account_Sync::HISTORY, array() ) );
        $when    = function ( $t ) {
            return $t ? wp_date( 'Y-m-d H:i', (int) $t ) . ' (' . human_time_diff( (int) $t ) . ' ago)' : '';
        };
        echo '<div class="wrap oat-account-sync"><h1>' . esc_html__( 'Account Sync', 'owbn-archivist-toolkit' ) . '</h1>';
        echo '<p>' . esc_html__( 'Every account on www gets an account here, matched by email only, once a day. Nothing is changed or deleted: a new email gets a subscriber account, and the person signs in through SSO as usual. A character with no linked account is linked to the account whose email equals the player email on the character, after each sync and when that person signs in.', 'owbn-archivist-toolkit' ) . '</p>';
        if ( isset( $_GET['ran'] ) ) {
            echo '<div class="notice notice-' . ( 'ok' === $_GET['ran'] ? 'success' : 'error' ) . '"><p>' . esc_html( 'ok' === $_GET['ran'] ? __( 'The sync ran.', 'owbn-archivist-toolkit' ) : __( 'The sync could not run. See the last run below.', 'owbn-archivist-toolkit' ) ) . '</p></div>';
        }
        if ( ! OAT_Account_Sync::is_configured() ) {
            echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'The www account source is not set, so the sync is not running.', 'owbn-archivist-toolkit' ) . '</p></div></div>';
            return;
        }
        if ( empty( $report ) ) {
            echo '<p>' . esc_html__( 'It has not run yet.', 'owbn-archivist-toolkit' ) . '</p>';
        } elseif ( empty( $report['www_total'] ) && empty( $report['ok'] ) ) {
            echo '<div class="notice notice-error inline"><p><strong>' . esc_html__( 'Last run failed', 'owbn-archivist-toolkit' ) . '</strong> ' . esc_html( $when( $report['at'] ) ) . ': ' . esc_html( isset( $report['error'] ) ? $report['error'] : '' ) . '</p></div>';
        } else {
            $here     = (int) $report['matched'] + (int) $report['created'];
            $eligible = (int) $report['www_total'] - (int) $report['skipped'];
            echo '<table class="widefat striped" style="max-width:640px"><tbody>';
            $rows = array(
                __( 'Last run', 'owbn-archivist-toolkit' )                    => $when( $report['at'] ),
                __( 'Accounts on www', 'owbn-archivist-toolkit' )             => (int) $report['www_total'],
                __( 'Already here (same email)', 'owbn-archivist-toolkit' )   => (int) $report['matched'],
                __( 'Created in this run', 'owbn-archivist-toolkit' )         => (int) $report['created'],
                __( 'Skipped (no usable email, or the web admin)', 'owbn-archivist-toolkit' ) => (int) $report['skipped'],
                __( 'Could not create (login taken)', 'owbn-archivist-toolkit' ) => count( (array) $report['conflicts'] ),
                __( 'Characters linked to an account in this run', 'owbn-archivist-toolkit' ) => isset( $report['characters_linked'] ) ? (int) $report['characters_linked'] : 0,
                __( 'Characters with a player email and no linked account', 'owbn-archivist-toolkit' ) => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}oat_characters WHERE ( wp_user_id IS NULL OR wp_user_id = 0 ) AND TRIM(player_email) <> ''" ),
                __( 'Errors', 'owbn-archivist-toolkit' )                      => count( (array) $report['errors'] ),
            );
            foreach ( $rows as $label => $val ) {
                echo '<tr><th style="width:55%">' . esc_html( $label ) . '</th><td>' . esc_html( (string) $val ) . '</td></tr>';
            }
            echo '</tbody></table>';
            printf(
                '<p><strong>%s</strong></p>',
                esc_html( sprintf( /* translators: 1: accounts here, 2: accounts on www */ __( '%1$d of %2$d www accounts have an account here.', 'owbn-archivist-toolkit' ), $here, $eligible ) )
            );
            if ( ! empty( $report['conflicts'] ) ) {
                echo '<h2>' . esc_html__( 'Accounts it could not create', 'owbn-archivist-toolkit' ) . '</h2><table class="widefat striped"><thead><tr><th>www login</th><th>www email</th><th>Why</th></tr></thead><tbody>';
                foreach ( $report['conflicts'] as $c ) {
                    echo '<tr><td>' . esc_html( $c['login'] ) . '</td><td>' . esc_html( $c['email'] ) . '</td><td>' . esc_html( $c['why'] ) . '</td></tr>';
                }
                echo '</tbody></table>';
            }
            if ( ! empty( $report['errors'] ) ) {
                echo '<h2>' . esc_html__( 'Errors', 'owbn-archivist-toolkit' ) . '</h2><ul>';
                foreach ( $report['errors'] as $e ) {
                    echo '<li>' . esc_html( $e ) . '</li>';
                }
                echo '</ul>';
            }
        }
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:16px 0">';
        wp_nonce_field( 'oat_account_sync_run' );
        echo '<input type="hidden" name="action" value="oat_account_sync_run" />';
        submit_button( __( 'Run it now', 'owbn-archivist-toolkit' ), 'secondary', 'submit', false );
        echo '</form>';
        if ( $history ) {
            echo '<h2>' . esc_html__( 'Recent runs', 'owbn-archivist-toolkit' ) . '</h2><table class="widefat striped" style="max-width:640px"><thead><tr><th>Run</th><th>www</th><th>Matched</th><th>Created</th><th>Conflicts</th><th>Errors</th><th>Linked</th></tr></thead><tbody>';
            foreach ( array_slice( $history, 0, 14 ) as $h ) {
                echo '<tr><td>' . esc_html( wp_date( 'Y-m-d H:i', (int) $h['at'] ) ) . '</td><td>' . (int) $h['www_total'] . '</td><td>' . (int) $h['matched'] . '</td><td>' . (int) $h['created'] . '</td><td>' . (int) $h['conflicts'] . '</td><td>' . (int) $h['errors'] . '</td><td>' . ( isset( $h['linked'] ) ? (int) $h['linked'] : 0 ) . '</td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';
    }
}
