# AccessSchema Client Library (Distributable)

**Version: 2.0.0** (Production Release - February 2026)

This is the **clean, production-ready** AccessSchema client library for distribution with the accessSchema server plugin.

## Purpose

This directory contains the embeddable client library that other WordPress plugins can use to integrate with the accessSchema RBAC system.

## Usage

Consumer plugins should:
1. Copy this entire `accessschema-client/` directory into their plugin
2. Rename `prefix.php.example` (if exists) or modify `prefix.php` to set their plugin-specific constants:
   - `ASC_PREFIX` - Unique prefix for this client instance (e.g., 'wpvp' for wp-voting-plugin)
   - `ASC_LABEL` - Human-readable label (e.g., 'Voting Plugin')
3. Require the client: `require_once plugin_dir_path( __FILE__ ) . 'accessschema-client/accessSchema-client.php';`

## Code Quality

✅ **PHPCS Clean** - 0 errors, 0 warnings (WordPress Coding Standards)
✅ **Security Hardened** - All inputs sanitized, outputs escaped, nonces verified
✅ **WordPress Standards** - Yoda conditions, wp_unslash, wp_safe_redirect
✅ **Production Ready** - No debug code, no empty placeholders

## Version 2.0.0 Changes

### Critical Fixes
- ✅ CSS debug banner removed (no more red overlay)
- ✅ Function name bugs fixed (3 instances: missing client_ prefix, parameter order)
- ✅ Debug code removed (backtrace, 16+ commented error_log statements)

### Code Quality
- ✅ 1,771 PHPCS violations fixed → 0 errors, 0 warnings
- ✅ Empty placeholder files deleted (tests/core.php, tests/utils.php, tools/functions.php)
- ✅ Log prefix normalized to [AccessSchema Client]
- ✅ Error logging cleaned (no print_r in production)

### Security Hardening
- ✅ 4 nonce verifications added
- ✅ 4 output escaping fixes
- ✅ 7 wp_unslash() calls added
- ✅ 3 wp_safe_redirect() implementations
- ✅ 14 Yoda conditions applied

Last updated: February 2026
Based on: AccessSchema Cleanup & Hardening Plan (Phases 0-6)

## Integration Points

- **REST API Client** - `includes/core/client-api.php`
- **Permission Checks** - Integrates with WordPress `user_has_cap` filter
- **Admin Interface** - Settings page for mode configuration (remote/local/none)
- **Caching** - User role cache with manual flush/refresh options

## Files

- `accessSchema-client.php` - Main bootstrap file
- `prefix.php` - Client-specific configuration (ASC_PREFIX, ASC_LABEL)
- `.phpcs.xml.dist` - PHPCS ruleset (intentional exclusions documented)
- `includes/` - All functional code (admin, core, render, shortcodes, etc.)
- `LICENSE` - MIT License
- `readme.txt` - WordPress readme format

## Support

This client is maintained as part of the accessSchema server plugin.
For issues or questions, see the main accessSchema documentation.
