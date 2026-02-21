<?php

/** File: includes/admin/init.php
 * Text Domain: accessschema-client
 * version 2.0.4
 *
 * @author greghacke
 * Function: Init admin functionality for the plugin
 */

defined( 'ABSPATH' ) || exit;

/** --- Require each admin file once --- */
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/settings-fields.php';
require_once __DIR__ . '/users.php';
