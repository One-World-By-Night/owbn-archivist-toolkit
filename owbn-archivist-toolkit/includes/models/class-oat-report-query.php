<?php

defined( 'ABSPATH' ) || exit;

/**
 * Report query helpers for R&U distribution reports.
 *
 * All reports query oat_characters + oat_entries directly.
 * No dedicated reports table — these are live aggregate queries.
 */
class OAT_Report_Query {

	private static function chars_table() {
		global $wpdb;
		return $wpdb->prefix . 'oat_characters';
	}

	private static function entries_table() {
		global $wpdb;
		return $wpdb->prefix . 'oat_entries';
	}

	/**
	 * Build pc_npc WHERE clause from args.
	 *
	 * @param array  $args  Must contain 'pc_npc' key ('pc', 'npc', or 'all').
	 * @param string $alias Table alias (e.g. 'c' or empty).
	 * @return array WHERE clauses array.
	 */
	private static function pc_npc_where( $args, $alias = '' ) {
		$prefix = $alias ? "{$alias}." : '';
		$pc_npc = $args['pc_npc'] ?? 'pc';

		if ( 'all' === $pc_npc ) {
			return array();
		}

		return array( "{$prefix}pc_npc = '{$pc_npc}'" );
	}

	/**
	 * Build scope WHERE clauses that restrict results to the user's visible characters.
	 *
	 * @param array|null $scope  Scope array from OAT_Page_Reports::get_user_scope().
	 * @param string     $alias  Table alias for oat_characters (e.g. 'c').
	 * @return array     WHERE clause fragments to AND into the query.
	 */
	private static function build_scope_where( $scope, $alias = 'c' ) {
		if ( ! $scope || ! empty( $scope['is_global'] ) ) {
			return array();
		}

		$prefix     = $alias ? "{$alias}." : '';
		$conditions = array();

		if ( ! empty( $scope['chronicles'] ) ) {
			$slugs        = implode( "','", array_map( 'esc_sql', $scope['chronicles'] ) );
			$conditions[] = "{$prefix}chronicle_slug IN ('{$slugs}')";
		}

		if ( ! empty( $scope['character_ids'] ) ) {
			$ids          = implode( ',', array_map( 'intval', $scope['character_ids'] ) );
			$conditions[] = "{$prefix}id IN ({$ids})";
		}

		if ( empty( $conditions ) ) {
			return array( '1 = 0' );
		}

		return array( '(' . implode( ' OR ', $conditions ) . ')' );
	}

	/**
	 * Get chronicle → region lookup from owbn-client.
	 *
	 * @return array slug => region
	 */
	public static function get_chronicle_regions() {
		static $map = null;
		if ( null !== $map ) {
			return $map;
		}

		$map = array();
		if ( function_exists( 'owc_get_chronicles' ) ) {
			$chronicles = owc_get_chronicles();
			foreach ( $chronicles as $ch ) {
				$ch   = (array) $ch;
				$slug = $ch['slug'] ?? '';
				if ( $slug ) {
					$map[ $slug ] = $ch['chronicle_region'] ?? '';
				}
			}
		}
		return $map;
	}

	/**
	 * Get chronicle slug → title lookup from owbn-client.
	 *
	 * @return array slug => title
	 */
	public static function get_chronicle_titles() {
		static $map = null;
		if ( null !== $map ) {
			return $map;
		}

		$map = array();
		if ( function_exists( 'owc_get_chronicles' ) ) {
			$chronicles = owc_get_chronicles();
			foreach ( $chronicles as $ch ) {
				$ch   = (array) $ch;
				$slug = $ch['slug'] ?? '';
				if ( $slug ) {
					$map[ $slug ] = $ch['title'] ?? $slug;
				}
			}
		}
		return $map;
	}

	/**
	 * Get all chronicle data from owbn-client.
	 *
	 * @return array
	 */
	public static function get_chronicles() {
		if ( ! function_exists( 'owc_get_chronicles' ) ) {
			return array();
		}
		return owc_get_chronicles();
	}

	// ─── Report 1 & 2: All PCs / Active PCs ──────────────────────────

