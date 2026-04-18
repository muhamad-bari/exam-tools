# LIB KNOWLEDGE BASE

## OVERVIEW
`lib/` is now a **compatibility layer** only. Real implementations live in `app/shared/lib/*`.

## STRUCTURE
```text
lib/
├── database.php      # Wrapper -> app/shared/lib/database.php
└── pdf_helper.php    # Wrapper -> app/shared/lib/pdf_helper.php
```

## WHERE TO LOOK
| Task | Location | Notes |
|---|---|---|
| DB schema/helpers changes | `app/shared/lib/database.php` | `lib/database.php` should remain a thin include wrapper |
| QR PDF helper changes | `app/shared/lib/pdf_helper.php` | Keep wrapper stable for legacy includes |
| Wrapper behavior | `lib/*.php` | Should only bootstrap + require canonical target |

## CONVENTIONS
- Keep wrappers minimal (no business logic).
- Preserve wrapper paths to avoid breaking old include callers.
- Implementation edits belong in `app/shared/lib/*`, not `lib/*`.

## ANTI-PATTERNS
- Do not add new logic into wrapper files.
- Do not change wrapper file names/paths without explicit migration plan.
- Do not duplicate logic between wrapper and canonical implementation.

## QUICK CHECKS
```bash
php -l lib/database.php
php -l lib/pdf_helper.php
php -l app/shared/lib/database.php
php -l app/shared/lib/pdf_helper.php
```
