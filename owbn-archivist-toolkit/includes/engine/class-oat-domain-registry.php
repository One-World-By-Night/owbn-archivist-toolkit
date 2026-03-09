<?php

defined( 'ABSPATH' ) || exit;

// DB rows take precedence over PHP domain classes; PHP classes are seeders + hook providers.
class OAT_Domain_Registry {

	private static $domains = null;
	private static $php_domains = null;

	public static function get_all() {
		if ( self::$domains === null ) {
			self::$domains = array();
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
			if ( class_exists( 'OAT_Domain' ) ) {
				$db_domains = OAT_Domain::get_all( false );
				if ( is_array( $db_domains ) ) {
					foreach ( $db_domains as $row ) {
						self::$domains[ $row->slug ] = array(
							'slug'           => $row->slug,
							'label'          => $row->label,
							'archivist_mode' => $row->archivist_mode,
							'active'         => (bool) $row->active,
							'sort_order'     => isset( $row->sort_order ) ? (int) $row->sort_order : 0,
							'source'         => 'db',
						);
					}
				}
			}
		}
		return self::$domains;
	}

	public static function get( $slug ) {
		$all = self::get_all();
		return isset( $all[ $slug ] ) ? $all[ $slug ] : null;
	}

	public static function get_php_domain( $slug ) {
		self::load_php_domains();
		return isset( self::$php_domains[ $slug ] ) ? self::$php_domains[ $slug ] : null;
	}

	public static function get_slugs() {
		return array_keys( self::get_all() );
	}

	public static function get_label( $slug ) {
		$domain = self::get( $slug );
		return $domain ? $domain['label'] : '';
	}

	public static function get_archivist_mode( $slug ) {
		$domain = self::get( $slug );
		return $domain ? $domain['archivist_mode'] : 'manual';
	}

	public static function get_forms( $domain_slug ) {
		if ( ! class_exists( 'OAT_Domain_Form' ) ) {
			return array();
		}
		return OAT_Domain_Form::get_forms_for_domain_slug( $domain_slug );
	}

	// Auto-select when only one form exists for a domain.
	public static function get_single_form( $domain_slug ) {
		$forms = self::get_forms( $domain_slug );
		if ( count( $forms ) === 1 ) {
			return $forms[0];
		}
		return null;
	}

	public static function reset() {
		self::$domains     = null;
		self::$php_domains = null;
	}

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
