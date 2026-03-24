<?php

defined( 'ABSPATH' ) || exit;

class OAT_Regulation_Rule {

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'oat_regulation_rules';
    }
    public static function find( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE id = %d',
            $id
        ) );
    }
    public static function all( $args = array() ) {
        global $wpdb;
        $table = self::table();

        $where  = array();
        $values = array();

        if ( isset( $args['genre'] ) ) {
            $where[]  = 'genre = %s';
            $values[] = $args['genre'];
        }
        if ( isset( $args['pc_level'] ) ) {
            $where[]  = 'pc_level = %s';
            $values[] = $args['pc_level'];
        }
        if ( isset( $args['npc_level'] ) ) {
            $where[]  = 'npc_level = %s';
            $values[] = $args['npc_level'];
        }

        $active = isset( $args['active'] ) ? (int) $args['active'] : 1;
        $where[]  = 'active = %d';
        $values[] = $active;

        if ( isset( $args['search'] ) && $args['search'] !== '' ) {
            $like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where[]  = '(category LIKE %s OR subcategory LIKE %s OR condition_name LIKE %s OR controlling_coordinator LIKE %s OR section_ref LIKE %s)';
            $values[] = $like;
            $values[] = $like;
            $values[] = $like;
            $values[] = $like;
            $values[] = $like;
        }

        $sql = "SELECT * FROM {$table}";
        if ( $where ) {
            $sql .= ' WHERE ' . implode( ' AND ', $where );
        }
        $sql .= ' ORDER BY genre ASC, category ASC, subcategory ASC';

        $per_page = isset( $args['per_page'] ) ? absint( $args['per_page'] ) : 20;
        $offset   = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;
        $sql .= " LIMIT {$per_page} OFFSET {$offset}";

        return $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
    }
    public static function count( $args = array() ) {
        global $wpdb;
        $table = self::table();

        $where  = array();
        $values = array();

        if ( isset( $args['genre'] ) ) {
            $where[]  = 'genre = %s';
            $values[] = $args['genre'];
        }

        $active = isset( $args['active'] ) ? (int) $args['active'] : 1;
        $where[]  = 'active = %d';
        $values[] = $active;

        if ( isset( $args['search'] ) && $args['search'] !== '' ) {
            $like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where[]  = '(category LIKE %s OR subcategory LIKE %s OR condition_name LIKE %s OR controlling_coordinator LIKE %s OR section_ref LIKE %s)';
            $values[] = $like;
            $values[] = $like;
            $values[] = $like;
            $values[] = $like;
            $values[] = $like;
        }

        $sql = "SELECT COUNT(*) FROM {$table} WHERE " . implode( ' AND ', $where );
        return (int) $wpdb->get_var( $wpdb->prepare( $sql, $values ) );
    }

    /**
     * Get distinct genre values.
     *
     * @return array
     */
    public static function get_genres() {
        global $wpdb;
        return $wpdb->get_col( 'SELECT DISTINCT genre FROM ' . self::table() . ' WHERE active = 1 ORDER BY genre ASC' );
    }
    public static function search( $term, $genre = null ) {
        global $wpdb;
        $table = self::table();
        $like  = '%' . $wpdb->esc_like( $term ) . '%';

        $where  = array( 'active = 1' );
        $values = array();

        $where[]  = '(section_ref LIKE %s OR category LIKE %s OR subcategory LIKE %s OR condition_name LIKE %s)';
        $values[] = $like;
        $values[] = $like;
        $values[] = $like;
        $values[] = $like;

        if ( $genre !== null ) {
            $where[]  = 'genre = %s';
            $values[] = $genre;
        }

        $sql = "SELECT id, genre, category, subcategory, condition_name, pc_level, npc_level, controlling_coordinator, section_ref, elevation
                FROM {$table}
                WHERE " . implode( ' AND ', $where ) . '
                ORDER BY genre ASC, category ASC
                LIMIT 20';

        return $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
    }
    public static function create( $data ) {
        global $wpdb;

        // Coerce empty strings to null for nullable columns (matches import_csv behavior).
        foreach ( array( 'pc_level', 'npc_level', 'subcategory', 'condition_name', 'section_ref' ) as $nullable_col ) {
            if ( isset( $data[ $nullable_col ] ) && $data[ $nullable_col ] === '' ) {
                $data[ $nullable_col ] = null;
            }
        }

        $allowed = array(
            'genre', 'category', 'subcategory', 'condition_name',
            'pc_level', 'npc_level', 'controlling_coordinator',
            'section_ref', 'elevation', 'active',
        );

        $insert = array();
        $format = array();

        foreach ( $allowed as $col ) {
            if ( isset( $data[ $col ] ) ) {
                if ( in_array( $col, array( 'elevation', 'active' ), true ) ) {
                    $insert[ $col ] = (int) $data[ $col ];
                } elseif ( $data[ $col ] !== null ) {
                    $insert[ $col ] = sanitize_text_field( $data[ $col ] );
                } else {
                    $insert[ $col ] = null;
                }
                $format[] = in_array( $col, array( 'elevation', 'active' ), true ) ? '%d' : '%s';
            }
        }

        if ( ! isset( $insert['elevation'] ) ) {
            $insert['elevation'] = 0;
            $format[] = '%d';
        }
        if ( ! isset( $insert['active'] ) ) {
            $insert['active'] = 1;
            $format[] = '%d';
        }

        foreach ( array( 'pc_level', 'npc_level' ) as $level_col ) {
            if ( isset( $insert[ $level_col ] ) && $insert[ $level_col ] !== null ) {
                if ( ! OAT_Constants::is_valid_regulation_level( $insert[ $level_col ] ) ) {
                    return 0;
                }
            }
        }

        $insert['created_at'] = time();
        $format[] = '%d';

        $wpdb->insert( self::table(), $insert, $format );
        return (int) $wpdb->insert_id;
    }

    /**
     * Immutable update per D-043: deactivate old row, insert new.
     *
     * @param int   $id
     * @param array $data
     * @return int New row ID, or 0 on failure.
     */
    public static function update( $id, $data ) {
        $old = self::find( $id );
        if ( ! $old ) {
            return 0;
        }

        self::deactivate( $id );

        $new_data = array(
            'genre'                  => isset( $data['genre'] ) ? $data['genre'] : $old->genre,
            'category'               => isset( $data['category'] ) ? $data['category'] : $old->category,
            'subcategory'            => isset( $data['subcategory'] ) ? $data['subcategory'] : $old->subcategory,
            'condition_name'         => isset( $data['condition_name'] ) ? $data['condition_name'] : $old->condition_name,
            'pc_level'               => isset( $data['pc_level'] ) ? $data['pc_level'] : $old->pc_level,
            'npc_level'              => isset( $data['npc_level'] ) ? $data['npc_level'] : $old->npc_level,
            'controlling_coordinator' => isset( $data['controlling_coordinator'] ) ? $data['controlling_coordinator'] : $old->controlling_coordinator,
            'section_ref'            => isset( $data['section_ref'] ) ? $data['section_ref'] : $old->section_ref,
            'elevation'              => isset( $data['elevation'] ) ? $data['elevation'] : $old->elevation,
            'active'                 => 1,
        );

        return self::create( $new_data );
    }
    public static function deactivate( $id ) {
        global $wpdb;
        return (bool) $wpdb->update(
            self::table(),
            array( 'active' => 0 ),
            array( 'id' => $id ),
            array( '%d' ),
            array( '%d' )
        );
    }
    public static function find_by_section_ref( $section_ref ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE section_ref = %s AND active = 1 LIMIT 1',
            $section_ref
        ) );
    }

    private static function content_signature( $row ) {
        $parts = array(
            is_object( $row ) ? $row->genre : ( $row['genre'] ?? '' ),
            is_object( $row ) ? $row->category : ( $row['category'] ?? '' ),
            is_object( $row ) ? $row->subcategory : ( $row['subcategory'] ?? '' ),
            is_object( $row ) ? $row->condition_name : ( $row['condition_name'] ?? '' ),
            is_object( $row ) ? $row->controlling_coordinator : ( $row['controlling_coordinator'] ?? '' ),
        );
        return strtolower( implode( '|', array_map( 'trim', $parts ) ) );
    }

    private static function content_changed( $old, $new_data ) {
        $fields = array( 'genre', 'category', 'subcategory', 'condition_name',
                         'pc_level', 'npc_level', 'controlling_coordinator' );
        foreach ( $fields as $f ) {
            $old_val = is_object( $old ) ? ( $old->$f ?? '' ) : '';
            $new_val = $new_data[ $f ] ?? '';
            if ( (string) $old_val !== (string) $new_val ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Smart sync: three-pass upsert that preserves rule IDs for unchanged rules.
     *
     * Pass 1: Match by section_ref. Update in place if only section_ref moved,
     *         immutable update if content changed.
     * Pass 2: Match unmatched rows by content signature (renumbering detection).
     *         Update section_ref in place.
     * Pass 3: Deactivate unmatched old rows. Insert unmatched new rows.
     */
    public static function sync_csv( $file_path ) {
        global $wpdb;
        $table = self::table();

        $result = array(
            'unchanged' => 0,
            'updated'   => 0,
            'ref_moved' => 0,
            'inserted'  => 0,
            'removed'   => 0,
            'skipped'   => 0,
            'errors'    => array(),
        );

        if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
            $result['errors'][] = 'File not found or not readable.';
            return $result;
        }

        $handle = fopen( $file_path, 'r' );
        if ( ! $handle ) {
            $result['errors'][] = 'Could not open file.';
            return $result;
        }

        $header = fgetcsv( $handle );
        if ( ! $header ) {
            fclose( $handle );
            $result['errors'][] = 'Empty CSV file.';
            return $result;
        }
        $header = array_map( 'trim', $header );

        // Load all incoming rows.
        $incoming = array();
        $line = 1;
        while ( ( $row = fgetcsv( $handle ) ) !== false ) {
            $line++;
            if ( count( $row ) !== count( $header ) ) {
                $result['errors'][] = "Line {$line}: column count mismatch.";
                $result['skipped']++;
                continue;
            }
            $data = array_combine( $header, $row );
            if ( empty( $data['genre'] ) || empty( $data['category'] ) || empty( $data['controlling_coordinator'] ) ) {
                $result['errors'][] = "Line {$line}: missing required fields.";
                $result['skipped']++;
                continue;
            }
            $data['elevation'] = isset( $data['elevation'] ) ? (int) $data['elevation'] : 0;
            foreach ( array( 'pc_level', 'npc_level', 'subcategory', 'condition_name', 'section_ref' ) as $nullable ) {
                if ( isset( $data[ $nullable ] ) && $data[ $nullable ] === '' ) {
                    $data[ $nullable ] = null;
                }
            }
            $incoming[] = $data;
        }
        fclose( $handle );

        // Load all active existing rules, indexed by section_ref and content signature.
        $existing = $wpdb->get_results( "SELECT * FROM {$table} WHERE active = 1" );
        $by_ref  = array();
        $by_sig  = array();
        foreach ( $existing as $row ) {
            if ( $row->section_ref ) {
                $by_ref[ $row->section_ref ] = $row;
            }
            $sig = self::content_signature( $row );
            $by_sig[ $sig ] = $row;
        }

        $matched_existing_ids = array();
        $unmatched_incoming   = array();

        // Pass 1: Match by section_ref.
        foreach ( $incoming as $data ) {
            $ref = $data['section_ref'] ?? null;
            if ( $ref && isset( $by_ref[ $ref ] ) ) {
                $old = $by_ref[ $ref ];
                $matched_existing_ids[ $old->id ] = true;

                if ( self::content_changed( $old, $data ) ) {
                    // Content changed at same section_ref — immutable update.
                    self::update( (int) $old->id, $data );
                    $result['updated']++;
                } else {
                    // Identical — nothing to do.
                    $result['unchanged']++;
                }
            } else {
                $unmatched_incoming[] = $data;
            }
        }

        // Pass 2: Match unmatched incoming by content signature (renumbering).
        $still_unmatched = array();
        foreach ( $unmatched_incoming as $data ) {
            $sig = self::content_signature( $data );
            if ( isset( $by_sig[ $sig ] ) && ! isset( $matched_existing_ids[ $by_sig[ $sig ]->id ] ) ) {
                $old = $by_sig[ $sig ];
                $matched_existing_ids[ $old->id ] = true;
                $new_ref = $data['section_ref'] ?? null;

                // Same content, different section_ref — just update the ref in place.
                if ( $new_ref && $new_ref !== $old->section_ref ) {
                    $wpdb->update(
                        $table,
                        array( 'section_ref' => $new_ref ),
                        array( 'id' => $old->id ),
                        array( '%s' ),
                        array( '%d' )
                    );
                    $result['ref_moved']++;
                } else {
                    $result['unchanged']++;
                }
            } else {
                $still_unmatched[] = $data;
            }
        }

        // Pass 3: Insert new, deactivate removed.
        foreach ( $still_unmatched as $data ) {
            $data['active'] = 1;
            $id = self::create( $data );
            if ( $id ) {
                $result['inserted']++;
            } else {
                $result['errors'][] = 'Insert failed for: ' . ( $data['section_ref'] ?? $data['condition_name'] ?? '(unknown)' );
                $result['skipped']++;
            }
        }

        foreach ( $existing as $old ) {
            if ( ! isset( $matched_existing_ids[ $old->id ] ) ) {
                self::deactivate( (int) $old->id );
                $result['removed']++;
            }
        }

        return $result;
    }

    public static function import_csv( $file_path ) {
        $result = array( 'inserted' => 0, 'skipped' => 0, 'errors' => array() );

        if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
            $result['errors'][] = 'File not found or not readable.';
            return $result;
        }

        $handle = fopen( $file_path, 'r' );
        if ( ! $handle ) {
            $result['errors'][] = 'Could not open file.';
            return $result;
        }

        $header = fgetcsv( $handle );
        if ( ! $header ) {
            fclose( $handle );
            $result['errors'][] = 'Empty CSV file.';
            return $result;
        }

        $header = array_map( 'trim', $header );
        $line   = 1;

        while ( ( $row = fgetcsv( $handle ) ) !== false ) {
            $line++;
            if ( count( $row ) !== count( $header ) ) {
                $result['errors'][] = "Line {$line}: column count mismatch.";
                $result['skipped']++;
                continue;
            }

            $data = array_combine( $header, $row );
            if ( empty( $data['genre'] ) || empty( $data['category'] ) || empty( $data['controlling_coordinator'] ) ) {
                $result['errors'][] = "Line {$line}: missing required fields.";
                $result['skipped']++;
                continue;
            }

            $data['elevation'] = isset( $data['elevation'] ) ? (int) $data['elevation'] : 0;

            foreach ( array( 'pc_level', 'npc_level', 'subcategory', 'condition_name', 'section_ref' ) as $nullable ) {
                if ( isset( $data[ $nullable ] ) && $data[ $nullable ] === '' ) {
                    $data[ $nullable ] = null;
                }
            }

            $id = self::create( $data );
            if ( $id ) {
                $result['inserted']++;
            } else {
                $result['errors'][] = "Line {$line}: insert failed.";
                $result['skipped']++;
            }
        }

        fclose( $handle );
        return $result;
    }
}
