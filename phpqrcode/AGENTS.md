# PHPQRCODE DIRECTORY GUIDE

## OVERVIEW
Local vendored QR generation engine (`qrlib.php` + internals). Treat as third-party source.

## WHERE TO LOOK
| Task | Location | Notes |
|---|---|---|
| App integration point | `qrlib.php` | Main include used by app code |
| Cache/frame behavior | `qrspec.php`, `phpqrcode.php`, `cache/` | Generated cache files under `cache/` |
| Legacy tooling | `tools/`, `bindings/` | Not part of normal app runtime |

## CONVENTIONS
- Prefer changing app call sites before touching this library.
- If patching is unavoidable, keep modifications minimal and localized.
- Document every local patch in PR/commit notes with file + reason.

## ANTI-PATTERNS
- Do not refactor/format entire vendored files.
- Do not delete `cache/` strategy or alter defaults without proving runtime impact.
- Do not introduce app-specific business logic into this directory.

## NOTES
- Root app guidance still applies; this file only scopes vendored QR boundaries.
- Normal feature work should occur in `app/modules/*` and `app/shared/*`, not here.
