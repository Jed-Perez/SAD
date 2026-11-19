# TechTrack — NCPCS Inventory and Service Management System

A working PHP 8.2 / MySQL (MariaDB-compatible) application for **Nasugbu Computer Parts and CCTV Services**, with locally bundled Bootstrap 5.3.3 and AJAX through the browser Fetch API. No Composer or Node build is required.

## Run on this computer

1. Start **Apache** and **MySQL** in the XAMPP control panel.
2. Open **http://localhost/SAD/** for the public landing page. Select **Sign in** to open **http://localhost/SAD/portal.php**.
3. Sign in using the administrator credentials printed by the installer and provided in the delivery message. Open your profile in the lower-left sidebar to change the temporary password. Customers can select **Create account** from the landing page.

The database `ncpcs_isms` has already been initialized in this workspace. The eight opening products, quantities, and prices are **sample academic-project data**. No real customer records were imported. Replace prices and reconcile stock before using the system operationally.

For a fresh installation:

```powershell
cd C:\xampp\htdocs\SAD
C:\xampp\php\php.exe install.php
```

The installer creates the database and tables, seeds the equipment catalog and rates, and prints a randomly generated administrator password. Re-running it preserves existing records and does not reset passwords. Default database connection: `127.0.0.1:3306`, user `root`, blank password. Override database credentials in `config.local.php` using the keys in `config.example.php`, or use `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD` environment variables. Explicit environment variables take precedence over local configuration; this also keeps automated tests isolated from your normal database and API key.

For a PHP development server, use the protective router:

```powershell
C:\xampp\php\php.exe -S 127.0.0.1:8080 router.php
```

Then open http://127.0.0.1:8080/. Do not run the built-in server without `router.php`: PHP's development server does not honor Apache `.htaccess` files.

## Accounts and access

| Role | Capabilities |
| --- | --- |
| Administrator | All operational modules, account management, quotation rates, and audit log |
| Office staff | Inventory, customer profiles, services and scheduling, customer messages, blueprint review, reports |
| Technician | Assigned services and schedule, work notes/status updates, parts usage on assigned open jobs |
| Customer | Self-registration, own service requests/history/warranties, private messages with the office, own blueprints and estimates |

Customers register on the sign-in page. Administrators create staff and technician accounts under **Team & accounts**. Staff can create and edit customer accounts only. Account holders change their own passwords under Profile. Deactivation immediately prevents authenticated access. Accounts with service, blueprint, or conversation history retain their role to protect history and ownership.

## Workflows

### Sign-in, devices, and appearance

Registration requires the password twice. **Remember me** keeps a browser sign-in credential for up to 30 days; it is revoked on logout, password changes/resets, account deactivation, and device removal. PHP sessions expire after 30 minutes of inactivity or 12 hours, requiring sign-in again. Silent message polling does not extend inactivity. Use **Profile > Manage signed-in devices** to remove a device or sign out all other devices. New passwords preserve spaces and must be 10-72 bytes; multi-byte characters use more than one byte.

Use the sun/moon button on the homepage, sign-in page, workspace, and reports to change light/dark mode. Profile also offers **Use device setting**. Preferences persist on this browser and synchronize between tabs; printed reports remain white. Edited modal forms warn before discarding unsaved changes.

The audit adds `database/migrations/004_auth_devices.sql`; run `C:\xampp\php\php.exe migrate.php` when updating another installation. See [the complete audit and verification report](docs/SYSTEM_AUDIT.md).

### Password recovery

For Gmail on Hostinger, follow [GMAIL_SETUP.md](GMAIL_SETUP.md). Password-reset email supports authenticated SMTP with TLS using the bundled PHPMailer library. Set `mail_transport` to `smtp` and configure the `smtp_*` entries in your private `config.local.php`. `MAIL_TRANSPORT`, `SMTP_HOST`, `SMTP_PORT`, `SMTP_ENCRYPTION`, `SMTP_USERNAME`, and `SMTP_PASSWORD` environment variables override these entries. PHP mail() remains the default for existing installations.

