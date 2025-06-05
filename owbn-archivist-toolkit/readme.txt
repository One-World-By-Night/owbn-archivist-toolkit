=== OWBN Coordinator Toolkit ===
Contributors: greghacke
Tags: owbn, coordinator, multisite, toolkit, modules, webhooks
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 0.1.0
Version: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The OWBN Coordinator Toolkit is a modular, extensible WordPress plugin that provides shared coordinator functionality across all OWBN network sites, including council.owbn.net and archivist.owbn.net.

== Description ==

This plugin serves as a flexible framework for enabling coordinator and storyteller tools across the OWBN network. Designed with a modular architecture, it allows each site to load only the tools it needs, and enables cross-site communication via webhooks to maintain synced functionality and content consistency.

Each module (tool) within the plugin is self-contained, with its own custom post types, admin UI, shortcodes, render helpers, and webhook routing. Tools can declare themselves as the "master" for specific content domains (e.g., the Custom Content Database or Territory management), and other sites defer to the master for actions like editing or downloading related artifacts.

Key components:

* Modular structure (`/tools/<name>/`) allows future growth
* Cross-site communication via JSON webhooks
* Support for declaring "master" and "remote" roles for content domains
* Shared rendering, admin logic, and hooks across tools
* Per-tool CSS/JS asset separation
* Version-controlled and namespaced to support iterative development

Initial tools include:

- **CCDB** – The Custom Content Database module (mastered on archivist.owbn.net)
- **Territory** – Handles domain ownership and territory-based approvals/voting

== Installation ==

1. Upload the plugin to your `/wp-content/plugins/` directory or install via the WordPress admin interface.
2. Activate the plugin.
3. Use admin configuration or constants to declare which tools are active and which content areas are "mastered" on the current site.

== Frequently Asked Questions ==

= Can I use this plugin without enabling all tools? =
Yes. The toolkit is modular by design. You can selectively load only the tools needed for each site.

= How do I declare a site as master for a given tool? =
Each tool supports master declarations via PHP constant, admin UI, or a future JSON configuration loader.

= How does cross-site communication work? =
Tools register webhook listeners and emitters. Webhook routing is handled through a shared router inside `/includes/core/webhook-router.php`.

== Screenshots ==

Available at...

== Changelog ==

= 0.1.0 =
* Initial plugin architecture and module scaffolding.
* Added `ccdb` and `territory` tool modules.
* Per-tool asset structure and metadata consistency.
* Webhook routing bootstrap in core.

== Upgrade Notice ==

= 0.1.0 =
This is the initial release. Please review and configure `tools/` module activations and ensure proper webhook endpoints are accessible.