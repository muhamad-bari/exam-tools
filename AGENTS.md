# AGENTS.md

Operational guidance for coding agents in `exam-tools`.

## 1) Project Overview

- Stack: plain PHP + HTML + CSS + vanilla JS.
- Package manager: Composer.
- PDF library: `dompdf/dompdf`.
- QR library: local `phpqrcode/qrlib.php`.
- Data store: SQLite (`database.sqlite`) for session data.
- Target PHP version (`composer.json`): `8.0.30`.
- Main pages: `index.php` (QR generator), `jadwal.php` (schedule generator).

## 2) Cursor / Copilot Rule Files

Checked paths:

- `.cursorrules`
- `.cursor/rules/`
- `.github/copilot-instructions.md`

Current status: none exist in this repository.
If added later, treat them as higher-priority local instructions.

## 3) Setup and Local Run

Run from repo root.

```bash
composer install
php -S 127.0.0.1:8000
```

Open:

- `http://127.0.0.1:8000/index.php`
- `http://127.0.0.1:8000/jadwal.php`
- `http://127.0.0.1:8000/test_api.php` (manual diagnostics)

Notes:

- XAMPP/Apache is also valid.
- MySQL is not required for current features; sessions use SQLite.

## 4) Build / Lint / Test Commands

There is no formal build pipeline and no PHPUnit suite.
Use syntax checks + smoke tests.

### Preferred single test

```bash
php -l api_sessions.php
```

Use the same command for any changed PHP file.

### Lint all PHP files (Bash)

```bash
for f in $(find . -name "*.php"); do php -l "$f" || exit 1; done
```

### Lint all PHP files (PowerShell)

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

### Composer sanity check

```bash
composer validate
```

### API smoke tests

```bash
curl "http://127.0.0.1:8000/api_sessions.php?action=list"
curl -i "http://127.0.0.1:8000/generate_pdf_api.php"
```

Expected: non-POST request to `generate_pdf_api.php` returns JSON error.

## 5) Single-Test Guidance for Agents

When no unit tests exist, do one focused verification:

1. `php -l <changed-file.php>`
2. One relevant endpoint request (`curl`) for backend changes
3. One browser flow for UI-only changes

Minimum for backend edits:

- Run `php -l` on each changed backend file.
- Run one endpoint request for the changed code path.

## 6) Code Style and Conventions

Follow existing style in touched files. Avoid unrelated reformatting.

### 6.1 Formatting

- 4-space indentation in PHP.
- Opening braces on same line (`if (...) {`).
- Preserve file-local `elseif`/`else if` style.
- Prefer short arrays `[]`.
- Do not add `declare(strict_types=1);` unless requested globally.

### 6.2 Imports and Includes

- Put `require` / `require_once` near file top.
- Prefer `__DIR__` for local includes.
- Group `use` statements near top-level.
- Avoid duplicate includes in one execution path.

### 6.3 Naming

- PHP variables/functions: `camelCase`.
- API action keys/params: snake_case (`create_folder`, `folder_id`).
- JS functions: `camelCase`.
- Keep existing public API parameter names stable.

### 6.4 Validation and Types

- Validate request shape with `isset`, `is_array`, null/empty checks.
- Normalize mixed values (`'0'`, `0`, `''`, `null`) before branching.
- Cast numeric IDs with `intval()` when integer semantics are needed.
- Guard required fields before processing files/DB writes.

### 6.5 Error Handling

- Set JSON `Content-Type` early in API endpoints.
- Use top-level `try/catch` for handlers.
- Return consistent error JSON (`success` + `message`).
- Set HTTP status for failures (`400`/`500` as appropriate).
- Prevent warnings/notices from leaking into JSON output.
- Use output buffering carefully around PDF/QR generation.

### 6.6 Database and Persistence

- SQLite is canonical (`sqlite:database.sqlite`).
- Use prepared statements for reads/writes.
- Keep schema setup idempotent (`CREATE TABLE IF NOT EXISTS`, guarded `ALTER`).
- Do not add MySQL-specific logic unless migration is requested.

### 6.7 Security and Output Safety

- Escape HTML output with `htmlspecialchars()`.
- Sanitize uploaded filenames.
- Validate upload MIME/type + size on client and server.
- Parse CSV defensively; skip malformed/incomplete rows.

### 6.8 Frontend Conventions

- Keep vanilla JS (no framework introduction).
- Reuse existing variables/tokens in `style.css` when possible.
- Preserve existing UX patterns (toasts, drag-drop, session tree).

## 7) File and Directory Notes

- Generated/ignored: `uploads/`, `results/`, `vendor/`.
- Do not edit `vendor/` directly.
- Avoid committing generated PDFs/temp artifacts unless requested.

## 8) Change Discipline

- Make minimal, targeted changes.
- Keep API contracts backward-compatible.
- Avoid large inline JS rewrites unless necessary.
- New API actions should follow existing `action` routing style.

## 9) Quick Verification Matrix

- `api_sessions.php` changes:
  - `php -l api_sessions.php`
  - `curl "http://127.0.0.1:8000/api_sessions.php?action=list"`
- `generate_pdf_api.php` changes:
  - `php -l generate_pdf_api.php`
  - Manual run from `jadwal.php` upload + generate flow
- `index.php` or `jadwal.php` changes:
  - `php -l <changed-file>.php`
  - Browser verification of affected interaction

Update this file when tooling, CI, tests, or local agent rules are added.