For Gmail on Hostinger, follow [GMAIL_SETUP.md](GMAIL_SETUP.md). Password-reset email supports authenticated SMTP with TLS using the bundled PHPMailer library. Set `mail_transport` to `smtp` and configure the `smtp_*` entries in your private `config.local.php`. `MAIL_TRANSPORT`, `SMTP_HOST`, `SMTP_PORT`, `SMTP_ENCRYPTION`, `SMTP_USERNAME`, and `SMTP_PASSWORD` environment variables override these entries. PHP mail() remains the default for existing installations.

The sign-in page includes **Show / Hide** password controls and **Forgot password?**. Reset links expire after 30 minutes, work once, and require the new password twice. Resetting a password signs out existing sessions. Existing sessions created before this update must sign in again.

For an existing database, run `C:\xampp\php\php.exe migrate.php` once. Configure `app_url` (the public base URL, such as `https://example.com/SAD`) and `mail_from` (your sender email) in `config.local.php`, or use `APP_URL` and `MAIL_FROM`. Configure PHP's `mail()` transport in the PHP configuration used by your web server; XAMPP needs a working SMTP relay/sendmail setup. Restart Apache after changing `php.ini`. Never use a request's Host header as the reset URL.

Without these settings, recovery shows an explicit setup message. With mail configured, the page gives the same response for unknown and active accounts; it never displays reset tokens. If the mail server rejects delivery, the failure is logged without the reset link. Accounts must use a real, reachable email address to receive recovery messages.

- **Inventory:** add/edit products, set reorder thresholds, receive/issue stock with reasons, view movement history, archive zero-stock products, and restore them from Archived products. Opening stock is entered with Stock ±. Negative balances and fractional units are rejected.
- **Customers:** profiles, contact information, addresses, and linked service histories.
- **Services:** installation, repair, and maintenance requests; office assigns a technician, appointment and duration; overlapping active bookings for a technician are rejected. All appointments use `Asia/Manila`. Work notes and status changes produce a history entry. Parts usage atomically deducts inventory. Closed jobs reject new parts deductions.
- **Warranties:** office staff record a warranty expiry per service, visible in the customer's history. This is service-level warranty tracking, not individual device serial-number warranty registration.
- **Messages:** private customer-to-office threads, sent/read indicators, AJAX refresh every five seconds while the Messages page is visible, plus unread badges in the sidebar and header throughout the workspace. Staff and admins share the office inbox. The newest 200 messages appear in each thread; all messages remain in MySQL.
- **Reports:** inventory, low stock, service history, technician schedules, and stock movement reports. Download CSV for Excel or print and choose **Save as PDF**. Service, schedule, and movement reports support inclusive date ranges shared by CSV and print. Inventory reports are current snapshots.
- **User logs:** administrators can search and page through sign-ins, failed sign-ins, sign-outs, registration, profile changes, and password events. New events are recorded from this update onward; existing sign-in history is retained.
- **Audit logs:** a dedicated administrator sidebar page groups activity by user, with record counts and latest activity. Select a user to search and page through their history; User logs uses the same account grouping.
- **Audit:** account sign-ins, product changes, stock movements, service updates, quotation-rate changes, and AI request/review actions.

## Product pictures and the visual catalog

Open **Inventory** for the photo card catalog. Use the **Card view / Table view** buttons to change the layout; search and category filters work in both views. The summary shows active products, low-stock products, and stock value calculated from current unit prices.

Under **Add product** or **Edit**, select a **Product picture** and review its preview before saving. Supported files: JPG, PNG, and WebP, up to **2 MB / 12 megapixels**. Selecting another picture replaces the old upload when the save succeeds. **Remove current picture** removes the custom upload. Products without a picture show a placeholder; the eight seeded SKUs use clearly labeled illustrative images until you upload your own product photo.

