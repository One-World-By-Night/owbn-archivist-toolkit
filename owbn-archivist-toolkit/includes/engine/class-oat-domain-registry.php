<?php

defined( 'ABSPATH' ) || exit;

/**
 * Domain registry — merges DB-defined domains with PHP-registered ones.
 *
 * DB rows (oat_domains table) take precedence over PHP domain classes.
 * PHP domain classes remain as seeders and optional hook providers.
 *
 * Returns domain info arrays (not OAT_Domain_Interface instances) so that
 * DB-only domains (no PHP class) work identically to seeded ones.
 */
class OAT_Domain_Registry {

	/** @var array|null Cached slug => domain info array. */
	private static $domains = null;

	/** @var array|null Cached slug => OAT_Domain_Interface (PHP classes only). */
	private static $php_domains = null;

	/**
	 * Get all active domains as slug => info arrays.
	 *
	 * Info array keys: slug, label, archivist_mode, active, source ('db'|'php').
	 *
	 * @return array Associative array of slug => domain info.
	 */
	public static function get_all() {
		if ( self::$domains === null ) {
			self::$domains = array();

			// 1. Load PHP-registered domains.
			self::load_php_domains();
			foreach ( self::$php_domains as $slug => $domain ) {
				self::$domains[ $slug ] = array(
					'slug'           => $slug,
					'label'          => $domain->get_label(),
					'archivist_mode' => $domain->get_archivist_mode(),
					'active'         => true,
					'source'         => 'php',
				);
			}

			// 2. Overlay DB-defined domains (take precedence).
			if ( class_exists( 'OAT_Domain' ) ) {
				$db_domains = OAT_Domain::get_all( false );
				if ( is_array( $db_domains ) ) {
					foreach ( $db_domains as $row ) {
						self::$domains[ $row->slug ] = array(
							'slug'           => $row->slug,
							'label'          => $row->label,
							'archivist_mode' => $row->archivist_mode,
							'active'         => (bool) $row->active,
							'source'         => 'db',
						);
					}
				}
			}
		}
		return self::$domains;
	}

	/**
	 * Get domain info by slug.
	 *
	 * @param string $slug Domain slug.
	 * @return array|null Domain info array or null.
	 */
	public static function get( $slug ) {
		$all = self::get_all();
		return isset( $all[ $slug ] ) ? $all[ $slug ] : null;
	}

	/**
	 * Get the PHP domain class instance if one exists.
	 *
	 * Used by the engine to call domain-specific hooks (validate, etc.)
	 * when a PHP class is available.
	 *
	 * @param string $slug Domain slug.
	 * @return OAT_Domain_Interface|null
	 */
	public static function get_php_domain( $slug ) {
		self::load_php_domains();
		return isset( self::$php_domains[ $slug ] ) ? self::$php_domains[ $slug ] : null;
	}

	/**
	 * Get flat array of registered domain slugs.
	 *
	 * @return array
	 */
	public static function get_slugs() {
		return array_keys( self::get_all() );
	}

	/**
	 * Get domain label by slug.
	 *
	 * @param string $slug Domain slug.
	 * @return string Label or empty string.
	 */
	public static function get_label( $slug ) {
		$domain = self::get( $slug );
		return $domain ? $domain['label'] : '';
	}

	/**
	 * Get archivist mode for a domain.
	 *
	 * @param string $slug Domain slug.
	 * @return string 'auto' or 'manual'.
	 */
	public static function get_archivist_mode( $slug ) {
		$domain = self::get( $slug );
		return $domain ? $domain['archivist_mode'] : 'manual';
	}

	/**
	 * Reset cached data (for testing).
	 */
	public static function reset() {
		self::$domains     = null;
		self::$php_domains = null;
	}

	/**
	 * Load PHP-registered domain instances.
	 */
	private static function load_php_domains() {
		if ( self::$php_domains === null ) {
			self::$php_domains = array();
			$registered = apply_filters( 'oat_register_domains', array() );
			foreach ( $registered as $domain ) {
				if ( $domain instanceof OAT_Domain_Interface ) {
					self::$php_domains[ $domain->get_slug() ] = $domain;
				}
			}
		}
	}
}
