# OWbN Archivist Toolkit

A unified administrative process tool for One World by Night, built as a WordPress plugin.

---

## 1. Executive Summary

The OWbN Archivist Toolkit replaces the patchwork of email threads, spreadsheets, and aging Drupal forms that currently handle organizational requests across OWbN. It gives every request — from character transfers to custom content approvals to chronicle reports — a single place to live, a clear path through approval, and a permanent, searchable record when it's done.

**What it does:**

- Accepts submissions from players, staff, and coordinators
- Routes them through the right approval chain based on what the request is
- Enforces who can see and act on what, using AccessSchema roles everyone already has
- Records every approved outcome to a permanent, searchable registry
- Notifies the right people at the right time — no more wondering if your request was received

**What it means for you:**

- **Chronicles:** One place to submit reports, character actions, and requests. Track where things are instead of chasing email threads.
- **Coordinators:** A pending-items inbox instead of scattered emails. Clear audit trail for every decision you make.
- **Archivist Office:** Every approved action lands in the registry automatically. No more reconstructing history from forwarded emails.
- **Players:** Submit requests and actually see where they are in the pipeline.

**What it doesn't do:**

- It does not replace Council voting (the voting plugin handles that)
- It does not change who has authority over what — it enforces the existing organizational structure
- It does not require anyone to learn a new permissions system — it uses AccessSchema roles that are already assigned

---

## 2. The Problems We're Solving

### Requests get lost

There is no central system tracking where a request is in an approval pipeline. An R&U submission exists as an email thread between a player, their ST, and a coordinator. If any link in that chain drops the ball, the request stalls silently. Nobody knows it's stuck until someone asks.

**How we handle it:** Every request is a tracked entry with a visible status. It sits in someone's inbox until they act on it. If they don't act, timers fire — including the Bump Bump Pass process, which is enforced automatically with the correct 14-day window, bump requirements, and auto-approval on expiry.

### No audit trail

When a coordinator approves a character action, that approval lives in an email. When the Archivist records it, they may or may not have the full context of what was approved and why. Disputes about what was agreed to become "he said, she said."

**How we handle it:** Every action on a request — submission, approval, denial, changes requested, notes added — is recorded on an append-only timeline. The timeline travels with the entry all the way to the Archivist. The final record is the complete story, not just the outcome.

### The Archivist reconstructs history manually

The Archivist Office currently receives approved items through various channels and must piece together what happened. There's no guarantee they receive everything, and no standardized format.

**How we handle it:** Every approved item flows through the system to the Archivist as the final step. Depending on the domain, the Archivist either auto-records it (for simple pass-throughs like chronicle reports) or manually reviews it (for items that need compilation, like custom content). Either way, nothing approved skips the registry.

### Routing is tribal knowledge

Which coordinator handles a combo discipline that uses both Obtenebration and Blood Magic? The answer lives in people's heads and in bylaw sections that not everyone has memorized. New staff members have to learn by asking.

**How we handle it:** A digital Regulation Lookup Table encodes the Character Regulation Bylaws' Controlled Items. The system knows which items are regulated, at what level, and by which coordinator(s). Routing is data-driven — change the lookup table when bylaws change, and routing updates automatically. No code changes needed.

### Requests skip levels or bypass process

A coordinator should never communicate directly with a player through the tool — staff are the intermediary. But without a system enforcing the flow, shortcuts happen.

**How we handle it:** The workflow enforces tier structure. A coordinator requesting changes sends the entry back to staff, who then communicate with the player. Each level sees only the context appropriate to their role. Internal coordinator-to-staff discussion is not visible to players.

### Holds and delays have no accountability

A request can sit indefinitely because one party decided to stop responding. There's no mechanism to surface stalled items or force resolution.

**How we handle it:** Holds require mutual agreement (the other party approves) and a mandatory resume timer. When the timer fires, the entry automatically resumes. Executive oversight can impose holds unilaterally when needed and can also force items to resume. Nothing stays parked forever.

---

## 3. How It Works

### Domains

The toolkit handles six types of organizational processes at launch. Each is a "domain" — a configured workflow using shared building blocks.

| Domain | Who submits | Approval path | What gets recorded |
|--------|------------|---------------|-------------------|
| **Chronicle Reporting** | Staff (CM/HST) | Staff ⟷ Archivist | Monthly reports, game data |
| **Character Lifecycle** | Player or Staff | Player ⟷ Staff ⟷ Coordinator ⟷ Archivist | Transfers, deaths, registration, R&U, learning custom content |
| **Custom Content** | Staff (HST) | Staff ⟷ Coordinator ⟷ Archivist | New disciplines, combos, rituals, merits |
| **Binding Agreements** | Player or Staff | Player ⟷ Staff ⟷ Coordinator → Archivist | Cross-chronicle agreements |
| **Disciplinary Actions** | Staff or Exec | Staff → Archivist (local) / Exec → Archivist (global) | Disciplinary records |
| **Governance Records** | Admin Coordinator | Admin → Archivist | Organizational records |

