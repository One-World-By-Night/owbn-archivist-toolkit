<?php

defined( 'ABSPATH' ) || exit;

/**
 * Daily account sync: every account on www gets an account here, matched by email only, so a role holder who has never
 * signed in can still be assigned a request.
 *
 * The list comes from www's accounts/sync-list route (owbn-www), asked with a dedicated key held in the options
 * owbn_owc_accounts_remote_url and owbn_owc_accounts_remote_api_key. A www account whose email already belongs to an account
 * here is matched; one whose email is new gets a subscriber account the way the SSO client makes it; one whose email is new
 * but whose login belongs to another email is a conflict, reported and never created. Nothing is changed or deleted.
 */
class OAT_Account_Sync {

    const EVENT   = 'oat_account_sync_daily';
    const REPORT  = 'oat_account_sync_report';
    const HISTORY = 'oat_account_sync_history';

    public static function init() {
        add_action( self::EVENT, array( __CLASS__, 'run' ) );
        add_action( 'admin_post_oat_account_sync_run', array( __CLASS__, 'handle_run_now' ) );
        add_action( 'admin_notices', array( __CLASS__, 'maybe_notice' ) );
        if ( ! wp_next_scheduled( self::EVENT ) ) {
            wp_schedule_event( time() + 300, 'daily', self::EVENT );
        }
    }

    /**
     * Whether the www source is set.
     */
    public static function is_configured() {
        if ( ! function_exists( 'owc_option_name' ) ) {
            return false;
        }
        return '' !== trim( (string) get_option( owc_option_name( 'accounts_remote_url' ), '' ) )
            && '' !== (string) get_option( owc_option_name( 'accounts_remote_api_key' ), '' );
    }

    /**
     * The www account list.
     *
     * @return array|WP_Error Accounts with id, login, email, display_name, first_name, last_name, player_id.
     */
    public static function fetch() {
        if ( ! self::is_configured() || ! function_exists( 'owc_get_remote_base' ) || ! function_exists( 'owc_remote_request' ) ) {
            return new WP_Error( 'oat_sync_not_configured', 'The www account source is not set.' );
        }
        $base = owc_get_remote_base( 'accounts' );
        if ( '' === $base ) {
            return new WP_Error( 'oat_sync_bad_url', 'The www account source address is not valid.' );
        }
        $result = owc_remote_request( $base . 'accounts/sync-list', owc_get_remote_key( 'accounts' ), array() );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        $accounts = isset( $result['accounts'] ) && is_array( $result['accounts'] ) ? $result['accounts'] : array();
        if ( ! $accounts || ( isset( $result['total'] ) && (int) $result['total'] !== count( $accounts ) ) ) {
            return new WP_Error( 'oat_sync_bad_list', 'www sent an empty or incomplete account list.' );
        }
        return $accounts;
    }

    /**
     * Sorts www accounts by what this site holds, by email only.
     *
     * @param array $accounts www accounts.
     * @return array matched, create, conflict (with 'why' and 'holder_id'), skipped.
     */
    public static function classify( array $accounts ) {
        $out = array( 'matched' => array(), 'create' => array(), 'conflict' => array(), 'skipped' => array() );
        foreach ( $accounts as $a ) {
            $login = isset( $a['login'] ) ? (string) $a['login'] : '';
            $email = isset( $a['email'] ) ? (string) $a['email'] : '';
            if ( 1 === (int) ( isset( $a['id'] ) ? $a['id'] : 0 ) || '' === $login || ! is_email( $email ) ) {
                $out['skipped'][] = $a;
                continue;
            }
            if ( email_exists( $email ) ) {
                $out['matched'][] = $a;
                continue;
            }
            $holder = username_exists( $login );
            if ( $holder ) {
                $a['holder_id'] = (int) $holder;
                $a['why']       = 'the login belongs to another account here (#' . (int) $holder . ') with a different email';
                $out['conflict'][] = $a;
                continue;
            }
            $out['create'][] = $a;
        }
        return $out;
    }