	/**
	 * Get PC list with R&U counts.
	 *
	 * @param array $args {
	 *     @type string $status      Filter by status (e.g. 'active').
	 *     @type string $region      Filter by region (resolved from chronicle).
	 *     @type string $chronicle   Filter by chronicle_slug.
	 *     @type string $creature_type Filter by creature_type.
	 *     @type string $creature_genre Filter by creature_genre.
	 *     @type string $search      Search player_name or character_name.
	 *     @type string $orderby     Column to sort by.
	 *     @type string $order       ASC or DESC.
	 *     @type int    $per_page
	 *     @type int    $offset
	 *     @type array  $scope       Role-based scope filter.
	 * }
	 * @return array
	 */
	public static function get_pcs( $args = array() ) {
		global $wpdb;
		$ct = self::chars_table();
		$et = self::entries_table();

		$where  = self::pc_npc_where( $args, 'c' );
		$where  = array_merge( $where, self::build_scope_where( $args['scope'] ?? null, 'c' ) );
		$values = array();

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'c.status = %s';
			$values[] = $args['status'];
		}

		if ( ! empty( $args['chronicle'] ) ) {
			$where[]  = 'c.chronicle_slug = %s';
			$values[] = $args['chronicle'];
		}

		if ( ! empty( $args['creature_type'] ) ) {
			$where[]  = 'c.creature_type = %s';
			$values[] = $args['creature_type'];
		}

		if ( ! empty( $args['creature_sub_type'] ) ) {
			$where[]  = 'c.creature_sub_type = %s';
			$values[] = $args['creature_sub_type'];
		}

