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
 *
 * It also links characters to accounts, by email only: a character with no linked account whose player email equals an
 * account's email is linked to that account. That runs after each daily sync, when an account is made, and when someone signs in.
 */
class OAT_Account_Sync {

    const EVENT   = 'oat_account_sync_daily';
    const REPORT  = 'oat_account_sync_report';
    const HISTORY = 'oat_account_sync_history';
    const LINKS   = 'oat_account_sync_links';

    public static function init() {
        add_action( self::EVENT, array( __CLASS__, 'run' ) );
        add_action( 'admin_post_oat_account_sync_run', array( __CLASS__, 'handle_run_now' ) );
        add_action( 'admin_notices', array( __CLASS__, 'maybe_notice' ) );
        add_action( 'user_register', array( __CLASS__, 'on_user_register' ), 20 );
        add_action( 'set_auth_cookie', array( __CLASS__, 'on_sign_in' ), 20, 4 );
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
     * Unlinked characters whose player email equals an account's email (case and surrounding spaces ignored), one row per
     * character. A character whose email matches more than one account is left out, and so is account #1 (the web admin).
     *
     * @param int $user_id Limit to one account; 0 for all.
     * @return array Rows with character_id and user_id.
     */
    public static function linkable( $user_id = 0 ) {
        global $wpdb;
        $c    = $wpdb->prefix . 'oat_characters';
        $user = (int) $user_id;
        if ( $user ) {
            $u     = get_userdata( $user );
            $email = $u ? strtolower( trim( $u->user_email ) ) : '';
            if ( 1 === $user || ! is_email( $email ) ) {
                return array();
            }
            $holders = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->users} WHERE LOWER(TRIM(user_email)) = %s", $email ) );
            if ( 1 !== $holders ) {
                return array();
            }
            $ids = $wpdb->get_col( $wpdb->prepare(
                "SELECT id FROM {$c} WHERE ( wp_user_id IS NULL OR wp_user_id = 0 ) AND LOWER(TRIM(player_email)) = %s",
                $email
            ) );
            $rows = array();
            foreach ( (array) $ids as $id ) {
                $rows[] = array( 'character_id' => (int) $id, 'user_id' => $user );
            }
            return $rows;
        }
        $rows = $wpdb->get_results(
            "SELECT c.id AS character_id, MIN(x.ID) AS user_id
               FROM {$c} c JOIN {$wpdb->users} x ON LOWER(TRIM(x.user_email)) = LOWER(TRIM(c.player_email))
              WHERE ( c.wp_user_id IS NULL OR c.wp_user_id = 0 ) AND TRIM(c.player_email) <> '' AND x.ID <> 1
              GROUP BY c.id HAVING COUNT(*) = 1",
            ARRAY_A
        );
        return is_array( $rows ) ? $rows : array();
    }

    /**
     * Links each character in linkable() to its account. Each row is re-checked as it is written: still unlinked, and the
     * account still has that email. Every link is added to the log option, newest last.
     *
     * @param int $user_id Limit to one account; 0 for all.
     * @return int Characters linked.
     */
    public static function link_characters( $user_id = 0 ) {
        global $wpdb;
        $c      = $wpdb->prefix . 'oat_characters';
        $linked = array();
        foreach ( self::linkable( $user_id ) as $row ) {
            $n = (int) $wpdb->query( $wpdb->prepare(
                "UPDATE {$c} SET wp_user_id = %d, updated_at = %d
                  WHERE id = %d AND ( wp_user_id IS NULL OR wp_user_id = 0 )
                    AND LOWER(TRIM(player_email)) = ( SELECT LOWER(TRIM(user_email)) FROM {$wpdb->users} WHERE ID = %d )",
                (int) $row['user_id'],
                time(),
                (int) $row['character_id'],
                (int) $row['user_id']
            ) );
            if ( 1 === $n ) {
                $linked[] = array( 'at' => time(), 'character_id' => (int) $row['character_id'], 'user_id' => (int) $row['user_id'] );
            }
        }
        if ( $linked ) {
            $log = array_merge( (array) get_option( self::LINKS, array() ), $linked );
            update_option( self::LINKS, array_slice( $log, -500 ), false );
        }
        return count( $linked );
    }

    /**
     * A new account: link the characters carrying its email.
     *
     * @param int $user_id New user id.
     */
    public static function on_user_register( $user_id ) {
        self::link_characters( (int) $user_id );
    }

    /**
     * A sign-in (the auth cookie is set for every kind of sign-in): link the characters carrying this account's email.
     *
     * @param string $auth_cookie The cookie value (unused).
     * @param int    $expire      Expiry (unused).
     * @param int    $expiration  Expiry (unused).
     * @param int    $user_id     The user signing in.
     */
    public static function on_sign_in( $auth_cookie, $expire, $expiration, $user_id ) {
        if ( (int) $user_id > 0 ) {
            self::link_characters( (int) $user_id );
        }
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
        $would_link = count( self::linkable() );
        $linked     = $apply ? self::link_characters() : 0;

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
            'characters_linked'   => $linked,
            'characters_linkable' => $would_link,
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
            $history[] = array( 'at' => $start, 'www_total' => $report['www_total'], 'matched' => $report['matched'], 'created' => $report['created'], 'conflicts' => count( $conflicts ), 'errors' => count( $errors ), 'linked' => $linked );
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