    /**
     * Makes one account the way the SSO client does: random password, subscriber, names and player id copied.
     *
     * @return int|WP_Error The new user id.
     */
    public static function create_account( array $a ) {
        $id = wp_create_user( $a['login'], wp_generate_password( 12, false ), $a['email'] );
        if ( is_wp_error( $id ) ) {
            return $id;
        }
        $id = (int) $id;
        foreach ( array( 'first_name', 'last_name', 'player_id' ) as $key ) {
            if ( ! empty( $a[ $key ] ) ) {
                update_user_meta( $id, $key, sanitize_text_field( $a[ $key ] ) );
            }
        }
        if ( ! empty( $a['display_name'] ) && $a['display_name'] !== $a['login'] ) {
            wp_update_user( array( 'ID' => $id, 'display_name' => $a['display_name'] ) );
        }
        return $id;
    }

    /**
     * The scheduled run: fetch, create what is missing, store the report.
     *
     * @return array|WP_Error The report.
     */
    public static function run() {
        return self::sync( true );
    }

    /**
     * @param bool $apply False classifies and reports without creating anything.
     * @return array|WP_Error
     */
    public static function sync( $apply ) {
        $start    = time();
        $accounts = self::fetch();
        if ( is_wp_error( $accounts ) ) {
            $report = array( 'at' => $start, 'ok' => false, 'error' => $accounts->get_error_message() );
            update_option( self::REPORT, $report, false );
            return $accounts;
        }
        $mails = 0;
        $block = function () use ( &$mails ) {
            ++$mails;
            return true;
        };
        add_filter( 'pre_wp_mail', $block );
        $sorted  = self::classify( $accounts );
        $created = array();
        $errors  = array();
        if ( $apply ) {
            foreach ( $sorted['create'] as $a ) {
                $id = self::create_account( $a );
                if ( is_wp_error( $id ) ) {
                    $errors[] = $a['login'] . ': ' . $id->get_error_message();
                } else {
                    $created[] = $a['login'];
                }
            }
        }
        remove_filter( 'pre_wp_mail', $block );

        $conflicts = array();
        foreach ( $sorted['conflict'] as $c ) {
            $conflicts[] = array( 'login' => $c['login'], 'email' => $c['email'], 'holder_id' => $c['holder_id'], 'why' => $c['why'] );
        }
        $report = array(
            'at'           => $start,
            'ok'           => ! $errors,
            'applied'      => (bool) $apply,
            'www_total'    => count( $accounts ),
            'matched'      => count( $sorted['matched'] ),
            'created'      => count( $created ),
            'would_create' => count( $sorted['create'] ),
            'skipped'      => count( $sorted['skipped'] ),
            'conflicts'    => $conflicts,
            'errors'       => $errors,
            'created_logins' => array_slice( $created, 0, 100 ),
            'mail_blocked' => $mails,
            'seconds'      => time() - $start,
        );
        update_option( self::REPORT, $report, false );
        if ( $apply ) {
            $history   = (array) get_option( self::HISTORY, array() );
            $history[] = array( 'at' => $start, 'www_total' => $report['www_total'], 'matched' => $report['matched'], 'created' => $report['created'], 'conflicts' => count( $conflicts ), 'errors' => count( $errors ) );
            update_option( self::HISTORY, array_slice( $history, -30 ), false );
        }
        return $report;
    }

    public static function handle_run_now() {
        if ( ! current_user_can( OAT_Constants::CAP_ADMIN ) ) {
            wp_die( esc_html__( 'You do not have permission to do that.', 'owbn-archivist-toolkit' ) );
        }
        check_admin_referer( 'oat_account_sync_run' );
        $result = self::run();
        wp_safe_redirect( add_query_arg( array( 'page' => 'oat-account-sync', 'ran' => is_wp_error( $result ) ? 'fail' : 'ok' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * A notice for archivist admins when the sync is set up but the last run failed, or none succeeded in 36 hours.
     */
    public static function maybe_notice() {
        if ( ! self::is_configured() || ! current_user_can( OAT_Constants::CAP_ADMIN ) ) {
            return;
        }
        $report = get_option( self::REPORT, array() );
        $at     = isset( $report['at'] ) ? (int) $report['at'] : 0;
        $bad    = empty( $report ) ? false : ( empty( $report['ok'] ) || ( time() - $at ) > 36 * HOUR_IN_SECONDS );
        if ( ! $bad ) {
            return;
        }
        printf(
            '<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
            esc_html__( 'The daily account sync from www has not succeeded lately.', 'owbn-archivist-toolkit' ),
            esc_url( admin_url( 'admin.php?page=oat-account-sync' ) ),
            esc_html__( 'See Account Sync', 'owbn-archivist-toolkit' )
        );
    }
}
