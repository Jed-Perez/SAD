# TechTrack system audit - 2026-11-17

Status: implementation complete; final verification results are recorded below. This is a repository and local-runtime audit, with isolated functional tests. It is not a claim that every production failure, browser, concurrent race, or deployment configuration has been tested.

## A. System overview

TechTrack supports NCPCS inventory and service operations. The public homepage leads to customer registration, password or Google sign-in, and a role-specific portal. Administrators manage accounts, quotation rates, audit logs and operations; staff manage customers and operations; technicians work on assigned services; customers access their own requests, conversations and blueprints.

Technology: PHP 8, PDO prepared queries, MySQL/MariaDB with InnoDB, Bootstrap 5 and plain JavaScript Fetch/AJAX. Existing modules include dashboard, product photos and stock movements, customers/accounts, requests and scheduling, service parts and warranties, private messaging, CCTV blueprint analysis/estimates/office review, settings, account/audit logs, CSV and printable reports. CSS/JS are local assets; no application build step is required.

Reviewed areas: public entry points and server access rules; authentication, recovery, OAuth, sessions and CSRF; validation and database migrations; every business module listed above; shared rendering, forms, navigation and asynchronous behavior; responsive layouts, light/dark appearance and reports; existing and added test infrastructure. Automated coverage concentrates on realistic workflows and security boundaries. Remaining limits are explicit in sections F and G.

## Change plan recorded before authentication/schema implementation

The existing PHP session and role model will be retained. An additive `auth_devices` table will track signed-in devices and store SHA-256 hashes of persistent cookie selector/validator secrets, never raw passwords or cookie secrets. All interactive logins will register a device; opt-in Remember me will issue a 30-day HttpOnly/SameSite=Lax cookie (Secure on HTTPS), rotate its validator when it restores a browser session, and expire/revoke it on logout, password reset, deactivation or explicit device revocation. A short previous-validator grace period will handle concurrent browser requests. Normal sessions will have a 30-minute inactivity limit and a 12-hour absolute lifetime; background message polls will not extend activity. Device controls will be restricted to the signed-in account and require CSRF validation.

`004_auth_devices.sql` will only create a new table with indexes and a foreign key to users. Existing application rows will not be deleted, rewritten, or reseeded. Existing PHP sessions will be registered lazily on their next valid request. The production/local database will not be changed by audit tests; migrations will be prepared and exercised on isolated test databases. An installation must run the migration before using the updated authentication code.

Password confirmation will be required for new customer registrations. Existing account hashes will remain compatible; no password algorithm migration is planned. Raw passwords will be validated without trimming, and new bcrypt passwords will be limited to 72 bytes to prevent silent truncation. Existing users whose old passwords were normalized by the previous implementation can still log in through a narrowly scoped legacy normalization fallback, documented in the final report.

## B. Findings and completion checklist

All listed code corrections are implemented. Severity reflects the original issue, not a statement that the issue remains open. Verification details appear in section F.

