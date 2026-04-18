# APP LAYER KNOWLEDGE BASE

## OVERVIEW
`app/` is the canonical application layer for this repo: bootstrap constants, feature modules, and shared runtime code.

## STRUCTURE
```text
app/
├── bootstrap.php              # PROJECT_ROOT + APP_ROOT
├── modules/
│   ├── qr/                    # QR upload/download + legacy handlers
│   ├── schedule/              # Jadwal UI + PDF APIs
│   ├── master_data/           # Kelas/mahasiswa/mata kuliah
│   ├── sessions/              # Saved session tree API
│   ├── grade_recap/           # XLSX rekap nilai
│   └── diagnostics/           # Internal diagnostics page
└── shared/
    ├── layout/                # Shared navbar/layout fragments
    ├── lib/                   # Shared DB/PDF business logic
    └── templates/             # Shared rendering templates
```

## ROUTING CONTRACT
- Web page modules are loaded by `index.php?route=<name>`.
- API modules are loaded by `index.php?api=<name>` (+`action` where relevant).
- Module files should assume root routing, not direct `/app/modules/...` URL access.

## CONVENTIONS
- Always include `app/bootstrap.php` (directly or transitively) before using root-relative paths.
- Use `PROJECT_ROOT` for file includes (`vendor/`, `phpqrcode/`, templates, sqlite file).
- Keep API JSON shape stable: `success`, `message`, plus domain payload keys.

## ANTI-PATTERNS
- Do not reintroduce root-level page/API entry files.
- Do not use CWD-dependent relative includes in APIs.
- Do not bypass shared lib functions by re-implementing DB/schema logic per module.

## QUICK CHECKS
```bash
php -l app/bootstrap.php
php -l app/modules/schedule/api/generate_pdf_api.php
php -l app/modules/sessions/api/api_sessions.php
php -l app/shared/lib/database.php
```
