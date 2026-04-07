# OWbN Archivist Toolkit (OAT)

The workflow engine for One World by Night. Handles organizational requests, approvals, and the permanent character registry.

Version: 1.10.1 (DB v1.8.3)
Deployed to: archivist.owbn.net

## What It Does

OAT replaces OWBN's email-based request processes with structured, auditable workflows. Players submit requests (character transfers, custom content, disciplinary actions, etc.), which route through chronicle staff, coordinators, and the Archivist office for approval. Every action is logged in a permanent timeline.

The plugin also maintains the organization's character registry -- the authoritative record of all active characters across all chronicles.

## How It Works

- Domains define request types -- character lifecycle, custom content, chronicle reporting, disciplinary actions, and more. Domains are database-driven and admin-configurable without code changes.
- Workflow steps chain together into approval routes. Each step can require sign-off from staff, coordinators, or the archivist office. Steps support conditional routing, multi-approval, delegation, and hold/resume.
- 15 action types (approve, deny, bump, delegate, hold, request changes, etc.) compose into domain-specific behaviors.
- Timer engine handles deadlines via WP-Cron -- auto-deny on expiration, bump-bump-pass escalation.
- Notifications go out via email and dashboard inbox with per-user preferences.
- Creature taxonomy -- 4-level hierarchy (genre, faction, type, variant) with admin editor and cascading pickers.
- Role scoping via accessSchema -- users see only what their chronicle/coordinator/exec role grants them.

## Architecture

- Archivist.owbn.net runs the full plugin with admin UI and direct DB access.
- All other OWBN sites interact via REST API through owbn-client.
- Elementor widgets provide the front-end experience (dashboard, inbox, entry detail, submit forms).

## Requirements

- WordPress 5.0+, PHP 7.4+
- accessSchema for permissions
- owbn-client on remote sites

## License

GPL-2.0-or-later
