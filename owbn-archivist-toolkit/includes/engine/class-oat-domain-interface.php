<?php

defined( 'ABSPATH' ) || exit;

/**
 * Interface that all OAT domains must implement.
 *
 * Each domain is a configuration of the shared workflow engine — it defines
 * its workflow steps and validation rules. Form fields are now managed via
 * the oat_forms / oat_form_fields tables (Phase 9).
 *
 * Optional methods (not in interface, checked via method_exists):
 *   get_meta_keys()    — legacy fallback for owbn-client pre-migration compat
 *   seed_form_fields() — seeds form field rows during activation
 */
interface OAT_Domain_Interface {

    /**
     * @return string Unique slug (e.g., 'character_lifecycle').
     */
    public function get_slug();

    /**
     * @return string Display name.
     */
    public function get_label();

    /**
     * Workflow template — array of step definitions.
     *
     * @return array
     */
    public function get_workflow_template();

    /**
     * Domain-specific validation on submit/resubmit.
     *
     * @param object $entry The entry object.
     * @param array  $meta  The meta values being submitted.
     * @return true|WP_Error True if valid, WP_Error if not.
     */
    public function validate( $entry, $meta );

    /**
     * @return string 'auto' or 'manual' — archivist step behavior.
     */
    public function get_archivist_mode();
}
