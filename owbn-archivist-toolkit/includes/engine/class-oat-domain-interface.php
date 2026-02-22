<?php

defined( 'ABSPATH' ) || exit;

/**
 * Interface that all OAT domains must implement.
 *
 * Each domain is a configuration of the shared workflow engine — it defines
 * its steps, meta keys, form fields, and validation rules.
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
     * Each step is an associative array with keys:
     *   'id'                 => string,
     *   'label'              => string,
     *   'assignee_role'      => string (accessSchema role path pattern),
     *   'visibility_tier'    => string ('staff'|'coordinator'|'archivist'),
     *   'on_approve'         => string|null (next step ID or null for terminal),
     *   'on_deny'            => string|null,
     *   'on_request_changes' => string|null (previous step ID),
     *   'timer'              => array|null (timer config),
     *   'condition'          => array|null (conditional routing),
     *   'multi_approve'      => bool (default false),
     *
     * @return array
     */
    public function get_workflow_template();

    /**
     * Meta key definitions — array of key name => config.
     *
     * Config keys: 'label', 'type' ('text'|'textarea'|'select'|'number'),
     * 'required' (bool), 'options' (for select).
     *
     * @return array
     */
    public function get_meta_keys();

    /**
     * Form field specs for submission and review forms.
     *
     * @return array
     */
    public function get_form_fields();

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
