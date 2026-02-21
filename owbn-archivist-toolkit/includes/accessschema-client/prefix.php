<?php

/** File: prefix.php
 * Text Domain: accessschema-client
 * version 2.4.0
 *
 * @author greghacke
 * Function: Instance-specific configuration for this embedded accessSchema client.
 *
 * Each plugin that embeds the client sets its own prefix and label here.
 * Uses variables (not constants) so multiple plugins can each have their own values.
 */

defined( 'ABSPATH' ) || exit;

// Unique prefix for this embedded instance of accessSchema-client.
// Change these to match your plugin.
$asc_instance_prefix = 'OAT';
$asc_instance_label  = 'OWbN Archivist Toolkit';