`⟷` means the step can loop (approve / deny / request changes). `→` means one-way pass-through.

New domains can be added in the future without modifying the core system.

### The Request Lifecycle

Every request follows the same fundamental pattern at each approval level:

1. **Someone submits** (or revises and resubmits)
2. **An assignee reviews** and chooses one of:
   - **Approve** — moves to the next level
   - **Deny** — closes the request with a reason
   - **Request Changes** — sends it back one level for revision
3. **The cycle repeats** at each tier until the request is either approved through all levels, denied, or withdrawn

The Archivist is always the final step for approved items.

### Actions Available

At any review step, the assignee can also:

- **Reassign** to someone else in the same role (e.g., coordinator to subcoordinator)
- **Delegate** to someone outside the normal pool (e.g., cross-genre expertise)
- **Hold/Pause** the request (requires the other party's agreement + a resume timer)
- **Add watchers** so others get notified of updates

The originator (person who submitted) can **cancel/withdraw** at any time before final recording.

### Timers and Deadlines

Steps can have timers attached. The most important one:

**Bump Bump Pass** — When a coordinator doesn't respond to an R&U request:
- 14-day timer starts when the request reaches the coordinator
- The submitter must send 2 bumps (reminders within the system)
- If the coordinator still hasn't acted after the timer and bumps, the request auto-approves
- Executive Team is notified
- Timer resets if the coordinator requests changes (fair — they responded)
- Positions and ranks cannot auto-approve (Exec must intervene)

Timer extensions are available through the Executive Team for vacancies, unavailability, and blackout periods.

### Regulation Lookup Table

The digital version of the Controlled Items list from the Character Regulation Bylaws. It determines:

- Whether a coordinator approval step is needed
- Which specific coordinator(s) are assigned
- Whether the item auto-approves or auto-denies if no one responds
- Whether auto-approval is blocked (elevation items)

Six regulation levels: **Unregulated**, **Coordinator Notify**, **Coordinator Approval**, **Disallowed**, **Majority Vote**, **2/3 Majority Vote**. PC and NPC levels are tracked independently.

Multiple rules can match a single submission — a combo crossing genre lines requires approval from all relevant coordinators.

### Notifications

People get notified through three channels:

- **Email** — the "go look at this" nudge for time-sensitive events
- **Dashboard** — persistent inbox and notification log within WordPress
- **API** — delivery to external OWbN systems (e.g., players.owbn.net)

Notifications fire on: submission, approval, denial, changes requested, recording, cancellation, timer warnings, holds, resumes, and council overrides.

### Visibility

Each level sees what's appropriate to their role:

- **Players** see their own requests: status, decisions communicated to them
- **Staff** see all requests for their chronicle, including staff ↔ coordinator discussion
- **Coordinators** see requests in their genre, full context from their tier down
- **Archivist / Executive** see everything

Internal discussion between staff and coordinators is not visible to players. Notes meant for internal deliberation go on the timeline at the appropriate tier.

### Council Override

If a coordinator denies a request and Council subsequently passes it via vote, an authorized role can reopen the denied entry and advance it to the Archivist for recording. The original denial, the override, and the vote reference are all preserved. This is the only way a closed request can be reopened.

---

## 4. Technical Details

### Platform

- **WordPress plugin** (PHP + JavaScript)
- **AccessSchema Client** embedded for permission checks against the central AccessSchema server
- **WordPress custom database tables** (not post meta) for performance and query control

### Database Schema

Nine custom tables, all prefixed with `{wp_prefix}oap_`:

| Table | Purpose |
|-------|---------|
| `oap_entries` | Core workflow state — domain, status, step, originator, associated entities, dates |
| `oap_entry_meta` | Domain-specific data as key-value pairs (form fields, submission content) |
| `oap_timeline` | Append-only action/note log — the complete audit trail |
| `oap_assignees` | Step assignees + individual approval status (supports multi-approval) |
| `oap_watchers` | Notification recipients per entry |
| `oap_notifications` | Dispatch log — every notification sent, channel, recipient, delivery status |
| `oap_regulation_rules` | The Regulation Lookup Table (Controlled Items) |
| `oap_timers` | Active timer state — duration, bump count, expiry behavior |
| `oap_entry_relationships` | Typed, directional links between entries (informational, not routing) |

Adding a new domain requires **no new tables** — just a domain class with workflow configuration and meta key definitions.

### Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                     OAP Plugin                              │
│                                                             │
│  ┌────────────────┐  ┌────────────────────────────────────┐ │
│  │ Workflow Engine │  │  Domain Registry                  │ │
│  │                │  │  ┌──────────────────────────────┐  │ │
│  │ - Steps        │  │  │ Chronicle Rpt  │ Binding Ag. │  │ │
│  │ - Routing      │  │  │ Char Lifecycle │ Disciplinary│  │ │
│  │ - State        │  │  │ Custom Content │ Governance  │  │ │
│  │ - Timeline     │  │  └──────────────────────────────┘  │ │
│  └──┬─────────┬───┘  │  (+ external domains via hook)     │ │
│     │         │      └────────────────────────────────────┘ │
│     │         │                                             │
│  ┌──▼──────┐ ┌▼───────────────────┐ ┌──────────────────┐   │
│  │ Timer   │ │ Notification Engine │ │ Regulation       │   │
│  │ Engine  │ │                     │ │ Lookup Table     │   │
│  │         │ │ ┌─────┐ ┌───────┐  │ │                  │   │
│  │ - BBP   │ │ │Email│ │Dashbd │  │ │                  │   │
│  │ - Bumps │ │ ├─────┤ ├───────┤  │ │                  │   │
│  │ - Expiry│ │ │ API │ │Channel│  │ │                  │   │
│  └────┬────┘ │ └─────┘ └───────┘  │ └──────────────────┘   │
│       │      └─────────────────────┘                        │
│  ┌────▼────────────────────────────────────────────────┐    │
│  │ Data Layer (WP Custom Tables)                       │    │
│  │ entries │ meta │ timeline │ assignees │ watchers │ …│    │
│  └─────────────────────────────────────────────────────┘    │
│  ┌─────────────────────────────────────────────────────┐    │
│  │ AccessSchema Client (embedded)                      │    │
│  └─────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────┘
```

### Domain Registration

Each domain is a PHP class implementing `OAP_Domain_Interface`. Domains register via a WordPress filter hook (`oap_register_domains`), making the system extensible — other plugins can add domains without modifying core code.

A domain class defines:
- Workflow steps (sequence, assignee roles, available actions, routing, timers, conditions)
- Meta keys (domain-specific data fields)
- Form field specifications
- Validation rules
- Archivist mode (`auto` or `manual`)
- Domain-specific logic (e.g., coordinator resolution for Custom Content)

### Action Types

12 action types compose into domain-specific workflows:

| Category | Actions |
|----------|---------|
| **Core** (5) | Submit, Approve, Deny, Request Changes, Cancel/Withdraw |
| **Routing** (3) | Reassign, Delegate, Hold/Pause |
| **System** (4) | Notify, Timer, Record, Council Override |

Every user action requires a note. Notes are appended to the timeline and become part of the permanent record.

### Key Design Decisions

- **Hybrid data model** — shared workflow tables + flexible key-value meta per domain. No per-domain tables.
- **Conditional routing** — steps can be skipped or activated based on entry meta values, evaluated live (not snapshot at submission)
- **Multi-approval** — all-or-nothing; one deny = denied, request changes resets all approvals at that step
- **Request Changes** — always routes exactly one step back; no level-skipping
- **Originator** — always the person who clicked Submit; "on behalf of" is domain-specific meta
- **Entry relationships** — typed, directional links between entries for cross-domain references
- **Template versioning** — handled at the schema level for in-flight entries

### Workflow Template Example

```
Character Lifecycle R&U:

Step 1: Submit         → Player/* or Chronicle/*/Staff
Step 2: Staff Review   → Chronicle/*/HST
                         approve → step 3, deny → close, request changes → step 1
Step 3: Coord Review   → Coordinator/*/Coordinator (conditional: only if regulated)
                         approve → step 4, deny → close, request changes → step 2
                         timer: 14 days, 2 bumps, auto-approve on expiry (BBP)
Step 4: Record         → Exec/Archivist/Coordinator
                         archivist_mode: manual
```

### AccessSchema Role Paths

| Role | AccessSchema Path |
|------|-------------------|
| Player | `Player/*` |
| Staff | `Chronicle/*/HST` or `Chronicle/*/Staff` |
| Coordinator | `Coordinator/*/Coordinator` |
| Admin | `Exec/Admin/Coordinator` |
| Archivist | `Exec/Archivist/Coordinator` |
| Web Coordinator | `Exec/Web/Coordinator` |
| Executive oversight | `Exec/AHC1/Coordinator`, `Exec/AHC2/Coordinator`, `Exec/Head-Coordinator/Coordinator` |

### Legacy Migration

The existing Drupal system data will be migrated **after** the new tool is stable. The approach:
1. Build the new tool with a clean design (not constrained by Drupal's data model)
2. Export Drupal data
3. Transform and import into the new schema

No data will be lost. Migration is a formatting problem, not a data loss problem.

---

## Status

**Current phase:** Schema Design

The proposal and all architectural decisions are finalized (27 decisions confirmed). Database table definitions are being specified. No code has been written yet.

## License

See [LICENSE](LICENSE) for details.
