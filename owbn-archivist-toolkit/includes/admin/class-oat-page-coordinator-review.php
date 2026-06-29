<?php
/**
 * OAT "Untagged R&U" review screen.
 *
 * Lists approved should-be-tagged entries with no controlling coordinator yet,
 * shows the derivation engine's suggestion + source, and lets staff bulk-apply
 * the high-confidence tiers or set authorities by hand (multi-select → join
 * table + grants). Reuses OAT_Coordinator_Backfill for derivation.
 */

defined( 'ABSPATH' ) || exit;

class OAT_Page_Coordinator_Review {

    const PER_PAGE = 50;

    /**
     * Handle POST actions before rendering (hooked on admin_init).
     */
    public static function maybe_handle() {
        if ( empty( $_POST['oat_cr_action'] ) ) {
            return;
        }
        if ( ! current_user_can( OAT_Constants::CAP_ARCHIVIST ) ) {
            return;
        }
        $action = sanitize_text_field( wp_unslash( $_POST['oat_cr_action'] ) );

        // Bulk auto-tag all high-confidence derivations.
        if ( 'autotag_all' === $action ) {
            check_admin_referer( 'oat_cr_autotag' );
            $stats = OAT_Coordinator_Backfill::backfill_blanks( true );
            $applied = (int) ( $stats['applied_entries'] ?? 0 );
            self::redirect( array( 'oat_cr_msg' => 'autotag', 'oat_cr_n' => $applied ) );
        }

        // Apply one entry's authorities (manual or accepted suggestion).
        if ( 'tag_entry' === $action ) {
            $entry_id = isset( $_POST['entry_id'] ) ? (int) $_POST['entry_id'] : 0;
            check_admin_referer( 'oat_cr_tag_' . $entry_id );
            $raw   = isset( $_POST['authorities'] ) ? wp_unslash( $_POST['authorities'] ) : '';
            $slugs = self::parse_slugs( $raw );
            if ( $entry_id && $slugs ) {
                self::apply_entry( $entry_id, $slugs );
                self::redirect( array( 'oat_cr_msg' => 'tagged', 'oat_cr_n' => 1 ) );
            }
            self::redirect( array( 'oat_cr_msg' => 'noslugs' ) );
        }
    }

    /**
     * Validate authority slugs against the known coordinator set.
     *
     * @param string $raw  Comma/space/pipe separated slugs.
     * @return array
     */
    private static function parse_slugs( $raw ) {
        $known = OAT_Coordinator_Backfill::known_slugs();
        $out   = array();
        foreach ( preg_split( '/[,|]+/', strtolower( (string) $raw ) ) as $p ) {
            $p = trim( $p );
            if ( '' !== $p && isset( $known[ $p ] ) ) {
                $out[ $p ] = true;
            }
        }
        return array_keys( $out );
    }

    /**
     * Write a single entry's authorities: join table + legacy primary + grants.
     *
     * @param int   $entry_id
     * @param array $slugs
     */
    private static function apply_entry( $entry_id, $slugs ) {
        OAT_Entry_Coordinator::set_for_entry( $entry_id, $slugs, 'manual' );
        OAT_Entry::update( $entry_id, array( 'coordinator_genre' => $slugs[0] ) );

        $entry = OAT_Entry::find( $entry_id );
        if ( $entry && ! empty( $entry->character_id ) ) {
            foreach ( $slugs as $slug ) {
                OAT_Registry_Access::ensure_grant( (int) $entry->character_id, 'coordinator', $slug );
            }
        }
    }

    private static function redirect( $args ) {
        $url = add_query_arg(
            array_merge( array( 'page' => 'oat-coordinator-review' ), $args ),
            admin_url( 'admin.php' )
        );
        wp_safe_redirect( $url );
        exit;
    }