		if ( ! empty( $args['creature_genre'] ) ) {
			$where[]  = 'c.creature_genre = %s';
			$values[] = $args['creature_genre'];
		}

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(c.player_name LIKE %s OR c.character_name LIKE %s OR c.player_email LIKE %s OR c.chronicle_slug LIKE %s OR c.creature_genre LIKE %s OR c.creature_type LIKE %s OR c.creature_sub_type LIKE %s OR c.creature_variant LIKE %s OR c.status LIKE %s)';
			for ( $i = 0; $i < 9; $i++ ) {
				$values[] = $like;
			}
		}

		// Region filter: resolve to chronicle slugs.
		if ( ! empty( $args['region'] ) ) {
			$region_map = self::get_chronicle_regions();
			$slugs      = array();
			foreach ( $region_map as $slug => $region ) {
				if ( $region === $args['region'] ) {
					$slugs[] = $slug;
				}
			}
			if ( $slugs ) {
				$placeholders = implode( ', ', array_fill( 0, count( $slugs ), '%s' ) );
				$where[]      = "c.chronicle_slug IN ({$placeholders})";
				$values       = array_merge( $values, $slugs );
			} else {
				$where[] = '1 = 0'; // No chronicles in this region.
			}
		}

		// Classification filter: join entry_meta to filter by item_description.
		$classification_join = '';
		if ( ! empty( $args['classification'] ) ) {
			$mt = $wpdb->prefix . 'oat_entry_meta';
			$classification_join = " JOIN {$et} ce ON ce.character_id = c.id AND ce.domain = 'character_lifecycle' AND ce.status = 'approved'"
			                     . " JOIN {$mt} cm ON ce.id = cm.entry_id AND cm.meta_key = 'item_description' AND cm.meta_value = %s";
			$values[] = $args['classification'];
		}

		$where_sql = $where ? implode( ' AND ', $where ) : '1=1';

		// Sorting whitelist.
		$allowed_orderby = array(
			'player_name', 'player_email', 'character_name', 'chronicle_slug',
			'creature_genre', 'creature_type', 'creature_sub_type', 'creature_variant', 'status', 'ru_count',
		);
		$orderby = isset( $args['orderby'] ) && in_array( $args['orderby'], $allowed_orderby, true )
			? $args['orderby']
			: 'ru_count';
		$order = isset( $args['order'] ) && 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		$per_page = isset( $args['per_page'] ) ? absint( $args['per_page'] ) : 50;
		$offset   = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;

		$sql = "SELECT c.id, c.player_name, c.player_email, c.character_name,
		               c.chronicle_slug, c.creature_genre, c.creature_type,
		               c.creature_sub_type, c.creature_variant,
		               c.status, COUNT(e.id) AS ru_count
		        FROM {$ct} c
		        LEFT JOIN {$et} e ON e.character_id = c.id AND e.form_slug = 'cl_ru_request'
		        {$classification_join}
		        WHERE {$where_sql}
		        GROUP BY c.id
		        ORDER BY {$orderby} {$order}
		        LIMIT {$per_page} OFFSET {$offset}";

		if ( $values ) {
			return $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
		}
		return $wpdb->get_results( $sql );
	}

	/**
	 * Count PCs matching filters (for pagination).
	 */
	public static function count_pcs( $args = array() ) {
		global $wpdb;
		$ct = self::chars_table();

		$where  = self::pc_npc_where( $args );
		$where  = array_merge( $where, self::build_scope_where( $args['scope'] ?? null, '' ) );
		$values = array();

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}
		if ( ! empty( $args['chronicle'] ) ) {
			$where[]  = 'chronicle_slug = %s';
			$values[] = $args['chronicle'];
		}
		if ( ! empty( $args['creature_type'] ) ) {
			$where[]  = 'creature_type = %s';
			$values[] = $args['creature_type'];
		}
		if ( ! empty( $args['creature_sub_type'] ) ) {
			$where[]  = 'creature_sub_type = %s';
			$values[] = $args['creature_sub_type'];
		}
		if ( ! empty( $args['creature_genre'] ) ) {
			$where[]  = 'creature_genre = %s';
			$values[] = $args['creature_genre'];
		}
		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(player_name LIKE %s OR character_name LIKE %s OR player_email LIKE %s OR chronicle_slug LIKE %s OR creature_genre LIKE %s OR creature_type LIKE %s OR creature_sub_type LIKE %s OR creature_variant LIKE %s OR status LIKE %s)';
			for ( $i = 0; $i < 9; $i++ ) {
				$values[] = $like;
			}
		}

		if ( ! empty( $args['region'] ) ) {
			$region_map = self::get_chronicle_regions();
			$slugs      = array();
			foreach ( $region_map as $slug => $region ) {
				if ( $region === $args['region'] ) {
					$slugs[] = $slug;
				}
			}
			if ( $slugs ) {
				$placeholders = implode( ', ', array_fill( 0, count( $slugs ), '%s' ) );
				$where[]      = "chronicle_slug IN ({$placeholders})";
				$values       = array_merge( $values, $slugs );
			} else {
				$where[] = '1 = 0';
			}
		}

		// Classification filter.
		$classification_join = '';
		if ( ! empty( $args['classification'] ) ) {
			$et = self::entries_table();
			$mt = $wpdb->prefix . 'oat_entry_meta';
			$classification_join = " JOIN {$et} ce ON ce.character_id = {$ct}.id AND ce.domain = 'character_lifecycle' AND ce.status = 'approved'"
			                     . " JOIN {$mt} cm ON ce.id = cm.entry_id AND cm.meta_key = 'item_description' AND cm.meta_value = %s";
			$values[] = $args['classification'];
		}

		$where_sql = $where ? implode( ' AND ', $where ) : '1=1';
		$sql       = "SELECT COUNT(DISTINCT {$ct}.id) FROM {$ct} {$classification_join} WHERE {$where_sql}";

		if ( $values ) {
			return (int) $wpdb->get_var( $wpdb->prepare( $sql, $values ) );
		}
		return (int) $wpdb->get_var( $sql );
	}

	// ─── Report 3: Regional Breakdown ─────────────────────────────────

	/**
	 * Get R&U aggregates grouped by region.
	 *
	 * @param string|null $status Filter by character status.
	 * @param string      $pc_npc
	 * @param array|null  $scope  Role-based scope filter.
	 * @return array [ region => { ru, chars, games } ]
	 */
	public static function get_regional_breakdown( $status = null, $pc_npc = 'pc', $scope = null ) {
		global $wpdb;
		$ct = self::chars_table();
		$et = self::entries_table();

		$where  = self::pc_npc_where( array( 'pc_npc' => $pc_npc ), 'c' );
		$where  = array_merge( $where, self::build_scope_where( $scope, 'c' ) );
		$values = array();

		if ( $status ) {
			$where[]  = 'c.status = %s';
			$values[] = $status;
		}

		$where_sql = $where ? implode( ' AND ', $where ) : '1=1';

		$sql = "SELECT c.chronicle_slug, COUNT(DISTINCT c.id) AS chars, COUNT(e.id) AS ru
		        FROM {$ct} c
		        LEFT JOIN {$et} e ON e.character_id = c.id AND e.form_slug = 'cl_ru_request'
		        WHERE {$where_sql}
		        GROUP BY c.chronicle_slug";

		if ( $values ) {
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
		} else {
			$rows = $wpdb->get_results( $sql );
		}

		// Map chronicle → region.
		$regions_map = self::get_chronicle_regions();
		$by_region   = array();

		foreach ( $rows as $row ) {
			$region = $regions_map[ $row->chronicle_slug ] ?? 'Unknown';
			if ( ! $region ) {
				$region = 'Unknown';
			}

			if ( ! isset( $by_region[ $region ] ) ) {
				$by_region[ $region ] = (object) array(
					'region' => $region,
					'ru'     => 0,
					'chars'  => 0,
					'games'  => 0,
					'slugs'  => array(),
				);
			}

			$by_region[ $region ]->ru    += (int) $row->ru;
			$by_region[ $region ]->chars += (int) $row->chars;
			if ( ! in_array( $row->chronicle_slug, $by_region[ $region ]->slugs, true ) ) {
				$by_region[ $region ]->slugs[] = $row->chronicle_slug;
				$by_region[ $region ]->games++;
			}
		}

		// Sort by region name.
		ksort( $by_region );

		return array_values( $by_region );
	}

	// ─── Report 4: By Chronicle ───────────────────────────────────────

	/**
	 * Get R&U aggregates grouped by chronicle.
	 *
	 * @param string|null $status Filter by character status.
	 * @param string      $pc_npc
	 * @param array|null  $scope  Role-based scope filter.
	 * @return array
	 */
	public static function get_by_chronicle( $status = null, $pc_npc = 'pc', $scope = null ) {
		global $wpdb;
		$ct = self::chars_table();
		$et = self::entries_table();

		$where  = self::pc_npc_where( array( 'pc_npc' => $pc_npc ), 'c' );
		$where  = array_merge( $where, self::build_scope_where( $scope, 'c' ) );
		$values = array();

		if ( $status ) {
			$where[]  = 'c.status = %s';
			$values[] = $status;
		}

		$where_sql = $where ? implode( ' AND ', $where ) : '1=1';

		$sql = "SELECT c.chronicle_slug, COUNT(DISTINCT c.id) AS chars, COUNT(e.id) AS ru
		        FROM {$ct} c
		        LEFT JOIN {$et} e ON e.character_id = c.id AND e.form_slug = 'cl_ru_request'
		        WHERE {$where_sql}
		        GROUP BY c.chronicle_slug
		        ORDER BY ru DESC";

		if ( $values ) {
			return $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
		}
		return $wpdb->get_results( $sql );
	}

	// ─── Report 5: By Player ──────────────────────────────────────────

	/**
	 * Get R&U aggregates grouped by player.
	 *
	 * Groups by player_email (primary) with player_name fallback.
	 *
	 * @param array $args {
	 *     @type string $status   Filter by character status.
	 *     @type string $search   Search player name/email.
	 *     @type string $orderby
	 *     @type string $order
	 *     @type int    $per_page
	 *     @type int    $offset
	 *     @type array  $scope    Role-based scope filter.
	 * }
	 * @return array
	 */
	public static function get_by_player( $args = array() ) {
		global $wpdb;
		$ct = self::chars_table();
		$et = self::entries_table();

		$where  = self::pc_npc_where( $args, 'c' );
		$where  = array_merge( $where, self::build_scope_where( $args['scope'] ?? null, 'c' ) );
		$values = array();

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'c.status = %s';
			$values[] = $args['status'];
		}

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(c.player_name LIKE %s OR c.player_email LIKE %s)';
			$values[] = $like;
			$values[] = $like;
		}

		$where_sql = $where ? implode( ' AND ', $where ) : '1=1';

		// Group by email (primary key), fall back to name for empties.
		$group_key = "IF(c.player_email != '', c.player_email, c.player_name)";

		$allowed_orderby = array( 'ru', 'chars', 'player_name', 'player_email' );
		$orderby = isset( $args['orderby'] ) && in_array( $args['orderby'], $allowed_orderby, true )
			? $args['orderby']
			: 'ru';
		$order = isset( $args['order'] ) && 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		$per_page = isset( $args['per_page'] ) ? absint( $args['per_page'] ) : 50;
		$offset   = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;

		$sql = "SELECT {$group_key} AS player_key,
		               MAX(c.player_name) AS player_name,
		               MAX(c.player_email) AS player_email,
		               COUNT(DISTINCT c.id) AS chars,
		               COUNT(e.id) AS ru
		        FROM {$ct} c
		        LEFT JOIN {$et} e ON e.character_id = c.id AND e.form_slug = 'cl_ru_request'
		        WHERE {$where_sql}
		        GROUP BY player_key
		        ORDER BY {$orderby} {$order}
		        LIMIT {$per_page} OFFSET {$offset}";

		if ( $values ) {
			return $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
		}
		return $wpdb->get_results( $sql );
	}

	/**
	 * Count unique players matching filters.
	 */
	public static function count_players( $args = array() ) {
		global $wpdb;
		$ct = self::chars_table();

		$where  = self::pc_npc_where( $args );
		$where  = array_merge( $where, self::build_scope_where( $args['scope'] ?? null, '' ) );
		$values = array();

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}
		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(player_name LIKE %s OR player_email LIKE %s)';
			$values[] = $like;
			$values[] = $like;
		}

		$where_sql = $where ? implode( ' AND ', $where ) : '1=1';
		$group_key = "IF(player_email != '', player_email, player_name)";

		$sql = "SELECT COUNT(DISTINCT {$group_key}) FROM {$ct} WHERE {$where_sql}";

		if ( $values ) {
			return (int) $wpdb->get_var( $wpdb->prepare( $sql, $values ) );
		}
		return (int) $wpdb->get_var( $sql );
	}

	// ─── Report 6: By Creature Type ───────────────────────────────────

	/**
	 * Get R&U aggregates grouped by creature type.
	 *
	 * @param string|null $status Filter by character status.
	 * @param string      $pc_npc
	 * @param array|null  $scope  Role-based scope filter.
	 * @return array
	 */
	public static function get_by_creature_type( $status = null, $pc_npc = 'pc', $scope = null ) {
		global $wpdb;
		$ct = self::chars_table();
		$et = self::entries_table();

		$where  = self::pc_npc_where( array( 'pc_npc' => $pc_npc ), 'c' );
		$where  = array_merge( $where, self::build_scope_where( $scope, 'c' ) );
		$values = array();

		if ( $status ) {
			$where[]  = 'c.status = %s';
			$values[] = $status;
		}

		$where_sql = $where ? implode( ' AND ', $where ) : '1=1';

		$sql = "SELECT c.creature_type, COUNT(DISTINCT c.id) AS chars, COUNT(e.id) AS ru
		        FROM {$ct} c
		        LEFT JOIN {$et} e ON e.character_id = c.id AND e.form_slug = 'cl_ru_request'
		        WHERE {$where_sql}
		        GROUP BY c.creature_type
		        ORDER BY ru DESC";

		if ( $values ) {
			return $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
		}
		return $wpdb->get_results( $sql );
	}

	/**
	 * Get R&U aggregates grouped by sect (creature_sub_type).
	 *
	 * @param string|null $status Filter by character status.
	 * @param string      $pc_npc
	 * @param array|null  $scope  Role-based scope filter.
	 * @return array
	 */
	public static function get_by_sect( $status = null, $pc_npc = 'pc', $scope = null ) {
		global $wpdb;
		$ct = self::chars_table();
		$et = self::entries_table();

		$where  = self::pc_npc_where( array( 'pc_npc' => $pc_npc ), 'c' );
		$where  = array_merge( $where, self::build_scope_where( $scope, 'c' ) );
		$values = array();

		if ( $status ) {
			$where[]  = 'c.status = %s';
			$values[] = $status;
		}

		$where_sql = $where ? implode( ' AND ', $where ) : '1=1';

		$sql = "SELECT COALESCE(NULLIF(c.creature_sub_type, ''), '(none)') AS sect,
		               COUNT(DISTINCT c.id) AS chars, COUNT(e.id) AS ru
		        FROM {$ct} c
		        LEFT JOIN {$et} e ON e.character_id = c.id AND e.form_slug = 'cl_ru_request'
		        WHERE {$where_sql}
		        GROUP BY sect
		        ORDER BY ru DESC";

		if ( $values ) {
			return $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
		}
		return $wpdb->get_results( $sql );
	}

	// ─── Report: By R&U Classification ────────────────────────────────

	/**
	 * Get R&U entries grouped by classification (item_description).
	 *
	 * @param array $args {
	 *     @type string $pc_npc
	 *     @type string $status    Character status filter.
	 *     @type string $search    Search classification text.
	 *     @type string $orderby
	 *     @type string $order
	 *     @type int    $per_page
	 *     @type int    $offset
	 *     @type array  $scope     Role-based scope filter.
	 * }
	 * @return array
	 */
	public static function get_by_classification( $args = array() ) {
		global $wpdb;
		$ct = self::chars_table();
		$et = self::entries_table();
		$mt = $wpdb->prefix . 'oat_entry_meta';

		$where  = self::pc_npc_where( $args, 'c' );
		$where  = array_merge( $where, self::build_scope_where( $args['scope'] ?? null, 'c' ) );
		$values = array();

		$where[] = "e.domain = 'character_lifecycle'";
		$where[] = "e.status = 'approved'";
		$where[] = "m.meta_key = 'item_description'";
		$where[] = "m.meta_value != ''";

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'c.status = %s';
			$values[] = $args['status'];
		}

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = 'm.meta_value LIKE %s';
			$values[] = $like;
		}

		// Limit to a single controlling coordinator (genre).
		if ( ! empty( $args['coordinator'] ) ) {
			$where[]  = 'e.coordinator_genre = %s';
			$values[] = $args['coordinator'];
		}

		$where_sql = $where ? implode( ' AND ', $where ) : '1=1';

		$allowed_orderby = array( 'classification', 'total', 'pc_count', 'npc_count' );
		$orderby = isset( $args['orderby'] ) && in_array( $args['orderby'], $allowed_orderby, true )
			? $args['orderby']
			: 'total';
		$order = isset( $args['order'] ) && 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		$per_page = isset( $args['per_page'] ) ? absint( $args['per_page'] ) : 50;
		$offset   = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;

		$sql = "SELECT m.meta_value AS classification,
		               GROUP_CONCAT(DISTINCT NULLIF(e.coordinator_genre, '') ORDER BY e.coordinator_genre SEPARATOR ',') AS coordinators,
		               COUNT(*) AS total,
		               SUM(CASE WHEN c.pc_npc = 'pc' THEN 1 ELSE 0 END) AS pc_count,
		               SUM(CASE WHEN c.pc_npc = 'npc' THEN 1 ELSE 0 END) AS npc_count
		        FROM {$et} e
		        JOIN {$mt} m ON e.id = m.entry_id
		        LEFT JOIN {$ct} c ON e.character_id = c.id
		        WHERE {$where_sql}
		        GROUP BY m.meta_value
		        ORDER BY {$orderby} {$order}
		        LIMIT {$per_page} OFFSET {$offset}";

		if ( $values ) {
			return $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
		}
		return $wpdb->get_results( $sql );
	}

	/**
	 * Count distinct classifications matching filters.
	 */
	public static function count_classifications( $args = array() ) {
		global $wpdb;
		$ct = self::chars_table();
		$et = self::entries_table();
		$mt = $wpdb->prefix . 'oat_entry_meta';

		$where  = self::pc_npc_where( $args, 'c' );
		$where  = array_merge( $where, self::build_scope_where( $args['scope'] ?? null, 'c' ) );
		$values = array();

		$where[] = "e.domain = 'character_lifecycle'";
		$where[] = "e.status = 'approved'";
		$where[] = "m.meta_key = 'item_description'";
		$where[] = "m.meta_value != ''";

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'c.status = %s';
			$values[] = $args['status'];
		}

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = 'm.meta_value LIKE %s';
			$values[] = $like;
		}

		// Limit to a single controlling coordinator (genre).
		if ( ! empty( $args['coordinator'] ) ) {
			$where[]  = 'e.coordinator_genre = %s';
			$values[] = $args['coordinator'];
		}

		$where_sql = $where ? implode( ' AND ', $where ) : '1=1';

		$sql = "SELECT COUNT(DISTINCT m.meta_value)
		        FROM {$et} e
		        JOIN {$mt} m ON e.id = m.entry_id
		        LEFT JOIN {$ct} c ON e.character_id = c.id
		        WHERE {$where_sql}";

		if ( $values ) {
			return (int) $wpdb->get_var( $wpdb->prepare( $sql, $values ) );
		}
		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Get characters that have a specific R&U classification.
	 * Used for drill-down from classification view.
	 *
	 * @param string $classification The item_description value.
	 * @param array  $args           Standard filter args.
	 * @return array
	 */
	public static function get_pcs_by_classification( $classification, $args = array() ) {
		global $wpdb;
		$ct = self::chars_table();
		$et = self::entries_table();
		$mt = $wpdb->prefix . 'oat_entry_meta';

		$where  = self::pc_npc_where( $args, 'c' );
		$where  = array_merge( $where, self::build_scope_where( $args['scope'] ?? null, 'c' ) );
		$values = array( $classification );

		$where[] = "e.domain = 'character_lifecycle'";
		$where[] = "e.status = 'approved'";
		$where[] = "m.meta_key = 'item_description'";
		$where[] = 'm.meta_value = %s';

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'c.status = %s';
			$values[] = $args['status'];
		}

		$where_sql = $where ? implode( ' AND ', $where ) : '1=1';

		$per_page = isset( $args['per_page'] ) ? absint( $args['per_page'] ) : 50;
		$offset   = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;

		$sql = "SELECT c.id, c.player_name, c.player_email, c.character_name,
		               c.chronicle_slug, c.creature_genre, c.creature_type,
		               c.creature_sub_type, c.creature_variant,
		               c.status, c.pc_npc
		        FROM {$et} e
		        JOIN {$mt} m ON e.id = m.entry_id
		        JOIN {$ct} c ON e.character_id = c.id
		        WHERE {$where_sql}
		        ORDER BY c.character_name ASC
		        LIMIT {$per_page} OFFSET {$offset}";

		return $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
	}

	/**
	 * Count characters with a specific classification.
	 */
	public static function count_pcs_by_classification( $classification, $args = array() ) {
		global $wpdb;
		$ct = self::chars_table();
		$et = self::entries_table();
		$mt = $wpdb->prefix . 'oat_entry_meta';

		$where  = self::pc_npc_where( $args, 'c' );
		$where  = array_merge( $where, self::build_scope_where( $args['scope'] ?? null, 'c' ) );
		$values = array( $classification );

		$where[] = "e.domain = 'character_lifecycle'";
		$where[] = "e.status = 'approved'";
		$where[] = "m.meta_key = 'item_description'";
		$where[] = 'm.meta_value = %s';

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'c.status = %s';
			$values[] = $args['status'];
		}

		$where_sql = $where ? implode( ' AND ', $where ) : '1=1';

		$sql = "SELECT COUNT(DISTINCT c.id)
		        FROM {$et} e
		        JOIN {$mt} m ON e.id = m.entry_id
		        JOIN {$ct} c ON e.character_id = c.id
		        WHERE {$where_sql}";

		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $values ) );
	}

	// ─── Filter Options ───────────────────────────────────────────────

	/**
	 * Get distinct statuses for filter dropdown.
	 */
	public static function get_statuses( $pc_npc = 'pc', $scope = null ) {
		global $wpdb;
		$ct    = self::chars_table();
		$where = self::pc_npc_where( array( 'pc_npc' => $pc_npc ) );
		$where = array_merge( $where, self::build_scope_where( $scope, '' ) );
		$where[] = "status != ''";
		$where_sql = $where ? implode( ' AND ', $where ) : '1=1';
		return $wpdb->get_col( "SELECT DISTINCT status FROM {$ct} WHERE {$where_sql} ORDER BY status" );
	}

	/**
	 * Get distinct creature types for filter dropdown.
	 */
	public static function get_creature_types( $pc_npc = 'pc', $scope = null ) {
		global $wpdb;
		$ct    = self::chars_table();
		$where = self::pc_npc_where( array( 'pc_npc' => $pc_npc ) );
		$where = array_merge( $where, self::build_scope_where( $scope, '' ) );
		$where[] = "creature_type != ''";
		$where_sql = $where ? implode( ' AND ', $where ) : '1=1';
		return $wpdb->get_col( "SELECT DISTINCT creature_type FROM {$ct} WHERE {$where_sql} ORDER BY creature_type" );
	}

	/**
	 * Get distinct creature genres for filter dropdown.
	 */
	public static function get_creature_genres( $pc_npc = 'pc', $scope = null ) {
		global $wpdb;
		$ct    = self::chars_table();
		$where = self::pc_npc_where( array( 'pc_npc' => $pc_npc ) );
		$where = array_merge( $where, self::build_scope_where( $scope, '' ) );
		$where[] = "creature_genre != ''";
		$where_sql = $where ? implode( ' AND ', $where ) : '1=1';
		return $wpdb->get_col( "SELECT DISTINCT creature_genre FROM {$ct} WHERE {$where_sql} ORDER BY creature_genre" );
	}

	/**
	 * Get distinct chronicle slugs for filter dropdown.
	 */
	public static function get_chronicle_slugs( $pc_npc = 'pc', $scope = null ) {
		global $wpdb;
		$ct    = self::chars_table();
		$where = self::pc_npc_where( array( 'pc_npc' => $pc_npc ) );
		$where = array_merge( $where, self::build_scope_where( $scope, '' ) );
		$where[] = "chronicle_slug != ''";
		$where_sql = $where ? implode( ' AND ', $where ) : '1=1';
		return $wpdb->get_col( "SELECT DISTINCT chronicle_slug FROM {$ct} WHERE {$where_sql} ORDER BY chronicle_slug" );
	}

	// ─── CSV Export ───────────────────────────────────────────────────

	/**
	 * Get all PCs with R&U for CSV export (no pagination).
	 *
	 * @param string|null $status
	 * @param string      $pc_npc
	 * @param array|null  $scope  Role-based scope filter.
	 * @return array
	 */
	public static function get_pcs_for_export( $status = null, $pc_npc = 'pc', $scope = null ) {
		global $wpdb;
		$ct = self::chars_table();
		$et = self::entries_table();

		$where  = self::pc_npc_where( array( 'pc_npc' => $pc_npc ), 'c' );
		$where  = array_merge( $where, self::build_scope_where( $scope, 'c' ) );
		$values = array();

		if ( $status ) {
			$where[]  = 'c.status = %s';
			$values[] = $status;
		}

		$where_sql = $where ? implode( ' AND ', $where ) : '1=1';

		$sql = "SELECT c.player_name, c.player_email, c.character_name,
		               c.chronicle_slug, c.creature_genre, c.creature_type,
		               c.creature_sub_type, c.creature_variant,
		               c.status, COUNT(e.id) AS ru_count
		        FROM {$ct} c
		        LEFT JOIN {$et} e ON e.character_id = c.id AND e.form_slug = 'cl_ru_request'
		        WHERE {$where_sql}
		        GROUP BY c.id
		        ORDER BY c.player_name ASC, c.character_name ASC";

		if ( $values ) {
			return $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A );
		}
		return $wpdb->get_results( $sql, ARRAY_A );
	}
}