| ID | Severity | Affected files/module | Issue and potential impact | Implemented correction |
|---|---|---|---|---|
| A01 | High | `app/Auth/devices.php`, `helpers.php`, `actions.php`; migration 004 | No durable device registry, remembered login or explicit idle/absolute expiry; stolen sessions could remain usable until ordinary session cleanup | Hashed, rotating persistent credentials; device-bound sessions; server-enforced expiry and account-owned revocation |
| A02 | High | `app/Core/validation.php`, authentication, recovery, account creation | Text normalization trimmed passwords; bcrypt truncation could make distinct long passwords equivalent | Validate raw strings; reject NUL/malformed values; enforce a 72-byte maximum for new passwords; exact verification first, legacy trimming fallback for old accounts |
| A03 | Medium | `portal.php`, `Auth/actions.php`, `Core/core.js` | Registration and profile password changes lacked confirmation, allowing accidental passwords | Matching confirmation required by browser and API; recovery retains confirmation |
| A04 | Medium | `assets/js/Core/core.js`, `api.php` | Fetch requests lacked a consistent timeout/response/error path; interrupted writes risked confusing repeat submissions | Unified JSON/error handling, 45-second ordinary / 690-second analysis deadline, disabled pending buttons, CSRF refresh without automatic write replay, explicit expired-session recovery |
| A05 | Medium | `assets/js/Services/services.js` | Global table selectors could attach service metadata to hidden modal rows | Scope service metadata queries to active content |
| A06 | Medium | `UI/interactions.js`, `Inventory/inventory.js` | Save/refresh lost search, filters and current page, interrupting list work | Per-account/per-route in-memory view state, clear/reset filter control, 10/25/50 page sizes |
| A07 | Medium | `Core/core.js`, `Settings/settings.js` | Browser timezone could shift database appointment/log times | Parse naive database times as Philippine time and format explicitly in `Asia/Manila` |
| A08 | Medium | `UI/interactions.js`, shared forms | Dismissing edited modals silently discarded input; pending forms could be closed | Keep editing / Discard changes decision, unload warning, pending-submit guard; failed saves preserve values |
| A09 | Medium | `app/Reports/filters.php`, `report.php`, Reports JS | No report date range; unexpected errors could expose details or produce broken output | Strict inclusive date range shared by print/CSV; invalid/reversed ranges rejected; generic error responses |
| A10 | High | `Users/routes.php`, authentication/recovery | Deactivation followed by reactivation could revive old sessions; concurrent password updates could overwrite a newer password | Revoke devices on deactivation/email/role changes, password change/reset; password update requires the previously verified hash; staff role rechecked under row lock |
| A11 | Low | `UI/account.js`, `portal.php`, shared UI CSS/JS | No Caps Lock hint, signed-in-device UI or clear mobile navigation close control | Account device manager, keyboard-state Caps Lock warning and focus-restoring mobile close button |
| A12 | High | `Core/core.js` shared `heading()` | User/account/blueprint titles inserted as HTML; stored markup could alter a page | Escape title and description centrally; persisted malicious account-name regression exercises real rendering |
| A13 | Medium | `Messages/messages.js` | Polling could overlap; a late send response could modify a different conversation/view | One refresh at a time; capture destination and ignore stale completion before updating active UI |
| A14 | Medium | `Inventory/routes.php`, Inventory/Events JS, product images | Archived quotation products could not be restored despite being referenced by planner errors | Staff/admin archive browser and named restore confirmation; existing product identity/history/photo retained |
| A15 | Medium | `Inventory/routes.php` | Large stock balance plus receipt could overflow database INT | Reject totals above 2,147,483,647 before updating; underflow checks/transactions retained |
| A16 | Medium | `api.php`, `blueprint-image.php`, `product-image.php` | Detailed exception logging could retain sensitive SQL parameters; image paths lacked consistent validation/error handling | Log exception category/code without payloads; allowlisted filenames; close session lock before image delivery; generic responses |
| A17 | High | Root `.htaccess` | Actual Apache request to `.git/HEAD` returned HTTP 200, exposing repository metadata | Deny all hidden path segments. `.git/HEAD` and `.git/config` now return 404; private app/config remain denied |
| A18 | Low | `.gitignore` | Running tests created Python bytecode churn | Ignore generated Python caches; preserve already tracked files at their committed bytes |

## C. Missing features addressed

- **Continue with Google:** implemented in the earlier part of this task for sign-in and account creation, using the same customer-account and device-session rules. It still needs real Google OAuth configuration.
- **Dark mode and UI refresh:** persisted light/dark choice, device preference option, tab synchronization, consistent surfaces, responsive navigation, loading/feedback states, reduced-motion support, accessible form controls and sortable/paginated tables. Reports remain white when printed.
- **Account security:** optional 30-day Remember me, current/other device list and revocation, idle/absolute session limits, confirmation on registration and password change, Caps Lock warning.
- **Daily operations:** restore archived products, inclusive report date ranges, retained list search/filter/page, clear filters/page sizes and unsaved-form protection.

Existing recovery, service warranties, stock history, scheduling conflict checks, private messaging, audit logs and planner costing were reviewed and regression-tested rather than replaced.

## D. Implementation and data handling

### Authentication

`auth_devices` is additive: indexed device ownership/expiry, nullable unique selector hash, current and previous validator hashes, rotation time, password version, coarse browser/OS label and activity timestamps. No raw remember token, password or full browser user-agent is stored in this table. Raw credentials exist only in an HttpOnly, SameSite=Lax cookie, Secure when HTTPS is detected; the cookie path follows the application's subdirectory.

Remembered credentials expire 30 days after issuance. Restoring a fresh PHP session rotates the validator. A 10-second previous-validator grace handles simultaneous requests without overwriting the rotated cookie; reuse outside that window revokes the device. Normal sessions expire after 30 minutes without activity or 12 hours total. Expiry ends that device's remembered credential too, so the next access requests sign-in. Background message reads alone do not extend inactivity. Restored/new sessions regenerate their session ID and CSRF token.

