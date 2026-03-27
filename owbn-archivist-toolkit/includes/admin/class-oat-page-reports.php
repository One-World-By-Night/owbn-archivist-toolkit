<?php

defined( 'ABSPATH' ) || exit;

class OAT_Page_Reports {

	/**
	 * Handle CSV export before any output.
	 * Hooked via admin_init so headers can be sent.
	 */
	public static function maybe_export_csv() {
		if ( ! isset( $_GET['page'] ) || 'oat-reports' !== $_GET['page'] || empty( $_GET['export_csv'] ) ) {
			return;
		}
		if ( ! OAT_Authorization::check( OAT_Constants::CAP_ARCHIVIST ) ) {
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
				$rows          = OAT_Report_Query::get_pcs_for_export( $filter_status, $pc_npc );
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
				$rows          = OAT_Report_Query::get_regional_breakdown( $filter_status, $pc_npc );
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
				$rows          = OAT_Report_Query::get_by_chronicle( $filter_status, $pc_npc );
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
				$rows          = OAT_Report_Query::get_by_classification( array(
					'status'   => $filter_status,
					'pc_npc'   => $pc_npc,
					'per_page' => 999999,
					'offset'   => 0,
				) );
				$filename      = 'oat-report-by-classification-' . gmdate( 'Y-m-d' ) . '.csv';

				header( 'Content-Type: text/csv; charset=utf-8' );
				header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
				header( 'Pragma: no-cache' );
				header( 'Expires: 0' );

				$out = fopen( 'php://output', 'w' );
				fputcsv( $out, array( 'R&U Classification', 'Total', 'PCs', 'NPCs' ) );
				foreach ( $rows as $row ) {
					fputcsv( $out, array( $row->classification, $row->total, $row->pc_count, $row->npc_count ) );
				}
				fclose( $out );
				exit;

			case 'by-type':
				$filter_status = $status ?: null;
				$types         = OAT_Report_Query::get_by_creature_type( $filter_status, $pc_npc );
				$sects         = OAT_Report_Query::get_by_sect( $filter_status, $pc_npc );
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

	/**
	 * Render the reports page.
	 */
	public static function render() {
		if ( ! OAT_Authorization::check( OAT_Constants::CAP_ARCHIVIST ) ) {
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
		$table_args = array( 'status_filter' => $status, 'pc_npc' => $pc_npc );

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
}
