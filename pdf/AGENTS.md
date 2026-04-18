# PDF DIRECTORY GUIDE

## OVERVIEW
Local vendored FPDF source/assets (`fpdf.php`, `font/`, `tutorial/`, `makefont/`). Treat as third-party bundle.

## STRUCTURE
```text
pdf/
├── fpdf.php        # Core FPDF engine
├── font/           # Font definition files
├── tutorial/       # Upstream tutorial examples
├── makefont/       # Font tooling scripts
└── doc/            # Upstream docs
```

## WHERE TO LOOK
| Task | Location | Notes |
|---|---|---|
| Understand base API | `fpdf.php` | Core class methods/behavior |
| Font-related issues | `font/`, `makefont/` | Keep assets/tooling aligned |
| Reference docs | `doc/`, `tutorial/` | Informational; not app-specific logic |

## CONVENTIONS
- Prefer app-layer fixes before editing vendored FPDF internals.
- If patching here is required, keep diff surgical and well-justified.

## ANTI-PATTERNS
- No broad formatting/rewrite of upstream files.
- No project business logic insertion into vendored library files.
- No deleting docs/tutorial assets as “cleanup”.

## NOTES
- Runtime app PDF flow is primarily in `app/modules/schedule/api/generate_pdf_api.php` and `app/shared/lib/pdf_helper.php`.
