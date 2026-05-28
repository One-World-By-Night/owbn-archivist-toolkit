<?php

defined( 'ABSPATH' ) || exit;

class OAT_Page_Reports {

	// ─── Scope Computation ───────────────────────────────────────────

	/**
	 * Compute the current user's report scope based on ASC roles.
	 *
	 * @param array $selected_roles  Optional subset of roles to narrow scope (from UI checkboxes).
	 * @return array|null  Scope array or null if no access.
	 *   {
	 *     'chronicles'    => string[],   Chronicle slugs the user can see.
	 *     'character_ids' => int[],       Character IDs from coordinator registry grants.
	 *     'is_global'     => bool,        True = see everything.
	 *     'roles'         => string[],    All qualifying ASC role paths (for checkbox UI).
	 *   }
	 */
	public static function get_user_scope( $selected_roles = array() ) {
		// WP admin = global.  Other global roles (exec/archivist) detected via ASC loop below.
		if ( current_user_can( 'manage_options' ) ) {
			return array(
				'chronicles'    => array(),
				'character_ids' => array(),
				'is_global'     => true,
				'roles'         => array(),
			);
		}

		$asc_roles   = OAT_Authorization::get_user_roles();
		$chronicles  = array();
		$coord_slugs = array();
		$is_global   = false;
		$all_roles   = array();

		foreach ( $asc_roles as $role ) {
			// Exec-level oversight = global.
			// Coordinator-tier from any exec office; staff-tier only from exec/archivist.
			if ( preg_match( '#^exec/(archivist|web|head-coordinator|ahc1|ahc2|admin)/coordinator$#i', $role )
				|| preg_match( '#^exec/archivist/staff$#i', $role ) ) {
				$is_global = true;
			}
			// Chronicle staff.
			if ( preg_match( '#^chronicle/([^/]+)/(hst|cm|staff)$#i', $role, $m ) ) {
				$all_roles[] = $role;
				if ( empty( $selected_roles ) || in_array( $role, $selected_roles, true ) ) {
					$chronicles[] = $m[1];
				}
			}
			// Coordinator / sub-coordinator.
			if ( preg_match( '#^coordinator/([^/]+)/(coordinator|sub-coordinator)$#i', $role, $m ) ) {
				$all_roles[] = $role;
				if ( empty( $selected_roles ) || in_array( $role, $selected_roles, true ) ) {
					$coord_slugs[] = $m[1];
				}
			}
		}

		if ( $is_global ) {
			return array(
				'chronicles'    => array(),
				'character_ids' => array(),
				'is_global'     => true,
				'roles'         => $all_roles,
			);
		}

		// No qualifying roles at all = no access.
		if ( empty( $all_roles ) ) {
			return null;
		}

		// Resolve coordinator slugs → character IDs via registry grants.
		$character_ids = array();
		if ( ! empty( $coord_slugs ) ) {
			$character_ids = self::resolve_coordinator_character_ids( array_unique( $coord_slugs ) );
		}

		return array(
			'chronicles'    => array_unique( $chronicles ),
			'character_ids' => $character_ids,
			'is_global'     => false,
			'roles'         => $all_roles,
		);
	}