    /**
     * Render the review screen.
     */
    public static function render() {
        if ( ! current_user_can( OAT_Constants::CAP_ARCHIVIST ) ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'owbn-archivist-toolkit' ) );
        }

        $only     = isset( $_GET['only'] ) ? sanitize_text_field( $_GET['only'] ) : '';
        $page_num = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;

        // Derive suggestions for every untagged entry.
        $rows = array();
        foreach ( OAT_Coordinator_Backfill::fetch_untagged() as $r ) {
            $d   = OAT_Coordinator_Backfill::derive( $r );
            $src = $d['source'];
            if ( 'null' === $only && 'null' !== $src ) {
                continue;
            }
            if ( 'review' === $only && ! in_array( $src, array( 'text', 'text-multi' ), true ) ) {
                continue;
            }
            $r->_authorities = $d['authorities'];
            $r->_source      = $src;
            $rows[] = $r;
        }

        $total   = count( $rows );
        $offset  = ( $page_num - 1 ) * self::PER_PAGE;
        $paged   = array_slice( $rows, $offset, self::PER_PAGE );
        $pages   = (int) ceil( $total / self::PER_PAGE );
        $base    = admin_url( 'admin.php?page=oat-coordinator-review' . ( $only ? '&only=' . urlencode( $only ) : '' ) );

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Untagged R&U', 'owbn-archivist-toolkit' ) . '</h1>';

        self::notice();

        echo '<p>' . esc_html__( 'Approved R&U-bearing entries with no controlling coordinator yet. Suggestions come from the derivation engine; review the text-derived and null rows before relying on them.', 'owbn-archivist-toolkit' ) . '</p>';

        // Filters.
        echo '<p>';
        foreach ( array( '' => 'All', 'review' => 'Text-derived (review)', 'null' => 'No suggestion (null)' ) as $k => $label ) {
            $url = admin_url( 'admin.php?page=oat-coordinator-review' . ( $k ? '&only=' . urlencode( $k ) : '' ) );
            $strong = ( $only === $k );
            echo ( $strong ? '<strong>' : '<a href="' . esc_url( $url ) . '">' ) . esc_html( $label ) . ( $strong ? '</strong>' : '</a>' ) . ' &nbsp;|&nbsp; ';
        }
        echo '</p>';

        // Bulk auto-tag.
        echo '<form method="post" style="margin:12px 0;">';
        wp_nonce_field( 'oat_cr_autotag' );
        echo '<input type="hidden" name="oat_cr_action" value="autotag_all">';
        echo '<button type="submit" class="button button-primary" onclick="return confirm(\'' . esc_attr__( 'Auto-tag all high-confidence derivations (rule, sub-type, creature-type, genre)? This writes join-table rows and coordinator grants.', 'owbn-archivist-toolkit' ) . '\');">'
            . esc_html__( 'Auto-tag all derivable (high-confidence)', 'owbn-archivist-toolkit' ) . '</button>';
        echo '</form>';

        echo '<p>' . sprintf( esc_html__( '%d untagged entries shown.', 'owbn-archivist-toolkit' ), (int) $total ) . '</p>';

        echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
        foreach ( array( 'Entry', 'Character', 'Form', 'Genre / Type / Sub-type', 'Suggestion', 'Source', 'Set authorities' ) as $h ) {
            echo '<th>' . esc_html( $h ) . '</th>';
        }
        echo '</tr></thead><tbody>';

        if ( empty( $paged ) ) {
            echo '<tr><td colspan="7">' . esc_html__( 'Nothing to tag.', 'owbn-archivist-toolkit' ) . '</td></tr>';
        }

        foreach ( $paged as $r ) {
            $suggest = implode( ', ', (array) $r->_authorities );
            echo '<tr>';
            echo '<td>#' . (int) $r->id . '</td>';
            echo '<td>' . esc_html( $r->character_name ?: ( '#' . (int) $r->character_id ) ) . '</td>';
            echo '<td>' . esc_html( $r->form_slug ) . '</td>';
            echo '<td>' . esc_html( trim( "{$r->cg} / {$r->ct} / {$r->cst}", ' /' ) ) . '</td>';
            echo '<td>' . esc_html( $suggest ?: '—' ) . '</td>';
            echo '<td>' . esc_html( $r->_source ) . '</td>';
            echo '<td><form method="post" style="display:flex;gap:4px;">';
            wp_nonce_field( 'oat_cr_tag_' . (int) $r->id );
            echo '<input type="hidden" name="oat_cr_action" value="tag_entry">';
            echo '<input type="hidden" name="entry_id" value="' . (int) $r->id . '">';
            echo '<input type="text" name="authorities" value="' . esc_attr( $suggest ) . '" placeholder="slug, slug" style="width:180px;">';
            echo '<button type="submit" class="button">' . esc_html__( 'Apply', 'owbn-archivist-toolkit' ) . '</button>';
            echo '</form></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        // Pagination.
        if ( $pages > 1 ) {
            echo '<div class="tablenav"><div class="tablenav-pages">';
            for ( $i = 1; $i <= $pages; $i++ ) {
                $url = $base . '&paged=' . $i;
                if ( $i === $page_num ) {
                    echo '<span class="tablenav-pages-navspan button disabled">' . $i . '</span> ';
                } else {
                    echo '<a class="button" href="' . esc_url( $url ) . '">' . $i . '</a> ';
                }
            }
            echo '</div></div>';
        }

        echo '</div>';
    }

    private static function notice() {
        if ( empty( $_GET['oat_cr_msg'] ) ) {
            return;
        }
        $msg = sanitize_text_field( $_GET['oat_cr_msg'] );
        $n   = isset( $_GET['oat_cr_n'] ) ? (int) $_GET['oat_cr_n'] : 0;
        $text = '';
        if ( 'autotag' === $msg ) {
            $text = sprintf( __( 'Auto-tagged %d entries.', 'owbn-archivist-toolkit' ), $n );
        } elseif ( 'tagged' === $msg ) {
            $text = __( 'Entry tagged.', 'owbn-archivist-toolkit' );
        } elseif ( 'noslugs' === $msg ) {
            $text = __( 'No valid coordinator slugs provided.', 'owbn-archivist-toolkit' );
        }
        if ( $text ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $text ) . '</p></div>';
        }
    }
}
