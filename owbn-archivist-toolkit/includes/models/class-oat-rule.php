<?php
/**
 * OAT Rule Model.
 *
 * Generic rules engine. Each rule defines a condition to evaluate
 * at a specific point (submission, approval, etc.) and an action
 * to take when the condition matches (block, warn, require).
 */

defined( 'ABSPATH' ) || exit;

class OAT_Rule {

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'oat_rules';
    }

    /**
     * Find a single rule by ID.
     */
    public static function find( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE id = %d",
            $id
        ) );
    }

    /**
     * Get rules with optional filters and pagination.
     */
    public static function all( $args = array() ) {
        global $wpdb;
        $table = self::table();

        $where  = array( '1=1' );
        $values = array();

        if ( isset( $args['rule_type'] ) ) {
            $where[]  = 'rule_type = %s';
            $values[] = $args['rule_type'];
        }
        if ( isset( $args['domain'] ) && $args['domain'] !== '' ) {
            $where[]  = 'domain = %s';
            $values[] = $args['domain'];
        }
        if ( isset( $args['active'] ) ) {
            $where[]  = 'active = %d';
            $values[] = (int) $args['active'];
        }
        if ( ! empty( $args['search'] ) ) {
            $like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where[]  = '(label LIKE %s OR check_field LIKE %s OR message LIKE %s)';
            $values[] = $like;
            $values[] = $like;
            $values[] = $like;
        }

        $where_sql = implode( ' AND ', $where );
        $orderby   = 'priority ASC, id ASC';
        $per_page  = isset( $args['per_page'] ) ? absint( $args['per_page'] ) : 50;
        $offset    = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;

        $sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} LIMIT {$per_page} OFFSET {$offset}";

        if ( $values ) {
            return $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
        }
        return $wpdb->get_results( $sql );
    }

    /**
     * Count rules with filters.
     */
    public static function count( $args = array() ) {
        global $wpdb;
        $table = self::table();

        $where  = array( '1=1' );
        $values = array();

        if ( isset( $args['rule_type'] ) ) {
            $where[]  = 'rule_type = %s';
            $values[] = $args['rule_type'];
        }
        if ( isset( $args['domain'] ) && $args['domain'] !== '' ) {
            $where[]  = 'domain = %s';
            $values[] = $args['domain'];
        }
        if ( isset( $args['active'] ) ) {
            $where[]  = 'active = %d';
            $values[] = (int) $args['active'];
        }

        $where_sql = implode( ' AND ', $where );
        $sql       = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";

        if ( $values ) {
            return (int) $wpdb->get_var( $wpdb->prepare( $sql, $values ) );
        }
        return (int) $wpdb->get_var( $sql );
    }

    /**
     * Create a new rule.
     */
    public static function create( $data ) {
        global $wpdb;

        $required = array( 'check_field', 'message' );
        foreach ( $required as $field ) {
            if ( empty( $data[ $field ] ) ) {
                return new WP_Error( 'missing_field', "Required field: {$field}" );
            }
        }

        $insert = array(
            'rule_type'    => sanitize_text_field( $data['rule_type'] ?? 'submission_check' ),
            'label'        => sanitize_text_field( $data['label'] ?? '' ),
            'domain'       => sanitize_text_field( $data['domain'] ?? '' ),
            'form_slug'    => sanitize_text_field( $data['form_slug'] ?? '' ),
            'check_source' => sanitize_text_field( $data['check_source'] ?? 'entry' ),
            'check_field'  => sanitize_text_field( $data['check_field'] ),
            'operator'     => sanitize_text_field( $data['operator'] ?? 'equals' ),
            'check_value'  => sanitize_text_field( $data['check_value'] ?? '' ),
            'action'       => sanitize_text_field( $data['action'] ?? 'block' ),
            'message'      => sanitize_textarea_field( $data['message'] ),
            'active'       => isset( $data['active'] ) ? (int) $data['active'] : 1,
            'priority'     => isset( $data['priority'] ) ? (int) $data['priority'] : 10,
            'created_at'   => time(),
        );

        $wpdb->insert( self::table(), $insert );
        return $wpdb->insert_id ?: new WP_Error( 'db_error', 'Failed to create rule.' );
    }

    /**
     * Update a rule.
     */
    public static function update( $id, $data ) {
        global $wpdb;

        $allowed = array(
            'rule_type', 'label', 'domain', 'form_slug', 'check_source',
            'check_field', 'operator', 'check_value', 'action', 'message',
            'active', 'priority',
        );

        $update = array();
        $format = array();

        foreach ( $allowed as $col ) {
            if ( array_key_exists( $col, $data ) ) {
                if ( in_array( $col, array( 'active', 'priority' ), true ) ) {
                    $update[ $col ] = (int) $data[ $col ];
                    $format[]       = '%d';
                } else {
                    $update[ $col ] = sanitize_text_field( $data[ $col ] );
                    $format[]       = '%s';
                }
            }
        }

        if ( empty( $update ) ) {
            return false;
        }

        return $wpdb->update( self::table(), $update, array( 'id' => $id ), $format, array( '%d' ) );
    }

    /**
     * Toggle active status.
     */
    public static function toggle( $id ) {
        global $wpdb;
        $rule = self::find( $id );
        if ( ! $rule ) return false;
        return $wpdb->update(
            self::table(),
            array( 'active' => $rule->active ? 0 : 1 ),
            array( 'id' => $id ),
            array( '%d' ),
            array( '%d' )
        );
    }

    /**
     * Delete a rule.
     */
    public static function delete( $id ) {
        global $wpdb;
        return $wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );
    }

    /**
     * Get all active rules for a given domain and form, ordered by priority.
     */
    public static function get_active_for( $rule_type, $domain, $form_slug = '' ) {
        global $wpdb;
        $table = self::table();

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE rule_type = %s AND active = 1
               AND (domain = '' OR domain = %s)
               AND (form_slug = '' OR form_slug = %s)
             ORDER BY priority ASC, id ASC",
            $rule_type,
            $domain,
            $form_slug
        ) );
    }

    /**
     * Evaluate all active submission_check rules against an entry.
     *
     * Returns null if all pass, or a WP_Error with the first blocking message.
     *
     * @param object $entry  The OAT entry object.
     * @param array  $meta   Entry metadata (key => value).
     * @return null|WP_Error
     */
    public static function evaluate_submission( $entry, $meta = array() ) {
        $rules = self::get_active_for( 'submission_check', $entry->domain, $entry->form_slug ?? '' );

        if ( empty( $rules ) ) {
            return null;
        }

        foreach ( $rules as $rule ) {
            $value = self::resolve_value( $rule, $entry, $meta );
            $match = self::compare( $value, $rule->operator, $rule->check_value );

            if ( $match && $rule->action === 'block' ) {
                return new WP_Error( 'rule_blocked', $rule->message, array(
                    'rule_id' => $rule->id,
                    'label'   => $rule->label,
                ) );
            }
        }

        return null;
    }

    /**
     * Resolve the value to check based on the rule's check_source and check_field.
     */
    private static function resolve_value( $rule, $entry, $meta ) {
        switch ( $rule->check_source ) {
            case 'entry':
                // Check entry columns first, then meta.
                if ( isset( $entry->{$rule->check_field} ) ) {
                    return $entry->{$rule->check_field};
                }
                return $meta[ $rule->check_field ] ?? '';

            case 'chronicle':
                return self::resolve_chronicle_field( $entry->chronicle_slug ?? '', $rule->check_field );

            case 'character':
                return self::resolve_character_field( $entry->character_id ?? 0, $rule->check_field );

            default:
                return '';
        }
    }

    /**
     * Look up a chronicle field.
     *
     * Tries local post type first (council/chronicles sites),
     * then falls back to the client API (archivist site).
     */
    private static function resolve_chronicle_field( $slug, $field ) {
        if ( ! $slug ) {
            return '';
        }

        // Local: chronicle post type exists on this site.
        if ( post_type_exists( 'owbn_chronicle' ) ) {
            $posts = get_posts( array(
                'post_type'      => 'owbn_chronicle',
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'meta_query'     => array(
                    array( 'key' => 'chronicle_slug', 'value' => $slug ),
                ),
            ) );
            if ( ! empty( $posts ) ) {
                return get_post_meta( $posts[0]->ID, $field, true );
            }
        }

        // Remote: use client API to fetch chronicle detail.
        if ( function_exists( 'owc_get_chronicle_detail' ) ) {
            $detail = owc_get_chronicle_detail( $slug );
            if ( ! is_wp_error( $detail ) && is_array( $detail ) ) {
                return $detail[ $field ] ?? '';
            }
        }

        return '';
    }

    /**
     * Look up a character field from the OAT characters table.
     */
    private static function resolve_character_field( $character_id, $field ) {
        if ( ! $character_id ) {
            return '';
        }

        $char = OAT_Character::find( (int) $character_id );
        if ( ! $char ) {
            return '';
        }

        return $char->{$field} ?? '';
    }

    /**
     * Compare a resolved value against the rule's check_value using the operator.
     */
    private static function compare( $actual, $operator, $expected ) {
        $actual   = is_string( $actual ) ? strtolower( trim( $actual ) ) : $actual;
        $expected = strtolower( trim( $expected ) );

        switch ( $operator ) {
            case 'equals':
                return (string) $actual === $expected;

            case 'not_equals':
                return (string) $actual !== $expected;

            case 'in':
                $list = array_map( 'trim', explode( ',', $expected ) );
                return in_array( (string) $actual, $list, true );

            case 'not_in':
                $list = array_map( 'trim', explode( ',', $expected ) );
                return ! in_array( (string) $actual, $list, true );

            case 'empty':
                return empty( $actual );

            case 'not_empty':
                return ! empty( $actual );

            default:
                return false;
        }
    }
}