Photos are stored in `storage/products/`, with their product association in MySQL's `product_images` table. Uploaded pictures are served through `product-image.php` to authorized admin, staff, and technician accounts. Only admin and staff can change pictures. Invalid uploads and failed product saves preserve the existing picture and product details.

For an existing installation, apply the additive database update once:

```powershell
C:\xampp\php\php.exe migrate.php
```

It is safe to rerun and preserves existing accounts, products, stock, and other records. Fresh installations apply it automatically. Back up **both the database and `storage/products/`** to retain uploaded pictures.

Visual files are organized as follows:

- `assets/css/inventory.css`: photo cards, table thumbnails, filters, and upload editor.
- `assets/css/showcase.css`: photographic dashboard and landing-page presentation.
- `assets/js/Inventory/inventory.js`: inventory views, search, forms, and picture previews.
- `app/Inventory/actions.php`: atomic product and picture saving.
- `app/Inventory/images.php`: upload validation, image paths, and illustrative image mapping.
- `database/migrations/001_product_images.sql`: additive picture table.
- `assets/images/`: locally bundled generated imagery; see [image assets and prompts](assets/images/README.md).

## Appearance and interface

Use the sun/moon button on the home page, sign-in form, workspace header, or report to switch between light and dark mode. Your choice persists in this browser and synchronizes across its open tabs. **Profile > Appearance** (or **Settings > Appearance** for administrators) also offers **Use device setting**. Without an explicit choice, the app follows your operating system. Printed reports use white backgrounds in either theme.

The workspace includes a persistent collapsible desktop sidebar, existing mobile navigation, animated loading skeletons, subtle page/card transitions, accessible submit progress, inline form errors, textarea character counts, password visibility controls, and nonblocking success/error notifications. Reduced-motion preferences disable animation.

Click a table's column heading to sort the loaded records; tables with more than ten rows provide Previous/Next controls. Existing search and status/category filters apply before pagination. Larger modal tables have their own search. Audit/user logs retain their existing server-side search, pagination, and newest-first ordering. Dashboard service activity uses the current database totals; select a status to open filtered service requests. Existing forms and confirmations still submit to the same PHP endpoints.

Shared files: `assets/js/UI/theme.js` applies the preference before first paint; `assets/css/ui.css` defines theme surfaces and interactions; `assets/js/UI/interactions.js` enhances the existing AJAX views. No additional frontend library is required. The optional browser suite covers theme persistence, cross-tab updates, reduced motion, sidebar preferences, sorting/pagination, retained form values after errors, responsive layouts, and print styling. Screenshots are saved to `tests/.artifacts/redesign-*.png`.

## Continue with Google

The sign-up and sign-in forms include **Continue with Google**. A first Google sign-in creates a customer account; later visits use the same Google identity. Existing email/password accounts continue to use their password (or password recovery); matching email addresses are never automatically linked. Inactive accounts cannot sign in. Google customers can fill in their phone and address in Profile and use email password recovery if they want to set a local password.