Logout removes the current device. Password change/reset removes all devices; a successful profile password change creates a fresh non-remembered current session. Account status/role/email changes revoke affected devices. Device operations enforce ownership and CSRF even for administrators. Existing valid PHP sessions without a device are registered lazily.

Legacy password compatibility: the exact raw password is checked first. A trimmed fallback accepts credentials matching hashes created by older releases that trimmed input. Thus padding an old normalized password can still authenticate; newly created passwords containing intentional spaces require those spaces. New password validation counts bytes (not Unicode characters), matching bcrypt's limit.

Google uses authorization code, one-use state, nonce and PKCE; tokens are exchanged only with Google's HTTPS endpoint. A verified Google subject is mapped uniquely; unverified email is rejected, and matching an existing local email does not silently link accounts. New Google accounts receive the customer role. Local cancellation/configuration errors return to the sign-in UI. Live consent has not been tested with real credentials.

### Other implementation details

Shared output escaping covers headings used by account and blueprint pages. API failures roll back transactions and return controlled JSON. Reports use prepared range parameters with an exclusive next-day upper bound, so the selected final date is fully included. Product restoration changes only its active flag; stock receipts/deductions retain the existing transaction and history model. No financial-accounting, deletion, reseeding or external notification workflow was introduced.

List state lasts within the current loaded application and is separated by account and route. It preserves filters and page after saves/navigation; it does not promise persistence across full reloads or preserve sort order. Appearance and sidebar preferences persist separately in browser storage. Failed modal saves retain input; only explicit discard, successful save or authentication expiry closes an edited form.

### Database application

Applied `migrate.php`'s additive migration routine to the configured local database on 2026-11-17. All 14 tables are available, including `auth_devices`. Before/after SHA-256 comparisons of complete existing table results matched: no existing table contents changed. The new authentication table is ready for application use. Other deployments still need the migration.

Tests create randomly named `ncpcs_test_*` databases and generated uploads. Their fixture changes, expiry simulations, stock extremes and cleanup are restricted to those databases/files. No real account, product, stock balance, service or message is used as a destructive test fixture. All external AI keys are overridden empty/disabled in integration runs, and mail goes to a loopback test sink.

## E. Features requiring configuration

| Feature | Current local state | Required action |
|---|---|---|
| Google sign-in | Client ID and secret empty | Create a Google Web application OAuth client; configure `google_client_id`, `google_client_secret`, canonical `app_url`; register exact callback `app_url + /google-auth.php`. See README Google setup. Complete live consent/restart/logout verification afterward. |
| Password-recovery delivery | `mail_from` empty | Set a real sender and canonical URL; configure the web server's PHP `mail()` SMTP relay/sendmail transport. Run a real inbox/spam/delivery check. No SMTP credentials were invented. |
| AI analysis | Ollama/OpenAI/Gemini key fields present; Meta key empty | Key presence is not proof of validity, quota or model access. Verify the selected provider/model and only send a real blueprint with its owner's consent. No live generation was run during this audit. |
| Existing deployments | New additive migration required | Run `C:\xampp\php\php.exe migrate.php` before serving updated authentication code. Fresh installer includes all migrations. |
| Hosted deployment | Local XAMPP HTTP environment | Enable HTTPS, restricted DB credentials, writable private upload/session directories and Apache `AllowOverride` support. If a TLS proxy is used, configure trusted HTTPS detection on the server rather than trusting arbitrary forwarded headers. |
| Production operations | No managed backup/monitoring service configured by this task | Define backup retention/restore procedure for MySQL and private images together; configure log rotation and monitoring. |

## F. Verification results

**Final result: ALL 320 CHECKS PASSED** from `python tests\integration.py --browser`, including HTTP/security assertions, Chrome UI checks and Google fixture checks. No JavaScript runtime exceptions were recorded. Both standalone PHP unit suites passed. Syntax validation passed for 50 PHP files and 14 application JavaScript files. Apache access checks and the local additive migration verification also passed.

