<?php
/**
 * OAT Coordinator Backfill / Derivation.
 *
 * One place for the logic that decides which coordinator authorities OWN an
 * entry, and for migrating legacy single-value coordinator_genre data into the
 * oat_entry_coordinators join table + oat_registry_access grants.
 *
 * Derivation tiers (highest confidence first):
 *   rule    — linked regulation rule's controlling_coordinator, parsed to slugs
 *   text    — a known coordinator slug appears in the entry's own meta text
 *   subtype — character.creature_sub_type is a known coordinator slug
 *   ctype   — character.creature_type is a known coordinator slug
 *   genre   — character.creature_genre maps to a coordinator (taxonomy)
 *
 * Reused by the backfill (one-shot) and the "Untagged R&U" review screen.
 */

defined( 'ABSPATH' ) || exit;

class OAT_Coordinator_Backfill {

    /** Forms that should carry a controlling coordinator. */
    const SHOULD_FORMS = array(
        'cl_ru_request', 'custom_content', 'cl_registration', 'binding_agreements',
        'cl_learn_custom_content', 'cl_transfer', 'cl_death', 'player_actions',
    );

    /** High-confidence tiers applied automatically; 'text'/'text-multi' route to review. */
    const AUTO_SOURCES = array( 'rule', 'subtype', 'ctype', 'genre' );

    /** Free-text normalization for known typos/spacing in source data. */
    const NORM = array(
        'changing breeds' => 'changing-breeds',
        'venture'         => 'ventrue',
        'anach'           => 'anarch',
    );

    /** creature_genre (lowercased) → coordinator slug. */
    const GENRE_COORD = array(
        'changing breeds' => 'changing-breeds',
        'mage'            => 'mage',
        'changeling'      => 'changeling',
        'demon'           => 'demon',
        'wraith'          => 'wraith',
        'kuei-jin'        => 'kuei-jin',
        'hunter'          => 'hunter',
        'mummy'           => 'mummy',
    );

    /**
     * The authoritative known coordinator slug set (lowercased).
     * Cached per request.
     *
     * @return array slug => true
     */
    public static function known_slugs() {
        static $known = null;
        if ( null !== $known ) {
            return $known;
        }
        global $wpdb;
        $g = $wpdb->prefix . 'oat_registry_access';
        $e = $wpdb->prefix . 'oat_entries';
        $known = array();
        foreach ( $wpdb->get_col( "SELECT DISTINCT LOWER(grant_value) FROM {$g} WHERE grant_type='coordinator' AND grant_value<>''" ) as $s ) {
            $known[ $s ] = true;
        }
        foreach ( $wpdb->get_col( "SELECT DISTINCT LOWER(coordinator_genre) FROM {$e} WHERE coordinator_genre<>''" ) as $s ) {
            $known[ $s ] = true;
        }
        return $known;
    }

    private static function norm( $v ) {
        $v = strtolower( trim( (string) $v ) );
        return self::NORM[ $v ] ?? $v;
    }

    /**
     * Tokenize a controlling_coordinator free-text string into candidate slugs.
     *
     * @param string $raw
     * @return array
     */
    public static function tokenize_authority( $raw ) {
        $s = strtolower( trim( (string) $raw ) );
        $s = preg_replace( '/\([^)]*\)/', ' ', $s );          // drop parentheticals
        $s = str_replace( array( '&', ' and ', '/', ';', '||' ), ',', $s );
        $out = array();
        foreach ( array_map( 'trim', explode( ',', $s ) ) as $p ) {
            if ( '' === $p ) {
                continue;
            }
            $out[] = self::NORM[ $p ] ?? $p;
        }
        return $out;
    }

    /**
     * Find known coordinator slugs appearing as words in free text.
     *
     * @param string $txt
     * @return array
     */
    public static function scan_text( $txt ) {
        $known = self::known_slugs();
        $t     = ' ' . strtolower( (string) $txt ) . ' ';
        $hits  = array();
        foreach ( array_keys( $known ) as $s ) {
            if ( strpos( $t, $s ) !== false && preg_match( '/(^|[^a-z])' . preg_quote( $s, '/' ) . '([^a-z]|$)/', $t ) ) {
                $hits[ $s ] = true;
            }
        }
        return array_keys( $hits );
    }

