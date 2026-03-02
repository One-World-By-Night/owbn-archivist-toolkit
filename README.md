# OWbN Archivist Toolkit

WordPress plugin providing a workflow engine for OWbN organizational requests and approvals. Replaces email-based processes with structured routing, audit trails, and a permanent registry.

**Version**: 0.4.0
**Requires PHP**: 7.4
**License**: GPL-2.0-or-later

## Installation

1. Copy `owbn-archivist-toolkit/` into `/wp-content/plugins/`
2. Activate in WordPress admin
3. Run the seeder to populate default domains and workflow steps

## Architecture

- **Domains** define request types (character lifecycle, custom content, chronicle reporting, etc.)
- **Workflow engine** routes entries through step-based approval chains
- **Models** map to custom DB tables (`oat_entries`, `oat_timeline`, `oat_assignees`, etc.)
- **Actions** handle state transitions (approve, deny, bump, delegate, hold, etc.)
- **Timer engine** processes expiration via WP-Cron
- **Notifications** dispatch via email, dashboard, and API channels

Uses AccessSchema for role-based permissions.

## Changelog

### 0.4.0

- Stripped trivial CRUD PHPDoc and step-numbered comments

### 0.3.0

- Player Actions domain, on-approve hooks, record snapshots

### 0.2.0

- Core workflow engine, domain registry, timer processing

## Contributing

[github.com/One-World-By-Night/owbn-archivist-toolkit](https://github.com/One-World-By-Night/owbn-archivist-toolkit)
