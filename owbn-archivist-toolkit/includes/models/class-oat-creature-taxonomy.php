<?php

defined( 'ABSPATH' ) || exit;

/**
 * Creature Type Taxonomy — stored as a single JSON blob in wp_options.
 *
 * Structure: { genres: { GenreName: { factions: { FactionName: { types: { TypeName: { variants: [] } } } } } } }
 *
 * The picker reads this directly. The editor writes to it via AJAX.
 * Reports use creature_genre/creature_sub_type/creature_type/creature_variant columns (populated by migration).
 */
class OAT_Creature_Taxonomy {

	const OPTION_KEY = 'oat_creature_taxonomy';

	/**
	 * Get the full taxonomy tree.
	 *
	 * @return array
	 */
	public static function get() {
		$data = get_option( self::OPTION_KEY, '' );
		if ( is_string( $data ) && $data !== '' ) {
			$data = json_decode( $data, true );
		}
		if ( ! is_array( $data ) || empty( $data['genres'] ) ) {
			return array( 'genres' => array() );
		}
		return $data;
	}

	/**
	 * Save the full taxonomy tree.
	 *
	 * @param array $data
	 * @return bool
	 */
	public static function set( $data ) {
		if ( ! isset( $data['genres'] ) ) {
			$data = array( 'genres' => $data );
		}
		return update_option( self::OPTION_KEY, wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ), false );
	}

	/**
	 * Get genre names.
	 *
	 * @return array
	 */
	public static function get_genres() {
		$data = self::get();
		return array_keys( $data['genres'] );
	}

	/**
	 * Get factions for a genre.
	 *
	 * @param string $genre
	 * @return array
	 */
	public static function get_factions( $genre ) {
		$data = self::get();
		if ( ! isset( $data['genres'][ $genre ]['factions'] ) ) {
			return array();
		}
		return array_keys( $data['genres'][ $genre ]['factions'] );
	}

	/**
	 * Get types for a genre + faction.
	 *
	 * @param string $genre
	 * @param string $faction
	 * @return array
	 */
	public static function get_types( $genre, $faction ) {
		$data = self::get();
		if ( ! isset( $data['genres'][ $genre ]['factions'][ $faction ]['types'] ) ) {
			return array();
		}
		return array_keys( $data['genres'][ $genre ]['factions'][ $faction ]['types'] );
	}

	/**
	 * Get variants for a genre + faction + type.
	 *
	 * @param string $genre
	 * @param string $faction
	 * @param string $type
	 * @return array
	 */
	public static function get_variants( $genre, $faction, $type ) {
		$data = self::get();
		if ( ! isset( $data['genres'][ $genre ]['factions'][ $faction ]['types'][ $type ]['variants'] ) ) {
			return array();
		}
		return $data['genres'][ $genre ]['factions'][ $faction ]['types'][ $type ]['variants'];
	}

	/**
	 * Get the full cascading data for the picker (one AJAX call).
	 * Returns a flat structure optimized for JS consumption.
	 *
	 * @return array { genres: [...], factions: { genre: [...] }, types: { genre|faction: [...] }, variants: { genre|faction|type: [...] } }
	 */
	public static function get_picker_data() {
		$data    = self::get();
		$genres  = array();
		$factions = array();
		$types   = array();
		$variants = array();

		foreach ( $data['genres'] as $genre_name => $genre_data ) {
			$genres[] = $genre_name;
			$genre_factions = array();

			foreach ( ( $genre_data['factions'] ?? array() ) as $faction_name => $faction_data ) {
				$genre_factions[] = $faction_name;
				$faction_types = array();

				foreach ( ( $faction_data['types'] ?? array() ) as $type_name => $type_data ) {
					$faction_types[] = $type_name;
					$type_variants = $type_data['variants'] ?? array();
					if ( ! empty( $type_variants ) ) {
						$variants[ $genre_name . '|' . $faction_name . '|' . $type_name ] = $type_variants;
					}
				}

				sort( $faction_types );
				$types[ $genre_name . '|' . $faction_name ] = $faction_types;
			}

			sort( $genre_factions );
			$factions[ $genre_name ] = $genre_factions;
		}

		sort( $genres );

		return array(
			'genres'   => $genres,
			'factions' => $factions,
			'types'    => $types,
			'variants' => $variants,
		);
	}

	/**
	 * Seed taxonomy from the reference JSON file if option is empty.
	 */
	public static function maybe_seed() {
		$existing = get_option( self::OPTION_KEY, '' );
		if ( $existing && $existing !== '' ) {
			return;
		}

		$path = OAT_PLUGIN_DIR . 'reference/creature-type-taxonomy.json';
		if ( ! file_exists( $path ) ) {
			return;
		}

		$raw = json_decode( file_get_contents( $path ), true );
		if ( ! $raw ) {
			return;
		}

		// Convert from old taxonomy format to new picker format.
		// Old format has genre-level keys with nested sections.
		// New format is { genres: { Name: { factions: { Name: { types: { Name: { variants: [] } } } } } } }
		$taxonomy = array( 'genres' => array() );

		// Use the wod-creature-type-schema.json if available (more complete).
		$wod_path = dirname( OAT_PLUGIN_DIR ) . '/../_INPROGRESS/wod-creature-type-schema.json';
		if ( file_exists( $wod_path ) ) {
			$wod = json_decode( file_get_contents( $wod_path ), true );
			if ( $wod && isset( $wod['genres'] ) ) {
				self::set( $wod );
				return;
			}
		}

		// Fallback: use the _genre_map to build a minimal taxonomy.
		if ( isset( $raw['_genre_map']['map'] ) ) {
			$genre_map = $raw['_genre_map']['map'];
			foreach ( $genre_map as $type => $genre ) {
				if ( ! isset( $taxonomy['genres'][ $genre ] ) ) {
					$taxonomy['genres'][ $genre ] = array( 'factions' => array( '' => array( 'types' => array() ) ) );
				}
				$taxonomy['genres'][ $genre ]['factions']['']['types'][ $type ] = array( 'variants' => array() );
			}
		}

		self::set( $taxonomy );
	}

	/**
	 * Add a genre.
	 */
	public static function add_genre( $name ) {
		$data = self::get();
		if ( isset( $data['genres'][ $name ] ) ) {
			return false;
		}
		$data['genres'][ $name ] = array( 'factions' => array() );
		return self::set( $data );
	}

	/**
	 * Rename a genre.
	 */
	public static function rename_genre( $old, $new ) {
		$data = self::get();
		if ( ! isset( $data['genres'][ $old ] ) || isset( $data['genres'][ $new ] ) ) {
			return false;
		}
		$data['genres'][ $new ] = $data['genres'][ $old ];
		unset( $data['genres'][ $old ] );
		return self::set( $data );
	}

	/**
	 * Delete a genre (and all its children).
	 */
	public static function delete_genre( $name ) {
		$data = self::get();
		if ( ! isset( $data['genres'][ $name ] ) ) {
			return false;
		}
		unset( $data['genres'][ $name ] );
		return self::set( $data );
	}

	/**
	 * Add a faction to a genre.
	 */
	public static function add_faction( $genre, $name ) {
		$data = self::get();
		if ( ! isset( $data['genres'][ $genre ] ) ) {
			return false;
		}
		if ( isset( $data['genres'][ $genre ]['factions'][ $name ] ) ) {
			return false;
		}
		$data['genres'][ $genre ]['factions'][ $name ] = array( 'types' => array() );
		return self::set( $data );
	}

	/**
	 * Rename a faction.
	 */
	public static function rename_faction( $genre, $old, $new ) {
		$data = self::get();
		if ( ! isset( $data['genres'][ $genre ]['factions'][ $old ] ) ) {
			return false;
		}
		$data['genres'][ $genre ]['factions'][ $new ] = $data['genres'][ $genre ]['factions'][ $old ];
		unset( $data['genres'][ $genre ]['factions'][ $old ] );
		return self::set( $data );
	}

	/**
	 * Delete a faction.
	 */
	public static function delete_faction( $genre, $name ) {
		$data = self::get();
		if ( ! isset( $data['genres'][ $genre ]['factions'][ $name ] ) ) {
			return false;
		}
		unset( $data['genres'][ $genre ]['factions'][ $name ] );
		return self::set( $data );
	}

	/**
	 * Add a type to a genre + faction.
	 */
	public static function add_type( $genre, $faction, $name ) {
		$data = self::get();
		if ( ! isset( $data['genres'][ $genre ]['factions'][ $faction ] ) ) {
			return false;
		}
		if ( isset( $data['genres'][ $genre ]['factions'][ $faction ]['types'][ $name ] ) ) {
			return false;
		}
		$data['genres'][ $genre ]['factions'][ $faction ]['types'][ $name ] = array( 'variants' => array() );
		return self::set( $data );
	}

	/**
	 * Rename a type.
	 */
	public static function rename_type( $genre, $faction, $old, $new ) {
		$data = self::get();
		if ( ! isset( $data['genres'][ $genre ]['factions'][ $faction ]['types'][ $old ] ) ) {
			return false;
		}
		$data['genres'][ $genre ]['factions'][ $faction ]['types'][ $new ] = $data['genres'][ $genre ]['factions'][ $faction ]['types'][ $old ];
		unset( $data['genres'][ $genre ]['factions'][ $faction ]['types'][ $old ] );
		return self::set( $data );
	}

	/**
	 * Delete a type.
	 */
	public static function delete_type( $genre, $faction, $name ) {
		$data = self::get();
		if ( ! isset( $data['genres'][ $genre ]['factions'][ $faction ]['types'][ $name ] ) ) {
			return false;
		}
		unset( $data['genres'][ $genre ]['factions'][ $faction ]['types'][ $name ] );
		return self::set( $data );
	}

	/**
	 * Set variants for a type.
	 */
	public static function set_variants( $genre, $faction, $type, $variants ) {
		$data = self::get();
		if ( ! isset( $data['genres'][ $genre ]['factions'][ $faction ]['types'][ $type ] ) ) {
			return false;
		}
		$data['genres'][ $genre ]['factions'][ $faction ]['types'][ $type ]['variants'] = array_values( array_filter( $variants ) );
		return self::set( $data );
	}

	/**
	 * Import taxonomy from JSON string (full replace).
	 */
	public static function import( $json_string ) {
		$data = json_decode( $json_string, true );
		if ( ! $data || ! isset( $data['genres'] ) ) {
			return false;
		}
		return self::set( $data );
	}

	/**
	 * Export taxonomy as JSON string.
	 */
	public static function export() {
		return wp_json_encode( self::get(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
	}
}