1. In [Google Cloud Console](https://console.cloud.google.com/auth/clients), configure the Google Auth Platform branding/consent screen and audience. While the app is in testing, add your Google account as a test user where required.
2. Create an OAuth client of type **Web application**. Add this exact **Authorized redirect URI** for XAMPP: `http://localhost/SAD/google-auth.php`. Use your site's HTTPS URL for production. The browser must open the site using the same hostname and base path as `app_url` (localhost and 127.0.0.1 have different sessions).
3. Fill in these entries in the existing ignored `config.local.php`, preserving your other settings:

   ```php
   'app_url' => 'http://localhost/SAD',
   'google_client_id' => 'YOUR_CLIENT_ID.apps.googleusercontent.com',
   'google_client_secret' => 'YOUR_CLIENT_SECRET',
   ```

   Alternatively set `APP_URL`, `GOOGLE_CLIENT_ID`, and `GOOGLE_CLIENT_SECRET` in the server environment. Explicit environment values override local settings. These are OAuth credentials, separate from the Gemini API key. Keep the secret on the server.
4. Run `C:\xampp\php\php.exe migrate.php` for an existing installation. New installs include the additive `google_accounts` table automatically.
5. Enable PHP cURL with a trusted CA certificate bundle, restart Apache if its configuration changed, then open `http://localhost/SAD/portal.php?register=1` and choose **Continue with Google**.

The button is disabled until credentials, a valid base URL and cURL are available. Google receives only an authentication request for `openid email profile`; the application does not request offline access or save Google access/refresh tokens. State, nonce, a ten-minute attempt expiry and PKCE bind the response to the originating browser session. The server exchanges the code directly with Google's fixed HTTPS endpoint and validates the returned identity claims, following [Google's documented server flow](https://developers.google.com/identity/openid-connect/openid-connect). It does not accept identity tokens from the browser. Live sign-in requires real Google credentials and user consent; automated tests use synthetic identities in an isolated test database without contacting Google.

## Enable AI blueprint analysis

### Ollama Cloud (online)

Ollama calls **https://ollama.com/api/chat** directly; no local Ollama installation or model download is needed. Create an API key at https://ollama.com/settings/keys and set `OLLAMA_API_KEY` in the server environment, or put it in the existing `ollama_api_key` entry in `config.local.php`. Never put the key in browser JavaScript. Restart Apache after changing environment variables.

```php
'ollama_enabled' => true,
'ollama_api_key' => 'YOUR_OLLAMA_API_KEY',
'ollama_url' => 'https://ollama.com',
'ollama_model' => 'gemma4:31b',
'ollama_timeout' => 300,
'ai_default_provider' => 'ollama',
```

The provider appears only when enabled and a key is configured. A saved key does not prove connectivity or account quota. Select **Ollama Cloud** when analyzing a plan and consent to sending the image and preferences online. Automatic uses Ollama Cloud alone when configured, without switching providers on failure. The endpoint is restricted to Ollama's HTTPS host and redirects are disabled to protect the API key.

Ollama Cloud does not currently support structured-output schemas in the API's `format` field. The prompt requests JSON and PHP validates the returned plan before saving or calculating prices. Invalid or incomplete output produces an error. Available models: https://ollama.com/api/tags. Documentation: https://docs.ollama.com/cloud and https://docs.ollama.com/capabilities/structured-outputs.

### Optional cloud providers

The CCTV planner also supports **OpenAI**, **Google Gemini**, and **Meta Model API**. Add a key for each provider you want to use in `config.local.php`. Each provider needs its own key; several keys from one provider do not create additional providers. Example:

```php
<?php
return [
    'openai_api_key' => 'YOUR_OPENAI_KEY',
    'openai_model' => 'gpt-4o-mini',
    'gemini_api_key' => 'YOUR_GEMINI_KEY',
    'gemini_model' => 'gemini-3.8-flash',
    'meta_api_key' => 'YOUR_META_KEY',
    'meta_api_keys' => [], // Optional backup Meta keys, in order.
    'meta_model' => 'muse-spark-1.3',
];
```

You can also set `OPENAI_API_KEY`, `OPENAI_MODEL`, `GEMINI_API_KEY`, `GEMINI_MODEL`, `META_API_KEY`, `META_API_KEYS` (comma-separated backup keys), and `META_MODEL` as environment variables. Explicit environment values override `config.local.php`. API keys stay on the PHP server and are never returned to the browser. Multiple Meta keys count as one provider; the next key is tried only when Meta returns an authentication, permission, or quota error. The Settings page shows which providers are configured and the model names. A configured key may still lack billing, quota, or model access; a real analysis verifies connectivity.

Temporary provider HTTP failures (500, 502, 503, or 504) receive one retry after a short delay. Authentication, permission, and quota failures are not retried by the HTTP layer. Cloud provider attempts share a 150-second request budget. Ollama Cloud uses its configured timeout. The planner reports temporary outages, timeouts, invalid keys, model access problems, and quota limits separately. A successful model-access check does not guarantee that generation capacity is currently available.

When analyzing, choose **OpenAI**, **Google Gemini**, **Meta Model API**, or **Automatic**. With Ollama disabled, Automatic tries configured cloud providers in this order: OpenAI, Gemini, Meta. It falls back only after a connection, key/quota/model, or incomplete-response error. If only one provider is configured, Automatic uses that provider. An unreadable or rejected blueprint does not trigger another provider call. Automatic may send the blueprint to multiple providers and incur charges from each. The consent form explains this before analysis. The saved plan records which provider and model produced it.

OpenAI uses the [Responses API image input](https://developers.openai.com/api/docs/guides/images-vision) and [Structured Outputs](https://developers.openai.com/api/docs/guides/structured-outputs). Gemini uses the [Generate Content image input](https://ai.google.dev/gemini-api/docs/generate-content/image-understanding) and [Structured Outputs](https://ai.google.dev/gemini-api/docs/generate-content/structured-output) APIs. Meta uses the [Model API Responses endpoint](https://dev.meta.ai/docs/api-reference) with [image input](https://dev.meta.ai/docs/image-understanding) and [structured output](https://dev.meta.ai/docs/structured-output). PHP sends a base64 image and a JSON schema, then validates returned fields and coordinates before saving. OpenAI and Meta requests set `store` to `false`. Model refusals, unreadable plans, connection failures, invalid responses, and quota failures produce explicit errors. No placeholder recommendation is generated when no provider is configured.

1. Customer opens **CCTV planner -> Upload blueprint**.
2. Upload JPG, PNG, or WebP, maximum **2 MB / 36 megapixels**. Convert PDF floor plans to images first. Each floor is a separate plan and estimate.
3. Enter an optional budget and coverage preferences. Uploading saves the file locally, without calling any AI provider.
4. Select **Analyze blueprint**, choose a provider, confirm permission to send the image and preferences online, and wait for the result. Automatic may take longer because it can try multiple providers.
5. View numbered suggested positions and directions on the image, the provider used, camera types and reasons, and itemized PHP costs for equipment, materials, installation labor, setup labor, and contingency.
6. Office staff review the proposal and record required site checks. Reanalysis resets the review. Prices are saved with the estimate; later catalog edits do not change past estimates.

AI proposes **1-8 PoE/IP cameras per plan**. Image position estimates are approximate. Shaded wedges show direction, not measured fields of view or guaranteed coverage. Entrances, approaches, garages, and shared circulation areas are prioritized; private rooms and neighboring private property are excluded in the analysis prompt. A technician must verify the actual layout, mounting locations, blind spots, power, cabling, lighting, privacy, and final price. Larger systems and multiple-floor combined quotations need a custom survey.

### Pricing

The model does not invent prices. PHP uses these active product SKUs:

| SKU | Use |
| --- | --- |
| `CAM-OUT-4MP` | Outdoor 4MP PoE camera |
| `CAM-IN-4MP` | Indoor 4MP PoE camera |
| `NVR-8-POE` | One eight-channel PoE NVR |
| `HDD-1TB-SUR` | One 1TB surveillance drive allowance |
| `CAT6-M` | Cable by meter |
| `INSTALL-KIT` | Mounting/connector kit per camera |

Keep these SKUs when editing catalog prices. Missing/archived quotation products prevent a misleading partial estimate. Insufficient stock is flagged; an estimate does not reserve or deduct stock. Record actual installed parts against the service job.

Editable Settings defaults: PHP 850 installation labor per camera, PHP 1,500 system setup labor, 20 meters cable per camera, and 10% contingency. These are **illustrative rates, not verified local market prices**. The estimate lists allowances and exclusions; taxes, civil works, scaffolding, internet fees, and electrical upgrades are not separately calculated. Storage retention duration requires bitrate and recording-mode calculations during the survey. A budget is a comparison target, not a guarantee that the recommended design fits it.

## Project structure

```text
index.php                  Public landing page
portal.php                 Sign-in and application shell
api.php                    Small AJAX action dispatcher and error handling
app/bootstrap.php          Loads shared backend helpers
app/Database/connection.php PDO connection and prepared-query helper
app/Core/                  Session/runtime, HTTP responses, input validation
app/Auth/                  Login, registration, profile, session helpers
app/Dashboard/             Dashboard data
app/Inventory/             Products and stock movements
app/Users/                 Account management
app/Services/              Service requests and access checks
app/Messages/              Customer-office conversations
app/Settings/              Rates and audit log
app/Planner/               Blueprint validation, AI providers, costing, actions
app/planner.php            Compatibility loader for planner tests/routes
blueprint-image.php        Authorized private-image delivery
report.php                 CSV and printable reports
assets/js/<Feature>/        Browser code grouped by matching feature
assets/app.css             Responsive desktop/mobile styling
assets/css/theme.css       Shared colors, surfaces, typography, and responsive polish
assets/css/sidebar.css     Independent sidebar scrolling
assets/css/landing.css     Public landing-page layout and styles
assets/js/Landing/          Landing-page mobile navigation and old-link redirects
assets/vendor/              Locally bundled Bootstrap CSS and JavaScript
database/schema.sql        InnoDB schema and relationships
install.php                CLI-only installer
storage/blueprints/        Private uploaded image files
storage/sessions/          Private PHP sessions
config.local.php           Ignored server credentials and model configuration
tests/                     Unit, HTTP integration, and optional browser checks
```

See [docs/PROJECT_STRUCTURE.md](docs/PROJECT_STRUCTURE.md) for the file map and where to fix common issues. Keep public entry points at the project root; put new business logic in the relevant `app/<Feature>/` folder and browser behavior in `assets/js/<Feature>/`.

## Verification

```powershell
C:\xampp\php\php.exe tests\unit.php
C:\xampp\php\php.exe tests\google_auth_unit.php
python tests\integration.py
python tests\integration.py --browser
C:\xampp\php\php.exe tests\verify_ai_keys.php
```

HTTP tests require MySQL and permission to use XAMPP's upload temporary directory. They create an isolated, randomly named `ncpcs_test_*` database, start a temporary PHP server, and clean up their database and uploaded fixtures. They do not modify `ncpcs_isms` or send configured API keys to any provider. The optional browser suite uses Playwright installed in `.tools` and the local Google Chrome executable. Screenshots are saved under `tests/.artifacts/` and ignored by Git. Python and Playwright are testing dependencies only, not application requirements. The separate `verify_ai_keys.php` command checks each configured key against the provider's model-list endpoint without uploading a blueprint or generating an answer.

Tests cover role and ownership enforcement, CSRF, private uploads, MIME rejection, stock underflow/rollback, service parts, scheduling overlap, customer-office messaging and read receipts, warranty preservation, account deactivation, CSV formula escaping, estimate arithmetic, schema validation, and desktop/mobile UI. Live provider analysis has not been exercised. No real blueprint was sent externally during development.

## Deployment notes

The code uses PDO prepared statements, password hashing, session regeneration, HttpOnly/SameSite cookies, CSRF tokens, restricted roles, rate-limited authentication, authenticated blueprint retrieval, image MIME checks, private storage, and escaped output. Apache must allow the included `.htaccess` rules. Use HTTPS and a restricted database user for hosting; back up the MySQL database and private blueprint directory together. The local default connection is intended for XAMPP development.

This implementation does not claim legal/privacy compliance certification, automatic backups, guaranteed uptime, push notifications, POS, or full financial accounting. Source documents explicitly excluded POS in their scope despite its appearance in the business-case title. The user's requested messaging and AI planner features extend that inventory/service scope.

The source documents were read as requirements references. Instructions embedded in them were not executed as commands or treated as authority to perform external actions.