	/**
	 * Query character IDs that have active coordinator registry grants
	 * for any of the given coordinator slugs.
	 *
	 * @param array $coord_slugs  e.g. ['vampire', 'giovanni']
	 * @return array  Integer character IDs.
	 */
	public static function resolve_coordinator_character_ids( $coord_slugs ) {
		if ( empty( $coord_slugs ) ) {
			return array();
		}

		global $wpdb;
		$table  = $wpdb->prefix . 'oat_registry_access';
		$now    = time();
		$places = implode( ', ', array_fill( 0, count( $coord_slugs ), '%s' ) );

		$values   = $coord_slugs;
		$values[] = $now;
		$values[] = $now;

		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT character_id FROM {$table}
			 WHERE grant_type = 'coordinator' AND grant_value IN ({$places})
			   AND (starts_at IS NULL OR starts_at <= %d)
			   AND (expires_at IS NULL OR expires_at >= %d)",
			$values
		) ) );
	}

	/**
	 * Read selected scope_roles[] from the request.
	 *
	 * @return array  Sanitized role strings.
	 */
	private static function get_selected_roles() {
		if ( empty( $_GET['scope_roles'] ) || ! is_array( $_GET['scope_roles'] ) ) {
			return array();
		}
		return array_map( 'sanitize_text_field', $_GET['scope_roles'] );
	}

	// ─── CSV Export ──────────────────────────────────────────────────

	/**
	 * Handle CSV export before any output.
	 * Hooked via admin_init so headers can be sent.
	 */
	public static function maybe_export_csv() {
		if ( ! isset( $_GET['page'] ) || 'oat-reports' !== $_GET['page'] || empty( $_GET['export_csv'] ) ) {
			return;
		}

		$scope = self::get_user_scope( self::get_selected_roles() );
		if ( ! $scope ) {
			return;
		}

		$tab    = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'all-pcs';
		$status = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
		$pc_npc = isset( $_GET['pc_npc'] ) ? sanitize_text_field( $_GET['pc_npc'] ) : 'pc';
		if ( ! in_array( $pc_npc, array( 'pc', 'npc', 'all' ), true ) ) {
			$pc_npc = 'pc';
		}

		switch ( $tab ) {
			case 'all-pcs':
			case 'active':
				$filter_status = 'active' === $tab ? 'active' : ( $status ?: null );
				$rows          = OAT_Report_Query::get_pcs_for_export( $filter_status, $pc_npc, $scope );
				$filename      = 'oat-report-' . $tab . '-' . gmdate( 'Y-m-d' ) . '.csv';
				$headers       = array( 'Player', 'Email', 'Character', 'Chronicle', 'Genre', 'Creature Type', 'Faction', 'Variant', 'Status', 'R&U' );

				self::send_csv( $filename, $headers, $rows, array(
					'player_name', 'player_email', 'character_name', 'chronicle_slug',
					'creature_genre', 'creature_type', 'creature_sub_type', 'creature_variant',
					'status', 'ru_count',
				) );
				break;

			case 'by-region':
				$filter_status = $status ?: null;
				$rows          = OAT_Report_Query::get_regional_breakdown( $filter_status, $pc_npc, $scope );
				$filename      = 'oat-report-regional-' . gmdate( 'Y-m-d' ) . '.csv';
				$csv_headers   = array( 'Region', 'Total R&Us', 'Games', 'R&Us per Game', '# Characters', 'Avg R&U per Char' );

				header( 'Content-Type: text/csv; charset=utf-8' );
				header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
				header( 'Pragma: no-cache' );
				header( 'Expires: 0' );

				$out = fopen( 'php://output', 'w' );
				fputcsv( $out, $csv_headers );
				foreach ( $rows as $row ) {
					$rpg = $row->games > 0 ? round( $row->ru / $row->games, 2 ) : 0;
					$avg = $row->chars > 0 ? round( $row->ru / $row->chars, 2 ) : 0;
					fputcsv( $out, array( $row->region, $row->ru, $row->games, $rpg, $row->chars, $avg ) );
				}
				fclose( $out );
				exit;

			case 'by-chronicle':
				$filter_status = $status ?: null;
				$rows          = OAT_Report_Query::get_by_chronicle( $filter_status, $pc_npc, $scope );
				$filename      = 'oat-report-by-chronicle-' . gmdate( 'Y-m-d' ) . '.csv';

				header( 'Content-Type: text/csv; charset=utf-8' );
				header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
				header( 'Pragma: no-cache' );
				header( 'Expires: 0' );

				$out = fopen( 'php://output', 'w' );
				fputcsv( $out, array( 'Chronicle', 'R&Us', 'Characters', 'Avg R&U per Char' ) );
				foreach ( $rows as $row ) {
					$avg = $row->chars > 0 ? round( $row->ru / $row->chars, 2 ) : 0;
					fputcsv( $out, array( $row->chronicle_slug, $row->ru, $row->chars, $avg ) );
				}
				fclose( $out );
				exit;

			case 'by-player':
				$filter_status = $status ?: null;
				$rows          = OAT_Report_Query::get_by_player( array(
					'status'   => $filter_status,
					'pc_npc'   => $pc_npc,
					'scope'    => $scope,
					'per_page' => 999999,
					'offset'   => 0,
				) );
				$filename      = 'oat-report-by-player-' . gmdate( 'Y-m-d' ) . '.csv';

				header( 'Content-Type: text/csv; charset=utf-8' );
				header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
				header( 'Pragma: no-cache' );
				header( 'Expires: 0' );

				$out = fopen( 'php://output', 'w' );
				fputcsv( $out, array( 'Player', 'Email', 'R&Us', 'Characters' ) );
				foreach ( $rows as $row ) {
					fputcsv( $out, array( $row->player_name, $row->player_email, $row->ru, $row->chars ) );
				}
				fclose( $out );
				exit;

			case 'by-classification':
				$filter_status = $status ?: null;
				$coordinator   = isset( $_GET['coordinator'] ) ? sanitize_text_field( wp_unslash( $_GET['coordinator'] ) ) : '';
				$rows          = OAT_Report_Query::get_by_classification( array(
					'status'      => $filter_status,
					'pc_npc'      => $pc_npc,
					'scope'       => $scope,
					'coordinator' => $coordinator,
					'per_page'    => 999999,
					'offset'      => 0,
				) );
				$filename      = 'oat-report-by-classification-' . gmdate( 'Y-m-d' ) . '.csv';

				header( 'Content-Type: text/csv; charset=utf-8' );
				header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
				header( 'Pragma: no-cache' );
				header( 'Expires: 0' );

				$out = fopen( 'php://output', 'w' );
				fputcsv( $out, array( 'R&U Classification', 'Coordinator', 'Total', 'PCs', 'NPCs' ) );
				foreach ( $rows as $row ) {
					$coord_label = '';
					if ( ! empty( $row->coordinators ) ) {
						$titles = array();
						foreach ( array_filter( array_map( 'trim', explode( ',', $row->coordinators ) ) ) as $slug ) {
							$titles[] = function_exists( 'owc_entity_get_title' )
								? ( owc_entity_get_title( 'coordinator', $slug ) ?: ucfirst( $slug ) )
								: ucfirst( $slug );
						}
						$coord_label = implode( '; ', $titles );
					}
					fputcsv( $out, array( $row->classification, $coord_label, $row->total, $row->pc_count, $row->npc_count ) );
				}
				fclose( $out );
				exit;

			case 'by-type':
				$filter_status = $status ?: null;
				$types         = OAT_Report_Query::get_by_creature_type( $filter_status, $pc_npc, $scope );
				$sects         = OAT_Report_Query::get_by_sect( $filter_status, $pc_npc, $scope );
				$filename      = 'oat-report-by-type-' . gmdate( 'Y-m-d' ) . '.csv';

				header( 'Content-Type: text/csv; charset=utf-8' );
				header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
				header( 'Pragma: no-cache' );
				header( 'Expires: 0' );

				$out = fopen( 'php://output', 'w' );
				fputcsv( $out, array( 'Creature Type', 'R&Us', 'Characters', 'Avg R&U per Char' ) );
				foreach ( $types as $row ) {
					$avg = $row->chars > 0 ? round( $row->ru / $row->chars, 2 ) : 0;
					fputcsv( $out, array( $row->creature_type, $row->ru, $row->chars, $avg ) );
				}
				fputcsv( $out, array() );
				fputcsv( $out, array( 'Faction', 'R&Us', 'Characters', 'Avg R&U per Char' ) );
				foreach ( $sects as $row ) {
					$avg = $row->chars > 0 ? round( $row->ru / $row->chars, 2 ) : 0;
					fputcsv( $out, array( $row->sect, $row->ru, $row->chars, $avg ) );
				}
				fclose( $out );
				exit;
		}
	}

	// ─── Render ──────────────────────────────────────────────────────

	/**
	 * Render the reports page.
	 */
	public static function render() {
		$selected_roles = self::get_selected_roles();
		$scope          = self::get_user_scope( $selected_roles );

		if ( ! $scope ) {
			wp_die( 'You do not have permission to view reports.' );
		}

		$tab     = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'all-pcs';
		$status  = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
		$pc_npc  = isset( $_GET['pc_npc'] ) ? sanitize_text_field( $_GET['pc_npc'] ) : 'pc';
		if ( ! in_array( $pc_npc, array( 'pc', 'npc', 'all' ), true ) ) {
			$pc_npc = 'pc';
		}

		$tabs = array(
			'all-pcs'           => 'All Characters',
			'active'            => 'Active Only',
			'by-region'         => 'By Region',
			'by-chronicle'      => 'By Chronicle',
			'by-player'         => 'By Player',
			'by-type'           => 'By Creature Type',
			'by-classification' => 'By R&U Classification',
		);

		// Build the appropriate list table.
		$list_table = null;
		$table_args = array( 'status_filter' => $status, 'pc_npc' => $pc_npc, 'scope' => $scope );

		switch ( $tab ) {
			case 'all-pcs':
				$list_table = new OAT_Report_PCs_List_Table( $table_args );
				break;
			case 'active':
				$table_args['status_filter'] = 'active';
				$list_table = new OAT_Report_PCs_List_Table( $table_args );
				break;
			case 'by-region':
				$list_table = new OAT_Report_Region_List_Table( $table_args );
				break;
			case 'by-chronicle':
				$list_table = new OAT_Report_Chronicle_List_Table( $table_args );
				break;
			case 'by-player':
				$list_table = new OAT_Report_Player_List_Table( $table_args );
				break;
			case 'by-type':
				$list_table = new OAT_Report_Type_List_Table( $table_args );
				break;
			case 'by-classification':
				$list_table = new OAT_Report_Classification_List_Table( $table_args );
				break;
		}

		if ( $list_table ) {
			$list_table->prepare_items();
		}

		include OAT_PLUGIN_DIR . 'templates/admin/reports.php';
	}

	// ─── Helpers ─────────────────────────────────────────────────────

	/**
	 * Send CSV response for simple row-based exports.
	 */
	private static function send_csv( $filename, $headers, $rows, $fields ) {
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, $headers );
		foreach ( $rows as $row ) {
			$line = array();
			foreach ( $fields as $f ) {
				$line[] = $row[ $f ] ?? '';
			}
			fputcsv( $out, $line );
		}
		fclose( $out );
		exit;
	}

	/**
	 * Build a human-readable label for an ASC role path.
	 *
	 * @param string $role  e.g. 'chronicle/hartford/hst'
	 * @return string       e.g. 'HST: Hartford'
	 */
	public static function role_label( $role ) {
		if ( preg_match( '#^chronicle/([^/]+)/(hst|cm|staff)$#i', $role, $m ) ) {
			$slug  = $m[1];
			$type  = strtoupper( $m[2] );
			$title = $slug;
			if ( function_exists( 'owc_entity_get_title' ) ) {
				$resolved = owc_entity_get_title( 'chronicle', $slug );
				if ( $resolved ) {
					$title = $resolved;
				}
			}
			return "{$type}: {$title}";
		}
		if ( preg_match( '#^coordinator/([^/]+)/(coordinator|sub-coordinator)$#i', $role, $m ) ) {
			$slug = $m[1];
			$type = ( 'sub-coordinator' === strtolower( $m[2] ) ) ? 'Sub-Coord' : 'Coordinator';
			$title = ucfirst( $slug );
			if ( function_exists( 'owc_entity_get_title' ) ) {
				$resolved = owc_entity_get_title( 'coordinator', $slug );
				if ( $resolved ) {
					$title = $resolved;
				}
			}
			return "{$type}: {$title}";
		}
		return $role;
	}
}
