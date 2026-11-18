<?php

defined( 'ABSPATH' ) || exit;

/**
 * The address a position uses. A person emailed because of a position they hold (a Coordinator office, a chronicle's CM, a
 * chronicle's HST) is emailed at the position's address, so the mailbox keeps its history when the person changes.
 *
 * Coordinator offices, CMs and HSTs use the role address on their record (coord_info, cm_info and hst_info display_email: the
 * ST email for a chronicle). Every other position, and any position without a usable address, has none: the caller uses the
 * person's account email.
 *
 * Mail about a request goes by what the request is about: the person's own character is their own email; something in their
 * chronicle is their ST email; something in their Coordinator office is that office's email.
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
     * The positions a request's filer acted in, with their addresses, for the mail that tells them how it ended. A request
     * filed as Player (declared on the form, or inferred because it is about their own character) has none: it goes to their
     * account email. Filed as Staff, or about something in a chronicle they are HST or CM of: that position. Filed as a
     * Coordinator, or about something in an office they hold: that office. The form's submitter_role wins when it records one.
     *
     * @param object $entry The request.
     * @return array[] Each array( 'email' => address, 'label' => position name ); empty means the account email.
     */
    public static function filer_positions( $entry ) {
        $filer = isset( $entry->originator_id ) ? (int) $entry->originator_id : 0;
        $user  = $filer > 1 ? get_userdata( $filer ) : null;
        if ( ! $user || ! class_exists( 'OAT_Entry_Meta' ) ) {
            return array();
        }
        $declared = strtolower( trim( (string) OAT_Entry_Meta::get( (int) $entry->id, 'submitter_role' ) ) );
        if ( 'player' === $declared || ( '' === $declared && self::owns_character( $user, $entry ) ) ) {
            return array();
        }
        $slug  = ! empty( $entry->chronicle_slug ) ? (string) $entry->chronicle_slug : (string) OAT_Entry_Meta::get( (int) $entry->id, 'chronicle_slug' );
        $staff = '' === $slug ? array() : array( 'chronicle/' . strtolower( $slug ) . '/hst', 'chronicle/' . strtolower( $slug ) . '/cm' );
        $coord = array();
        if ( class_exists( 'OAT_Workflow_Engine' ) ) {
            foreach ( OAT_Workflow_Engine::coordinator_offices( $entry ) as $office ) {
                $coord[] = 'coordinator/' . strtolower( $office ) . '/coordinator';
            }
        }
        if ( 'archivist' === $declared ) {
            return self::held_positions( (int) $user->ID, array( 'exec/archivist/coordinator' ) );
        }
        if ( 'coordinator' === $declared ) {
            return self::held_positions( (int) $user->ID, $coord );
        }
        if ( 'staff' === $declared ) {
            return self::held_positions( (int) $user->ID, $staff );
        }
        $found = self::held_positions( (int) $user->ID, $staff );
        return $found ? $found : self::held_positions( (int) $user->ID, $coord );
    }

    /**
     * The positions among some role paths that a person holds and that have an address.
     *
     * @param int      $user_id Local user id.
     * @param string[] $paths   Lowercase role paths.
     * @return array[]
     */
    private static function held_positions( $user_id, array $paths ) {
        $found = array();
        foreach ( $paths as $path ) {
            $position = self::position_for_role( $path );
            if ( $position && self::holds( $user_id, $path ) ) {
                $found[ strtolower( $position['email'] ) ] = $position;
            }
        }
        return array_values( $found );
    }

    /**
     * Whether the request is about the person's own character: linked to their account, or carrying their account email.
     *
     * @param WP_User $user  The filer.
     * @param object  $entry The request.
     * @return bool
     */
    private static function owns_character( $user, $entry ) {
        $character_id = isset( $entry->character_id ) ? (int) $entry->character_id : 0;
        if ( ! $character_id || ! class_exists( 'OAT_Character' ) ) {
            return false;
        }
        $character = OAT_Character::find( $character_id );
        return $character && ( (int) $character->wp_user_id === (int) $user->ID || 0 === strcasecmp( trim( (string) $character->player_email ), (string) $user->user_email ) );
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
            if ( '' === $email ) {
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