    /**
     * Derive the controlling authorities for one entry context.
     *
     * @param object $r  Row with: rules_txt, meta_txt, cg (creature_genre),
     *                   ct (creature_type), cst (creature_sub_type) — all lowercased.
     * @return array { authorities: string[], source: string }
     */
    public static function derive( $r ) {
        $known = self::known_slugs();

        // Tier 1: linked rule authorities.
        if ( ! empty( $r->rules_txt ) ) {
            $a = array();
            foreach ( self::tokenize_authority( $r->rules_txt ) as $t ) {
                if ( isset( $known[ $t ] ) ) {
                    $a[ $t ] = true;
                }
            }
            if ( $a ) {
                return array( 'authorities' => array_keys( $a ), 'source' => 'rule' );
            }
        }

        // Tier 2: entry text mentions a coordinator.
        $hits = self::scan_text( $r->meta_txt ?? '' );
        if ( $hits ) {
            return array( 'authorities' => $hits, 'source' => count( $hits ) > 1 ? 'text-multi' : 'text' );
        }

        // Tier 3: creature_sub_type.
        $cst = self::norm( $r->cst ?? '' );
        if ( '' !== $cst && isset( $known[ $cst ] ) ) {
            return array( 'authorities' => array( $cst ), 'source' => 'subtype' );
        }

        // Tier 4: creature_type.
        $ct = self::norm( $r->ct ?? '' );
        if ( '' !== $ct && isset( $known[ $ct ] ) ) {
            return array( 'authorities' => array( $ct ), 'source' => 'ctype' );
        }

        // Tier 5: creature_genre → coordinator.
        $cg = strtolower( trim( (string) ( $r->cg ?? '' ) ) );
        if ( isset( self::GENRE_COORD[ $cg ] ) ) {
            return array( 'authorities' => array( self::GENRE_COORD[ $cg ] ), 'source' => 'genre' );
        }

        return array( 'authorities' => array(), 'source' => 'null' );
    }

    /**
     * Fetch blank-coordinator_genre approved entries (should-be-tagged forms)
     * with the context needed for derivation.
     *
     * @param int|null $limit
     * @return array
     */
    public static function fetch_untagged( $limit = null ) {
        global $wpdb;
        $e  = $wpdb->prefix . 'oat_entries';
        $er = $wpdb->prefix . 'oat_entry_rules';
        $rr = $wpdb->prefix . 'oat_regulation_rules';
        $em = $wpdb->prefix . 'oat_entry_meta';
        $c  = $wpdb->prefix . 'oat_characters';

        $forms = "'" . implode( "','", array_map( 'esc_sql', self::SHOULD_FORMS ) ) . "'";
        $lim   = $limit ? ( ' LIMIT ' . (int) $limit ) : '';

        return $wpdb->get_results(
            "SELECT e.id, e.form_slug, e.character_id, c.character_name,
                    LOWER(c.creature_genre) cg, LOWER(c.creature_type) ct, LOWER(c.creature_sub_type) cst,
                    (SELECT GROUP_CONCAT(rr.controlling_coordinator SEPARATOR '||') FROM {$er} er JOIN {$rr} rr ON rr.id=er.rule_id WHERE er.entry_id=e.id) rules_txt,
                    (SELECT GROUP_CONCAT(m.meta_value SEPARATOR ' ') FROM {$em} m WHERE m.entry_id=e.id) meta_txt
             FROM {$e} e LEFT JOIN {$c} c ON c.id=e.character_id
             WHERE e.status='approved' AND (e.coordinator_genre IS NULL OR e.coordinator_genre='')
               AND e.form_slug IN ({$forms}){$lim}"
        );
    }

