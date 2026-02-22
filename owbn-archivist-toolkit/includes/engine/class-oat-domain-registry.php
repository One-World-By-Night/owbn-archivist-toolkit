<?php

defined( 'ABSPATH' ) || exit;

/**
 * Collects domain instances registered via the oat_register_domains filter.
 */
class OAT_Domain_Registry {

    /** @var array|null */
    private static $domains = null;

    /**
     * @return array Associative array of slug => OAT_Domain_Interface.
     */
    public static function get_all() {
        if ( self::$domains === null ) {
            self::$domains = array();
            $registered = apply_filters( 'oat_register_domains', array() );
            foreach ( $registered as $domain ) {
                if ( $domain instanceof OAT_Domain_Interface ) {
                    self::$domains[ $domain->get_slug() ] = $domain;
                }
            }
        }
        return self::$domains;
    }

    /**
     * @param string $slug
     * @return OAT_Domain_Interface|null
     */
    public static function get( $slug ) {
        $all = self::get_all();
        return isset( $all[ $slug ] ) ? $all[ $slug ] : null;
    }

    /**
     * @return array Flat array of registered domain slugs.
     */
    public static function get_slugs() {
        return array_keys( self::get_all() );
    }

    /**
     * Reset cached domains (for testing).
     */
    public static function reset() {
        self::$domains = null;
    }
}