| Workflow / check | Result | Evidence and limits |
|---|---|---|
| PHP and JavaScript syntax | PASS | 50 PHP files and 14 application JS files checked; final touched files rechecked |
| Planner arithmetic/schema/provider parsing | PASS | `tests/unit.php`; fixture responses, no live AI request |
| Google token/state validation | PASS | `tests/google_auth_unit.php`; rejects wrong claims/state/replay and malformed responses |
| Registration, passwords, recovery | PASS | Confirmation/type/length/whitespace cases, reset expiry/replay and isolated mail sink; browser reset and password masking |
| Persistent login and device security | PASS | Fresh-cookie-client restore/rotation, concurrent grace, stale replay, logout, ownership/CSRF, password/deactivation revocation, expired credentials, idle/absolute expiry and silent polling |
| Device manager, dirty forms, stored heading markup | PASS | Browser assertions against real fixture data and rendered controls |
| Role and ownership boundaries | PASS | Admin/staff/technician/customer checks, private blueprint access and messaging isolation |
| Inventory and image workflows | PASS | Product save, photos/replacement/removal, MIME rejection, rollback, stock limits, service parts, archive/restore, search/view controls |
| Services, schedule and warranty | PASS | Create/update, assigned access, overlapping appointment rejection, closed-job parts restriction and warranty preservation |
| Messaging and account/audit logs | PASS | Private exchanges/read indicators/unread clearing, account-group filtering and pagination; browser AJAX exchange |
| Reports | PASS | Print endpoints, role denial, CSV formula neutralization, invalid/reversed dates, final-day inclusion and matching CSV/print date filters |
| Light/dark, motion and responsive layouts | PASS | OS preference, saved override, tab sync, mobile widths, sidebar preference, contrast surfaces, reduced motion, white printed reports |
| List state and report/date controls | PASS | Browser save retains search/page; page-size/reset controls; CSV/print links carry identical ranges |
| JavaScript runtime exceptions | PASS | Full Chrome run records page errors across the exercised workflows |
| Apache private-path protection | PASS | Local HTTP: `.git/HEAD` and `.git/config` 404; `config.local.php` and `app/bootstrap.php` 403; homepage and portal 200 |
| Live Google account consent | REQUIRES CONFIGURATION | OAuth credentials empty; fixture authorization start/cancel tests do not substitute for live consent |
| Real recovery email delivery | REQUIRES CONFIGURATION | Sender/relay setup absent; loopback delivery tested only |
| Live external AI analysis | NOT TESTED | Saved analysis and provider responses are explicitly labeled fixtures; no real blueprint transmitted |
| Public HTTPS, proxy, backup restore, load and multi-browser matrix | NOT TESTED | Local Chrome/XAMPP audit cannot establish production TLS, resilience, capacity or every browser's behavior |

Any intermediate browser-test failures and their resolutions are retained in the work history: older dismissal tests needed the new explicit discard action, and a heading regression needed to scope its selector to the workspace because the hidden sign-in page also contains an h1; the device-dialog close selector was also scoped to distinguish its two valid close controls. The final result above reflects the final code/test run.

Reproduce: `C:\xampp\php\php.exe tests\unit.php`, `C:\xampp\php\php.exe tests\google_auth_unit.php`, and `python tests\integration.py --browser`. Browser checks require local Playwright/Chrome and MySQL. The run log is `tests/.artifacts/audit-run.log`; screenshots are in the same ignored directory. Tests do not exercise the actual Google token endpoint or configured AI credentials.

## G. Remaining recommendations

1. Complete Google and email setup plus real integration checks before advertising those integrations as operational. Credentials and provider-account permissions cannot be inferred from source code.
2. Add server-side pagination/index review as data grows. Operational lists currently load full collections and page them in-browser; messages show the latest 200, stock history the latest 200, with full records retained in MySQL. Reporting uses complete result sets. Load and retention requirements are needed before changing these contracts.
3. Consider MFA/email verification and a deliberate account-linking/unlinking recovery flow if required by the business. These alter identity/recovery policy and need product decisions; silent email-based Google linking remains deliberately disabled.
4. Consider durable message drafts and draft service forms. Current failed sends/saves retain input in the active view, while switching a conversation can lose a draft; modal close warns, but drafts are not synchronized or stored across browsers.
5. Run deployment-specific restore/concurrency/failure testing and browser/accessibility review with actual operational volume and assistive technologies. Keyboard labels/focus and responsive checks are implemented; no screen-reader certification or load benchmark is claimed.
6. Plan log/session/token retention, scheduled cleanup and audit indexes for operational volume. Expired device cleanup currently runs on sign-in; historical activity retention is unchanged.
7. Define the business need before adding purchase orders, suppliers, serial-number warranties, accounting/POS, push notifications or scheduled jobs. They are separate workflows, not safe assumptions for this inventory/service application.

## References used for authentication review

- [OWASP Session Management Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html): cookie/session expiry and lifecycle considerations.
- [PHP password_hash documentation](https://www.php.net/manual/en/function.password-hash.php): bcrypt's 72-byte input limit.