    /**
     * Migrate every already-tagged approved entry's coordinator_genre into the
     * join table, and ensure the character holds a matching coordinator grant.
     * Set-based and idempotent. Closes the pre-hook grant gap.
     *
     * @param bool $apply  When false, returns counts only (no writes).
     * @return array stats
     */
    public static function migrate_existing( $apply = false ) {
        global $wpdb;
        $e   = $wpdb->prefix . 'oat_entries';
        $ec  = $wpdb->prefix . 'oat_entry_coordinators';
        $g   = $wpdb->prefix . 'oat_registry_access';
        $now = time();

        $to_join = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$e} e
             WHERE e.status='approved' AND e.coordinator_genre<>''
               AND NOT EXISTS (SELECT 1 FROM {$ec} ec WHERE ec.entry_id=e.id AND ec.coordinator_slug=LOWER(e.coordinator_genre))"
        );
        $missing_grants = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT CONCAT(e.character_id,'|',LOWER(e.coordinator_genre))) FROM {$e} e
             WHERE e.status='approved' AND e.coordinator_genre<>'' AND e.character_id IS NOT NULL
               AND NOT EXISTS (
                 SELECT 1 FROM {$g} grt WHERE grt.character_id=e.character_id AND grt.grant_type='coordinator'
                   AND LOWER(grt.grant_value)=LOWER(e.coordinator_genre)
                   AND (grt.starts_at IS NULL OR grt.starts_at<={$now}) AND (grt.expires_at IS NULL OR grt.expires_at>={$now})
               )"
        );

        if ( $apply ) {
            // 1. Populate the join table from the legacy single value (approved only,
            //    matching the grant pass and the forward hook).
            $wpdb->query(
                "INSERT IGNORE INTO {$ec} (entry_id, coordinator_slug, source, created_at)
                 SELECT e.id, LOWER(e.coordinator_genre), 'migrate', {$now}
                 FROM {$e} e
                 WHERE e.status='approved' AND e.coordinator_genre<>''
                   AND NOT EXISTS (SELECT 1 FROM {$ec} ec WHERE ec.entry_id=e.id AND ec.coordinator_slug=LOWER(e.coordinator_genre))"
            );
            // 2. Create missing coordinator grants (idempotent; LOWER() both sides so
            //    the guard holds regardless of stored casing / collation).
            $wpdb->query(
                "INSERT INTO {$g} (character_id, grant_type, grant_value, granted_by, starts_at, expires_at, created_at)
                 SELECT DISTINCT e.character_id, 'coordinator', LOWER(e.coordinator_genre), NULL, NULL, NULL, {$now}
                 FROM {$e} e
                 WHERE e.status='approved' AND e.coordinator_genre<>'' AND e.character_id IS NOT NULL
                   AND NOT EXISTS (
                     SELECT 1 FROM {$g} grt WHERE grt.character_id=e.character_id AND grt.grant_type='coordinator'
                       AND LOWER(grt.grant_value)=LOWER(e.coordinator_genre)
                       AND (grt.starts_at IS NULL OR grt.starts_at<={$now}) AND (grt.expires_at IS NULL OR grt.expires_at>={$now})
                   )"
            );
        }

        return array(
            'join_rows_to_add'      => $to_join,
            'missing_grants_to_add' => $missing_grants,
            'applied'               => (bool) $apply,
        );
    }

    /**
     * Derive authorities for untagged entries and (optionally) apply the
     * high-confidence tiers — writing the join table, the legacy primary
     * column, and grants. text/text-multi/null are left for review.
     *
     * @param bool  $apply         When false, no writes (dry-run).
     * @param array $auto_sources  Tiers to auto-apply (default AUTO_SOURCES).
     * @return array stats { by_source, applied_entries, review_entries, null_entries, multi }
     */
    public static function backfill_blanks( $apply = false, $auto_sources = null ) {
        if ( null === $auto_sources ) {
            $auto_sources = self::AUTO_SOURCES;
        }
        $rows  = self::fetch_untagged();
        $stats = array(
            'by_source'       => array(),
            'applied_entries' => 0,
            'review_entries'  => 0,
            'null_entries'    => 0,
            'multi'           => 0,
            'grants_created'  => 0,
            'applied'         => (bool) $apply,
        );

        foreach ( $rows as $r ) {
            $d   = self::derive( $r );
            $src = $d['source'];
            $stats['by_source'][ $src ] = ( $stats['by_source'][ $src ] ?? 0 ) + 1;

            if ( 'null' === $src || empty( $d['authorities'] ) ) {
                $stats['null_entries']++;
                continue;
            }
            if ( count( $d['authorities'] ) > 1 ) {
                $stats['multi']++;
            }
            if ( ! in_array( $src, $auto_sources, true ) ) {
                $stats['review_entries']++;   // e.g. text / text-multi
                continue;
            }

            $stats['applied_entries']++;
            if ( $apply ) {
                OAT_Entry_Coordinator::set_for_entry( (int) $r->id, $d['authorities'], $src );
                // Keep legacy primary column populated for back-compat (first authority).
                OAT_Entry::update( (int) $r->id, array( 'coordinator_genre' => $d['authorities'][0] ) );
                // Grant each authority on the character.
                if ( $r->character_id ) {
                    foreach ( $d['authorities'] as $slug ) {
                        OAT_Registry_Access::ensure_grant( (int) $r->character_id, 'coordinator', $slug );
                        $stats['grants_created']++;
                    }
                }
            }
        }

        return $stats;
    }

    /**
     * Full backfill: migrate existing, then derive+apply blanks.
     *
     * @param bool $apply
     * @return array
     */
    public static function run( $apply = false ) {
        return array(
            'migrate'  => self::migrate_existing( $apply ),
            'blanks'   => self::backfill_blanks( $apply ),
        );
    }
}
