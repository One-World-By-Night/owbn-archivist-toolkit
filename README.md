# OWBN Archivist Toolkit

## Executive Summary

The **OWBN Archivist Toolkit** is a modular, extensible WordPress plugin designed for `archivist.owbn.net`, the authoritative archive and long-term recordkeeping site for the One World by Night network. It supports storage, indexing, and passive or active data sharing for systems mastered locally or coordinated with other OWBN properties.

Where the Coordinator Toolkit focuses on tools for distributed action (like voting and territory management), the Archivist Toolkit serves as the **data hub**, maintaining **canonical records**, **historical outputs**, and **synchronized artifacts**. Some tools may be fully mastered in the archivist space (e.g., CCDB), while others act in a support capacity to tools mastered elsewhere (e.g., vote results from Council).

It supports:

- Long-term storage of finalized or reference content
- Master roles for key data (e.g., CCDB, document archives)
- Webhook endpoints for receiving updates from other tools
- Passive render interfaces and minimal admin interaction

## Overview

The OWBN Archivist Toolkit follows the same modular architecture as the Coordinator Toolkit. Each tool is an isolated directory within `/tools/` and contains all logic, templates, and data handling required for its domain.

The archivist site acts as either:

- **Master**: Hosting authoritative versions of post types and documents (e.g., the CCDB).
- **Listener**: Receiving webhook data (e.g., vote summaries, logs, policy documents).
- **Viewer**: Displaying public or semi-public records in a read-only or browseable format.

The Archivist Toolkit may also include tools that do not exist in other environments, such as:

- **Document Archive** – for storing public PDFs, historical vote results, etc.
- **Chronicle Archives** – storing snapshots of chronicle data for history or audit.
- **Binding Agreements** - for snapshots of all binding agreements and approvals.
- **Character Connections Hub** - for connecting characters to approved assets such as CCDB, Binding Agreements, etc.
- **Narrative History** – tools that log and organize IC timeline, metaplot events, or summaries.

Each tool includes:

- Custom Post Types (CPTs)
- Admin UI (when needed)
- Shortcodes and renderers
- Webhook handlers for sync
- Optional JS/CSS asset bundles

## Core Design Principles

### Centralized Authority and Read-Only Views

- Many tools in the Archivist Toolkit will be **read-mostly** or **append-only**.
- Public tools may offer front-end filtering, search, download, and historical browsing.
- Admin UIs are limited to site staff and only when manual input is required.

### Interoperability With Coordinator Toolkit

The Coordinator and Archivist Toolkits will interact via **shared webhook contracts**. For example:

- `council.owbn.net` generates a finalized vote PDF
- It sends the file and metadata via a webhook to `archivist.owbn.net`
- The Archivist Toolkit accepts and stores it in the **Document Archive** module

### Master Site Declaration (Optional)

Some tools may still define the archivist site as **master**, such as:

- `ccdb/` – Mastered and stored at archivist
- `docvault/` – Repository for long-term PDF and text document storage
- `narrative/` – Master tracker for OWBN-level metaplot continuity

## File Structure

```
owbn-archivist-toolkit/
├── assets/
│   ├── css/
│   │   ├── style.css
│   │   ├── toolkit-ccdb.css
│   │   └── toolkit-docvault.css
│   └── js/
│       ├── toolkit.js
│       ├── toolkit-ccdb.js
│       └── toolkit-docvault.js
├── includes/
│   ├── core/
│   │   ├── init.php
│   │   ├── helpers.php
│   │   └── webhook-router.php
│   ├── admin/
│   │   └── settings.php
│   └── render/
│       └── render-functions.php
├── tools/
│   ├── ccdb/
│   ├── docvault/
│   ├── chronicle-snapshots/
│   ├── narrative/
│   └── _template/
├── languages/
│   └── owbn-archivist-toolkit.pot
├── owbn-archivist-toolkit.php
├── readme.txt
└── README.md
```

## Activation and Configuration

1. Upload the plugin to `/wp-content/plugins/owbn-archivist-toolkit/`.
2. Activate it in the WordPress admin interface.
3. In `includes/core/init.php`, declare active tools in the `$tools` array.
4. If the site is master for a given tool, declare it:

```
define( 'OWBN_AT_MASTER_FOR_CCDB', true );
define( 'OWBN_AT_MASTER_FOR_DOCVAULT', true );
```

5. Ensure webhook keys and receiving endpoints are registered correctly.

## Webhook Infrastructure

Like the Coordinator Toolkit, the Archivist Toolkit centralizes webhook management in:

```
includes/core/webhook-router.php
```

Each tool may register one or more webhook handlers to accept data from other OWBN sites. These are primarily used for:

- Ingesting documents
- Syncing CPT entries
- Recording finalized votes, proposals, policy updates
- Storing historical records

Webhooks are **read-only receivers** unless explicitly authorized to write or overwrite.

## Tool Asset Loading

All tools may define tool-specific JS and CSS assets using this naming convention:

- `toolkit-<tool>.js`
- `toolkit-<tool>.css`

Assets are conditionally loaded by hook logic and should only be included on pages relevant to the tool.

## Versioning and Metadata

Each file includes headers for consistent automation:

- `@version`
- `@tool`
- `@author`

These are updated on tool creation using the same conventions as the Coordinator Toolkit and help with build and deployment tracking.

## Future Enhancements

- Public filter/search interfaces for each archive-type tool
- Secure download access and audit logging
- Graphical display of IC metaplot data
- REST endpoints for trusted consumers
- Custom taxonomy browsing (e.g., by date, faction, chronicle)

## Changelog

### 0.1.0

- Core plugin structure implemented
- Scaffolding created for: `ccdb`, `docvault`, `chronicle-snapshots`, `narrative`
- Webhook receiver support added
- File and header standards aligned with Coordinator Toolkit