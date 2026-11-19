# Operating the advanced workflows

Open `http://localhost/SAD/portal.php`. Existing accounts and roles remain in use.

1. Administrators configure **Settings**: labor, cable allowances, contingency, quote tax/discount limits, visit durations and service checklist templates. Existing catalog prices remain the equipment source.
2. Office staff open **Technician schedule → Work hours & time off** and publish each technician's working days. Buffers are applied before and after a visit. No customer slots appear for an unconfigured day. Review existing visits before changing availability.
3. Customers use **Book a visit**. Pending requests reserve time, and staff approve/cancel them from the schedule. A reschedule request preserves the original confirmed appointment until approved. Cancel abandoned requests to release their times.
4. **CCTV planner → Edit camera layout** supports eight cameras (the existing eight-channel recorder), pointer/touch dragging, background pan, zoom, keyboard arrows, R to rotate, Delete, undo/redo, versions and approximate viewing cones. Comparisons are subsets of the customer's list, not claims of optimal layouts. Each save uses current catalog prices. Staff review a saved version and keep internal notes separate from customer feedback.
5. **Quotations** creates drafts against an existing service and optional same-customer plan. Staff can populate equipment from the saved plan. Sending exposes the proposal to its customer. Decisions require the current version. Accepted versions cannot be revised. Print / Save PDF uses the browser's PDF output; no paid or server PDF dependency is needed.
6. **Service details → Digital job card & report** contains the assigned work, equipment, checklist, notes and protected photos. A technician starts the service before completion. All checklist tasks must be checked. Parts use the existing stock form; its submission key prevents retry deductions. The customer acknowledges a completed visit separately from payment or legal acceptance.
7. **Stock forecast** uses recorded withdrawals over 90 days. Prediction requires three usage days spanning at least fourteen days. Approved equipment requirements subtract parts already used. Supplier lead time is optional. Inventory value history begins with actual snapshots; past values are never fabricated.
8. **Notifications** offers category filters, older pages, read controls and preferences. **Ask TechTrack** is available to customers. With explicit consent, the configured AI provider classifies the question; verified local data and curated guidance supply the answer. It cannot perform administrative actions. The last eight conversation entries are shared with staff only after consent. Users may clear assistant history.

## Reminder and snapshot worker

Run this CLI command every fifteen minutes with Windows Task Scheduler (or your production scheduler), under the account that can access this project and MySQL:

```powershell
C:\xampp\php\php.exe C:\xampp\htdocs\SAD\app\Advanced\reminders.php
```

It generates deduplicated upcoming-visit and warranty reminders, low-stock notices, and daily inventory value snapshots. A database advisory lock prevents simultaneous workers. Live status, message, review and quotation notifications are created immediately in their business transaction; the browser checks the bell every thirty seconds while visible. Email remains off until `notification_email_enabled` is explicitly enabled in ignored `config.local.php`, a valid `mail_from` and PHP mail transport are configured, and the recipient opts in. No SMS service is introduced. A mail row left in `Sending` after interruption needs operator review to avoid duplicate delivery. A successful PHP mail handoff is not proof of inbox delivery.

## Migration, backup and recovery

`app/Database/backup.php` is CLI-only and stores a database dump and source archive under the web-blocked, Git-ignored `storage/backups/`. Backups may contain private data and credentials and should be protected like the live database. Migration 005 is additive and safe to rerun with `php migrate.php`; it preserves current records and adds workflow history tables. MariaDB DDL cannot be rolled back as one transaction. Restore into a separate database first when rehearsing recovery; do not automatically drop new history tables.

Run `php tests/unit.php` and `python tests/integration.py --browser`. Integration tests create their own randomly named database and clean it up. Concurrent booking/stock tests use separate PHP processes. Browser tests produce local screenshots and a sample quotation PDF under ignored `tests/.artifacts/`.

Production readiness still requires business-specific hours/rates, a scheduled reminder worker, tested email delivery if used, and live AI-provider verification with approved nonprivate data. Previously exposed API credentials must be rotated before production. Browser checks cover installed Chromium; Safari/Firefox and a physical touchscreen require separate acceptance testing.
