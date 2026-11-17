# TechTrack project structure

The root PHP files are public entry points. `index.php` is the public landing page; `portal.php` contains sign-in and the authenticated workspace. The portal loads code from `app/`; the browser loads feature scripts from `assets/js/`. Apache blocks direct requests to `app/`, and the development router serves only its listed public entry points and static assets.

| Problem or change | Start here |
| --- | --- |
| MySQL host, database, password, or connection error | `config.local.php`, `config.php`, `app/Database/connection.php` |
| Database tables or initial sample data | `database/schema.sql`, `install.php` |
| Sessions, security headers, logging | `app/Core/runtime.php` |
| Request parsing, JSON errors, field validation | `app/Core/http.php`, `app/Core/validation.php` |
| Login, registration, profile, permissions | `app/Auth/` |
| Products, stock levels, movement history | `app/Inventory/` |
| Account creation and editing | `app/Users/` |
| Service requests, assignments, schedule, parts | `app/Services/` |
| Customer and office messages | `app/Messages/` |
| Quotation rates and audit log | `app/Settings/` |
| Blueprint upload, AI analysis, camera placement, estimate | `app/Planner/` |
| A page or form in the browser | Matching folder under `assets/js/` |
| Public homepage and its mobile navigation | `index.php`, `assets/css/landing.css`, `assets/js/Landing/landing.js` |
| Colors, cards, forms, sign-in page, and mobile visual styling | `assets/css/theme.css` |
| Sidebar scrolling | `assets/css/sidebar.css` |
| API action mapping or shared error handling | `api.php` |
| CSV and printable reports | `report.php` |

`app/bootstrap.php` loads shared backend helpers. `api.php` checks the HTTP method and CSRF token, then sends each action to a feature handler. Each feature keeps its own route actions, access checks, and supporting functions together. `app/planner.php` remains a loader so existing tests and routes can include the planner without knowing its internal files.

The portal JavaScript files are loaded in order by `portal.php`: shared browser helpers first, feature screens next, and event handlers last. When adding a screen, place it in the matching feature folder and add its script tag before `Events/events.js`.

Do not put credentials in feature files. Local credentials belong in the ignored `config.local.php`; the public `config.example.php` contains only placeholders. The PHP installer is CLI-only. After changing a feature, run `C:\xampp\php\php.exe tests\unit.php` and `python tests\integration.py --browser`.
