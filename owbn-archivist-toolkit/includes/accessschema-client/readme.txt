=== AccessSchema Client (Embedded Module) ===
Contributors: greghacke  
Tags: access control, remote API, roles, permissions  
Requires at least: 5.0  
Tested up to: 6.5  
Stable tag: 2.4.0  
License: MIT  
License URI: https://opensource.org/licenses/MIT  

An embeddable plugin module for querying and validating user access from an external WordPress AccessSchema server.

== Description ==

AccessSchema Client is not a standalone plugin — it is a modular component designed to be embedded inside another WordPress plugin that needs to verify user roles from a remote AccessSchema server.

This module:  
- Queries a remote AccessSchema API for user roles.  
- Checks if a user is granted a specific role or descendant.  
- Provides shortcode and programmatic utilities.  
- Uses a shared API key for secure access.

== How to Use ==

Place the accessschema-client folder inside your own plugin like so:

your-plugin/  
├── your-plugin.php  
└── includes/  
    └── accessschema-client/  
        ├── accessschema-client.php  
        ├── prefix.php  
        ├── includes/
            └── . . .

In your plugin’s main file:

require_once plugin_dir_path(__FILE__) . 'includes/accessschema-client/accessschema-client.php';

Edit the prefix.php file and set:
$asc_instance_prefix = 'YPP';
$asc_instance_label  = 'Your Plugin Label';
to match your plugin. (Uses variables, not constants, so multiple plugins can each embed their own client.)

== Configuration ==

Once initialized, a menu appears under “Users” as "{ASC_PREFIX} ASC" where you can configure:  
- Connection Mode (remote, local, none)  
- Remote API URL and key  
- Capability-to-role mapping for WP core caps

These are stored in options like:  
- YPP_accessschema_mode  
- YPP_accessschema_client_url  
- YPP_accessschema_client_key  
- YPP_capability_map

== Developer API ==

Use in plugin code:

accessSchema_client_remote_check_access( 'user@example.com', 'Chronicle/KONY/HST', 'ypp' );  
accessSchema_client_remote_grant_role( 'user@example.com', 'Coordinator/Tzimisce/Player' );  
accessSchema_client_remote_get_roles_by_email( 'user@example.com' );  
accessSchema_access_granted( 'Chronicle/KONY/*' );

== Usage Examples ==

Example 1: Use asc_has_access_to_group to verify access by group path.

$has_group_access = current_user_can( 'asc_has_access_to_group', "Chronicle/{$group}" );

if ( ! $has_group_access && ! current_user_can('administrator') ) {
    echo '<p>You do not have access to this Chronicle: <strong>' . esc_html( strtoupper( $group ) ) . '</strong></p>';
    return;
}

Example 2: Map core capabilities like edit_post for a Coordinator group.

$has_group_access = current_user_can( 'edit_post', "Coordinator/{$group}/Coordinator" );

if ( ! $has_group_access && ! current_user_can('administrator') ) {
    echo '<p>You cannot edit this: <strong>' . esc_html( strtoupper( $group ) ) . '</strong></p>';
    return;
}

Capability mapping (in settings or via update_option):

'edit_post' => ['Coordinator/$slug/Coordinator']

Example 3: Map core capabilities like edit_post for a Chronicle senior staff groups.

$has_group_access = current_user_can( 'edit_post', "Chronicle/{$group}/CM" )
                 || current_user_can( 'edit_post', "Chronicle/{$group}/ST" );

if ( ! $has_group_access && ! current_user_can('administrator') ) {
    echo '<p>You cannot edit this: <strong>' . esc_html( strtoupper( $group ) ) . '</strong></p>';
    return;
}

Capability mapping (in settings or via update_option):

'edit_post' => [
    'Chronicle/$slug/CM',
    'Chronicle/$slug/ST'
]

== Shortcode ==

Use [access_schema_client]...[/access_schema_client] to conditionally show content:

[access_schema_client role="Chronicles/ABC/HST"]  
Welcome, Head Storyteller!  
[/access_schema_client]

[access_schema_client any="Chronicles/ABC/HST, Chronicles/ABC/AST" wildcard="true" fallback="You do not have access."]  
Only visible to staff.  
[/access_schema_client]

== Shortcode Attributes ==

- role: Single role path  
- any: Comma-separated list of paths  
- wildcard: true to enable wildcard logic  
- fallback: Shown when access denied  
- children: Only applies to exact path matches

== Filters ==

Use the accessSchema_access_granted filter to monitor access:

add_filter('accessSchema_access_granted', function($granted, $patterns, $user_id) {
    $user = get_userdata($user_id);
    error_log("[AccessSchema] {$user->user_email} matched: " . implode(', ', $patterns));
    return $granted;
}, 10, 3);

== Changelog ==

= 2.4.0 =
- Shared cache key: all embedded client instances now read/write the same user meta key (accessschema_cached_roles)
- Eliminates stale-cache problem when multiple plugins embed the client — any plugin's refresh populates for all
- Login cache hook now breaks after first successful refresh (shared key means one refresh is enough)
- No change to per-instance settings (URL, API key, mode) — those remain prefixed

= 2.3.0 =
- Full variable-based refactor for true multi-instance support
- prefix.php now sets $asc_instance_prefix / $asc_instance_label variables (not constants)
- Each embedded client plugin loads its own prefix.php independently
- ASC_PREFIX / ASC_LABEL constants retained for backward compatibility (first plugin wins)
- Admin settings page iterates all registered slugs instead of reading ASC_PREFIX
- user_has_cap filter iterates all registered client instances; if any grants access, capability is allowed
- Early return on first match in user_has_cap for performance

= 2.2.0 =
- Multi-instance safety: all shared hooks now register once regardless of how many plugins embed the client
- Moved user_has_cap filter registration inside function_exists() guard to prevent duplicate execution
- Removed duplicate admin_action_* hooks (consolidated into admin_init handler in users.php)
- Guarded profile display hooks with ASC_CLIENT_PROFILE_HOOKS_REGISTERED constant
- Guarded Users page hooks with ASC_CLIENT_USERS_HOOKS_REGISTERED constant
- CSS handle collision fix: checks wp_style_is() before re-registering

= 1.2.0 =
- Switched from slug to client_id everywhere
- Added login caching and refresh logic
- Added remote + local fallback
- Improved error and access logs
- Clarified shortcode behavior and fallback

= 2.1.2 =
- Added function_exists() guard on accessSchema_client_render_grouped_roles() to prevent fatal on duplicate load

= 2.0.4 =
- PHP 7.4 compliance (replaced match expression with switch)
- Version sync with server plugin

== License ==

GPL-2.0-or-later