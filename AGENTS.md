# PROJECT KNOWLEDGE BASE

**Generated:** 2026-04-18 07:18:12 (Asia/Jakarta)
**Commit:** a3e0f5e
**Branch:** master

## OVERVIEW
Exam tools web app in plain PHP + HTML/CSS/vanilla JS with **single-entry root routing**.
Root only keeps `index.php`; page/API flows are dispatched by query route:
- Web pages: `index.php?route=<name>`
- APIs: `index.php?api=<name>&action=<snake_case>` (for action-based APIs)

## STRUCTURE
```text
exam-tools/
├── index.php                    # Single web+API entry router
├── app/
│   ├── bootstrap.php            # PROJECT_ROOT + APP_ROOT constants
│   ├── modules/                 # Feature modules (web/api/legacy)
│   └── shared/                  # Shared lib/layout/templates
├── assets/
│   ├── css/{core,components}/   # Shared frontend styles
│   └── js/shared/               # Shared JS helpers
├── lib/                         # Compatibility wrappers to app/shared/lib
├── phpqrcode/                   # Vendored QR library (do not refactor broadly)
├── pdf/                         # Vendored FPDF bundle (do not refactor broadly)
├── uploads/                     # Runtime input artifacts
├── results/                     # Runtime output artifacts
└── vendor/                      # Composer dependencies (do not edit)
```

## WHERE TO LOOK
| Task | Location | Notes |
|---|---|---|
| Router/web+api dispatch | `index.php` | Defines `route` and `api` maps; 404 behavior lives here |
| Schedule generator UI | `app/modules/schedule/web/jadwal.php` | Heavy inline JS + sessions + master-data integration |
| Master data UI/API | `app/modules/master_data/{web,api}` | Class/student/subject lifecycle |
| Session tree API | `app/modules/sessions/api/api_sessions.php` | `action` contract compatibility-sensitive |
| Grade recap upload/API | `app/modules/grade_recap/{web,api}` | XLSX parse + normalization |
| QR upload/download flow | `app/modules/qr/{web,api}` + `app/shared/lib/pdf_helper.php` | CSV parsing + PDF output |
| Shared DB schema/helpers | `app/shared/lib/database.php` | SQLite schema and helper surface used across APIs |

## CODE MAP
| Symbol | Type | Location | Role |
|---|---|---|---|
| `getDatabaseConnection` | function | `app/shared/lib/database.php` | Canonical SQLite connector + schema bootstrap |
| `initializeDatabaseSchema` | function | `app/shared/lib/database.php` | Idempotent table/index migration path |
| `generatePDF` | function | `app/shared/lib/pdf_helper.php` | CSV-to-PDF helper for QR flow |
| `renderCard` | function | `app/modules/schedule/api/api_generate_jadwal.php` | Card HTML renderer per student |

## CONVENTIONS (PROJECT-SPECIFIC)
- API responses keep `success` and `message` shape for failures.
- Action-based API keys are compatibility-sensitive (`action`, `folder_id`, etc.).
- Use `PROJECT_ROOT` for include/path safety from module files.
- SQLite changes are additive/idempotent (`CREATE TABLE IF NOT EXISTS`, guarded `ALTER`).
- Composer platform currently pins PHP `8.4.20`.

## ANTI-PATTERNS (THIS PROJECT)
- Do not re-introduce extra root PHP entry files (root should stay single-entry).
- Do not leak PHP notices/warnings into JSON API output.
- Do not rename public query params or action names casually.
- Do not edit `vendor/` or broad-refactor vendored `phpqrcode/` and `pdf/`.
- Do not commit runtime artifacts from `uploads/` and `results/` unless requested.

## COMMANDS
```bash
composer install
php -S 127.0.0.1:8000
php -l <changed-file.php>
composer validate
curl "http://127.0.0.1:8000/index.php?api=sessions&action=list"
curl "http://127.0.0.1:8000/index.php?api=master_data&action=list_all"
curl -i "http://127.0.0.1:8000/index.php?api=generate_pdf"
```

## HIERARCHY
- `./app/AGENTS.md` — modular app architecture (modules/shared/bootstrap).
- `./assets/AGENTS.md` — frontend asset organization conventions.
- `./lib/AGENTS.md` — compatibility wrapper boundaries (`lib/*` -> `app/shared/lib/*`).
- `./phpqrcode/AGENTS.md` — vendored QR boundaries.
- `./pdf/AGENTS.md` — vendored FPDF boundaries.

## NOTES
- LSP symbol indexing is limited in this workspace; rely on grep/ast/read.
- `app/shared/views/` currently exists but active navbar lives in `app/shared/layout/navbar.php`.
