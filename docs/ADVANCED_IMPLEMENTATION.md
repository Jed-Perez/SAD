# Advanced workflows: audit and implementation

The existing PHP/PDO application is retained. The working tree contained prior edits at the start of this work; they must not be reset. Existing service_events, stock_movements, messages, settings and blueprints are reused. No customer or inventory records are replaced.

## Audit findings and plan

| Area | Existing support | Extension |
| --- | --- | --- |
| Identity | Server roles, CSRF, rotating sessions, private image access | Apply existing guards to all new actions; scoped record access |
| Service tracking | services and append-only service_events | Explicit status transitions, event status, ETA, notifications |
| Planner | Secure image uploads, four configurable AI providers, catalog estimates | Versioned editor, coverage controls, review history, comparisons |
| Scheduling | Technician lock and overlap checks | Work hours, time off, buffers, customer requests, calendars |
| Inventory | Row locks, atomic deductions, unique SKU | Idempotency, actual consumption forecasts, reservations and snapshots |
| Job cards | Assignment and parts history | Checklists, private photos, completion notes, acknowledgment, print |
| Support | Customer/office messages | Constrained AI guidance, verified account context, explicit handoff |
| Quotations | Preliminary blueprint estimate | Immutable versions, item snapshots, approval events, printable document |
| Notifications | Message unread counter | Preferences, categories, pagination, deduplication and reminder worker |

Implementation order: additive migration and shared helpers; transactional backend features; existing screen integration; regression and browser checks. New modules live under app/Advanced and assets/js/Advanced. Migration 005 is rerunnable and preserves legacy records. Additional tables retain historical versions instead of overwriting them. The existing status enum gains Approved and Revision Required without removing existing values.

## Security and operations

Never output configuration secrets. Local keys remain in ignored config.local.php. Previously disclosed keys must be rotated before production. Configuration inspection reports only presence or source, never values. Backups are private under storage/backups and excluded from Git. The CLI backup runs before applying migration 005 to the working database. DDL is not transactionally reversible in MariaDB; take a database backup before migration. Roll back application code only after exporting newly created records; never drop historical tables as an automatic rollback.

Existing tests cover authentication, CSRF, data isolation, private images, stock validation and scheduling. New regression checks cover version conflicts, approval, booking, inventory idempotency, notifications, checklist access and assistant boundaries. Test results and remaining operational setup are recorded here after execution.

## Delivered modules and verification — 2026-11-19

All eight workflows now have integrated database, API and browser implementations in the existing portal. The implementation uses the existing PHP/PDO architecture and existing secure blueprint uploads, inventory catalog, AI provider transports, session controls and customer messages.

- A private database/source backup was completed in `storage/backups/20261119-133432/` before migration. The earlier `20261119-133240/` attempt has a database dump but its source archive is incomplete; use the later complete backup.
- Migration 005 was applied to the working database and rerun successfully against isolated test databases. No existing customer or business records were deleted or reseeded.
- `php tests/unit.php` passed the existing planner/provider unit checks.
- Syntax validation passed for 47 application/entry PHP files and 18 JavaScript files.
- `python tests/integration.py --browser` passed **446 checks**, including the existing application regression suite, all advanced API workflows, Chromium desktop/mobile UI, blueprint editing and persistence, quotation revisions, PDF output, protected photo upload, booking, support and notification controls.
- Independent concurrent PHP processes verified that duplicate stock submissions deduct only once and competing customer bookings cannot both reserve the same slot.
- An intentional database-trigger failure verified rollback of both service creation and its timeline when notification persistence fails.
- Data isolation and CSRF checks cover the new actions. Mobile checks include 360px, 390px and 768px viewports. Screenshots and the rendered quotation PDF are under `tests/.artifacts/` and remain ignored.
- A repeated-read race discovered in booking was fixed by using current locking reads after the technician lock. Background notification polling was added to the session-inactivity exemptions. A booking form handler name collision found by browser tests was fixed.

The browser uses installed Chromium. Live paid AI calls were deliberately excluded from tests; provider parsing and failure behavior are covered, and the new assistant's unconfigured-provider fallback was exercised. External email inbox delivery, Safari/Firefox, a physical touchscreen, recovery of the backup into a separate server, and unattended scheduler operation were not verified. Configure business hours/rates and schedule the reminder worker before relying on unattended reminders. See [operating instructions](ADVANCED_OPERATIONS.md).
