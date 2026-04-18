# ASSETS KNOWLEDGE BASE

## OVERVIEW
Frontend assets are centralized in `assets/` and consumed by routed PHP pages under `app/modules/*/web`.

## STRUCTURE
```text
assets/
├── css/
│   ├── core/                  # global/base styles
│   └── components/            # reusable UI blocks
└── js/
    └── shared/                # cross-page utility helpers
```

## WHERE TO LOOK
| Task | Location | Notes |
|---|---|---|
| Global look/feel | `assets/css/core/base.css` | Replaces old root `style.css` |
| Shared layout primitives | `assets/css/components/layout.css` | Keep generic/reusable selectors |
| Route/API URL helpers | `assets/js/shared/utils.js` | Use for consistent `index.php?route/api` URL creation |

## CONVENTIONS
- Prefer shared classes/utilities before adding page-specific duplication.
- Keep selectors neutral (module-agnostic) in shared CSS.
- Prefer additive CSS changes; avoid broad visual regressions.

## ANTI-PATTERNS
- Do not add new root-level asset files.
- Do not hardcode legacy wrapper endpoints (`api_*.php`, `jadwal.php`, etc.) in scripts.
- Do not move module-inline CSS/JS blindly without validating UI behavior.

## QUICK CHECKS
```bash
php -S 127.0.0.1:8000
curl -I "http://127.0.0.1:8000/assets/css/core/base.css"
curl -I "http://127.0.0.1:8000/assets/js/shared/utils.js"
```
