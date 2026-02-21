<?php

/** File: includes/core/init.php
 * Text Domain: accessschema-client
 * version 2.0.4
 *
 * @author greghacke
 * Function: Init core functionality for the plugin
 */

defined( 'ABSPATH' ) || exit;

/** --- Require each core file once --- */
require_once __DIR__ . '/client-init.php'; // e.g., register client plugin, hooks, etc.
require_once __DIR__ . '/client-api.php';
