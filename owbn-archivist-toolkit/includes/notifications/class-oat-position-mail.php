<?php

defined( 'ABSPATH' ) || exit;

/**
 * The address a position uses. A person emailed because of a position they hold (a Coordinator office, a chronicle's CM, a
 * chronicle's HST) is emailed at the position's address, so the mailbox keeps its history when the person changes.
 *
 * Coordinator offices and CMs use the role address on their record (coord_info and cm_info display_email). An HST uses
 * theirs only when it is an @owbn.net address. Every other position, and any position without a usable address, has none: the
 * caller uses the person's account email.
 */
class OAT_Position_Mail {

    /**
     * The positions a person holds at a step that have an address, one per position. A person with several roles is only
     * placed by the roles this step is for: their other roles, and their player email, are not touched.
     *
     * @param WP_User $user    The assigned person.
     * @param object  $entry   The request.
     * @param string  $step_id The step the request is at.
     * @return array[] Each array( 'email' => address, 'label' => position name ); empty when none has an address.
     */
    public static function positions_for_assignee( $user, $entry, $step_id ) {
        $found = array();
        if ( ! $user || empty( $user->ID ) || ! class_exists( 'OAT_Workflow_Engine' ) ) {
            return $found;
        }
        $config = OAT_Workflow_Engine::get_step_config( $entry, $step_id );
        if ( ! is_array( $config ) || empty( $config['assignee_role'] ) ) {
            return $found;
        }
        $expanded = OAT_Workflow_Engine::expand_role_pattern( $entry, $config['assignee_role'] );
        foreach ( explode( '|', strtolower( $expanded ) ) as $path ) {
            $path = trim( $path );
            if ( '' === $path ) {
                continue;
            }
            $tries = array( $path );
            // A chronicle with no HST is reviewed by its CM, else its Staff: only then do those roles count.
            if ( preg_match( '#^chronicle/([^/]+)/hst$#', $path, $m ) && ! self::holders( $path ) ) {
                $tries[] = 'chronicle/' . $m[1] . '/cm';
                $tries[] = 'chronicle/' . $m[1] . '/staff';
            }
            foreach ( $tries as $try ) {
                $position = self::position_for_role( $try );
                if ( $position && self::holds( (int) $user->ID, $try ) ) {
                    $found[ strtolower( $position['email'] ) ] = $position;
                }
            }
        }
        return array_values( $found );
    }

    /**
     * A Coordinator office's position address.
     *
     * @param string $slug Office slug.
     * @return array|null array( 'email', 'label' ) or null.
     */
    public static function office_address( $slug ) {
        $record = self::coordinator_record( $slug );
        if ( ! $record ) {
            return null;
        }
        $email = isset( $record['coord_info']['display_email'] ) ? self::usable( $record['coord_info']['display_email'] ) : '';
        if ( '' === $email ) {
            return null;
        }
        return array( 'email' => $email, 'label' => isset( $record['title'] ) ? (string) $record['title'] : (string) $slug );
    }

    /**
     * The position a role path stands for, with its address, or null when it has none.
     *
     * @param string $path Lowercase role path.
     * @return array|null
     */
    private static function position_for_role( $path ) {
        $parts = explode( '/', trim( $path, '/' ) );
        if ( count( $parts ) < 3 ) {
            return null;
        }
        list( $kind, $slug, $role ) = $parts;
        if ( in_array( $kind, array( 'coordinator', 'exec' ), true ) && in_array( $role, array( 'coordinator', 'sub-coordinator' ), true ) ) {
            return self::office_address( $slug );
        }
        if ( 'chronicle' === $kind && in_array( $role, array( 'cm', 'hst' ), true ) ) {
            $chronicle = self::chronicle_record( $slug );
            if ( ! $chronicle ) {
                return null;
            }
            $key   = 'cm' === $role ? 'cm_info' : 'hst_info';
            $email = isset( $chronicle[ $key ]['display_email'] ) ? self::usable( $chronicle[ $key ]['display_email'] ) : '';
            if ( '' === $email || ( 'hst' === $role && ! str_ends_with( $email, '@owbn.net' ) ) ) {
                return null;
            }
            $title = isset( $chronicle['title'] ) ? (string) $chronicle['title'] : (string) $slug;
            return array( 'email' => $email, 'label' => $title . ' ' . strtoupper( $role ) );
        }
        return null;
    }

    /**
     * Whether a person holds a role, by the role's holders (looked up once per role in a request).
     *
     * @param int    $user_id Local user id.
     * @param string $path    Lowercase role path.
     * @return bool
     */
    private static function holds( $user_id, $path ) {
        return in_array( $user_id, self::holders( $path ), true );
    }

    /**
     * The local user ids holding a role (looked up once per role in a request).
     *
     * @param string $path Lowercase role path.
     * @return int[]
     */
    private static function holders( $path ) {
        static $memo = array();
        if ( ! isset( $memo[ $path ] ) ) {
            $memo[ $path ] = array_map( 'intval', OAT_Authorization::get_users_with_role( $path ) );
        }
        return $memo[ $path ];
    }

    private static function usable( $email ) {
        $email = trim( (string) $email );
        return ( is_email( $email ) && 'web@owbn.net' !== strtolower( $email ) ) ? $email : '';
    }

    private static function coordinator_record( $slug ) {
        $list = function_exists( 'owc_get_coordinators' ) ? owc_get_coordinators() : array();
        foreach ( is_array( $list ) ? $list : array() as $c ) {
            $c = (array) $c;
            if ( isset( $c['slug'] ) && strtolower( (string) $c['slug'] ) === strtolower( (string) $slug ) ) {
                return $c;
            }
        }
        return null;
    }

    private static function chronicle_record( $slug ) {
        $list = function_exists( 'owc_get_chronicles' ) ? owc_get_chronicles() : array();
        foreach ( is_array( $list ) ? $list : array() as $c ) {
            $c = (array) $c;
            if ( isset( $c['slug'] ) && strtolower( (string) $c['slug'] ) === strtolower( (string) $slug ) ) {
                return $c;
            }
        }
        return null;
    }
}
