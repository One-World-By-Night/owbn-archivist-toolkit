<?php

defined( 'ABSPATH' ) || exit;

/**
 * Requests filed together, with the same approvers at every step, that move through review as one group.
 *
 * Each line is still an ordinary request (its own timeline, rules and registry entry). A group is the first line (the
 * lead) linked to every other line by an oat_entry_relationships row of type ru_batch. Lines are put in a group when
 * they are filed, and only when every line has the same filer, form, chronicle and coordinator offices, so the same
 * people approve all of them.
 *
 * While a group action or a late line is being processed the per-line emails are muted (see mute()); the code that muted
 * them sends one email for the group instead.
 */
class OAT_Batch {

    const REL = 'ru_batch';

    /**
     * Depth of the mute switch.
     *
     * @var int
     */
    private static $mute = 0;

    public static function mute() {
        self::$mute++;
    }

    public static function unmute() {
        if ( self::$mute > 0 ) {
            self::$mute--;
        }
    }

    /**
     * Whether the per-line emails are held back right now.
     *
     * @return bool
     */
    public static function muted() {
        return self::$mute > 0;
    }

    /**
     * What decides whether two requests have the same approvers: filer, form, chronicle and coordinator offices.
     *
     * @param object $entry The entry object.
     * @return string
     */
    public static function signature( $entry ) {
        $offices = class_exists( 'OAT_Workflow_Engine' ) ? OAT_Workflow_Engine::coordinator_offices( $entry ) : array();
        sort( $offices );
        return implode( '|', array(
            (string) $entry->domain,
            isset( $entry->form_slug ) ? (string) $entry->form_slug : '',
            isset( $entry->chronicle_slug ) ? (string) $entry->chronicle_slug : '',
            (int) $entry->originator_id,
            implode( ',', $offices ),
        ) );
    }

    /**
     * The lead line of the group a line belongs to: the line itself when it leads one or is in none.
     *
     * @param int $entry_id Request id.
     * @return int
     */
    public static function lead_id( $entry_id ) {
        $entry_id = (int) $entry_id;
        if ( $entry_id <= 0 || ! class_exists( 'OAT_Entry_Relationship' ) ) {
            return $entry_id;
        }
        foreach ( (array) OAT_Entry_Relationship::for_target( $entry_id ) as $rel ) {
            if ( self::REL === $rel->relationship_type ) {
                return (int) $rel->source_entry_id;
            }
        }
        return $entry_id;
    }

    /**
     * Every line of the group a request is in, the lead first.
     *
     * @param int $entry_id Request id.
     * @return int[] Line ids; empty when the request is in no group.
     */
    public static function line_ids( $entry_id ) {
        if ( ! class_exists( 'OAT_Entry_Relationship' ) ) {
            return array();
        }
        $lead  = self::lead_id( $entry_id );
        $lines = array();
        foreach ( (array) OAT_Entry_Relationship::for_source( $lead ) as $rel ) {
            if ( self::REL === $rel->relationship_type ) {
                $lines[] = (int) $rel->target_entry_id;
            }
        }
        return $lines ? array_merge( array( $lead ), $lines ) : array();
    }

    /**
     * Puts a line that was just filed in the group of an earlier line of the same submission, when one has the same approvers.
     *
     * @param int   $entry_id  The new line.
     * @param int   $user_id   Who filed it.
     * @param int[] $prior_ids Lines already filed in this submission.
     * @return bool Whether the line joined a group.
     */
    public static function attach( $entry_id, $user_id, array $prior_ids ) {
        $entry = OAT_Entry::find( (int) $entry_id );
        if ( ! $entry || (int) $entry->originator_id !== (int) $user_id ) {
            return false;
        }
        $signature = self::signature( $entry );
        foreach ( array_slice( array_unique( array_map( 'intval', $prior_ids ) ), 0, 50 ) as $prior_id ) {
            $prior = $prior_id > 0 && $prior_id !== (int) $entry_id ? OAT_Entry::find( $prior_id ) : null;
            if ( ! $prior || (int) $prior->originator_id !== (int) $user_id || OAT_Constants::is_terminal_status( $prior->status ) ) {
                continue;
            }
            if ( self::signature( $prior ) !== $signature ) {
                continue;
            }
            OAT_Entry_Relationship::create( self::lead_id( $prior_id ), (int) $entry_id, self::REL );
            return true;
        }
        return false;
    }

    /**
     * Sends the usual "waiting for you" email for a line that was filed with the mail muted but joined no group.
     *
     * @param int $entry_id The line.
     * @return void
     */
    public static function mail_assignment( $entry_id ) {
        $entry = OAT_Entry::find( (int) $entry_id );
        if ( ! $entry || OAT_Constants::is_terminal_status( $entry->status ) || ! class_exists( 'OAT_Assignee' ) ) {
            return;
        }
        $users = array();
        foreach ( OAT_Assignee::for_entry_step( (int) $entry->id, $entry->current_step ) as $a ) {
            if ( 'pending' === $a->status ) {
                $users[] = (int) $a->user_id;
            }
        }
        if ( $users ) {
            OAT_Workflow_Notify::on_step_assigned( (int) $entry->id, $entry->current_step, $users );
        }
    }

    /**
     * After lines moved together: one email per person for the lines now waiting on them, and one to the filer for
     * the lines that were approved and recorded.
     *
     * @param int[] $line_ids Lines that were just approved.
     * @param int   $actor    Who approved them.
     * @return void
     */
    public static function announce_progress( array $line_ids, $actor ) {
        $waiting  = array();
        $approved = array();
        foreach ( $line_ids as $id ) {
            $line = OAT_Entry::find( (int) $id );
            if ( ! $line ) {
                continue;
            }
            if ( OAT_Constants::is_terminal_status( $line->status ) ) {
                if ( in_array( $line->status, array( OAT_Constants::STATUS_APPROVED, OAT_Constants::STATUS_AUTO_APPROVED ), true ) ) {
                    $approved[] = $line;
                }
                continue;
            }
            $waiting[ $line->current_step ][] = $line;
        }
        foreach ( $waiting as $step => $lines ) {
            OAT_Workflow_Notify::group_waiting( $lines, (string) $step, (int) $actor );
        }
        if ( $approved ) {
            OAT_Workflow_Notify::group_approved( $approved );
        }
    }
}
