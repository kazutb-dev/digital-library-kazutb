# Runtime role-route matrix

Generated from the Laravel route registry and verified against `http://192.168.8.5:8080` on 2026-08-03.

Status notation: `200` is a rendered page, `302` is the deliberate authentication/canonical-shell redirect, `403` is an authorization denial, `404` hides an absent or foreign private entity, and `422` is validation. No expected path uses `500`.

## Shell boundaries

| Route | Guest | Student | Faculty | Librarian | Senior | Director | Acquisitions | Cataloguer | Bibliographer | Admin |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| `/dashboard` | 302 | 200 | 200 | 302 | 302 | 302 | 302 | 302 | 302 | 302 |
| `/librarian` | 302 | 403 | 403 | 200 | 200 | 200 | 200 | 200 | 200 | 200 |
| `/admin` | 302 | 403 | 403 | 200¹ | 403 | 403 | 403 | 403 | 403 | 200 |

¹ The ordinary librarian has explicitly delegated control-plane permissions. Individual admin routes still enforce their own permissions and the navigation hides unavailable entries.

## Complete member route registry

The registry contains 34 `/dashboard*` routes plus the legacy `/internal/dashboard` redirect: 35 routes in the cabinet surface. `S/F` means student and faculty. Every staff role is redirected to its canonical shell before a member action executes.

| Method | Route | Guest | S/F owner or valid request | S/F invalid/foreign | Staff/Admin |
|---|---|---:|---:|---:|---:|
| GET | `/dashboard` | 302 | 200 | — | 302 |
| GET | `/dashboard/card` | 302 | 200 | — | 302 |
| POST | `/dashboard/card/printed` | 302 | 302 | 419/422 | 302 |
| GET | `/dashboard/collections` | 302 | 200 | — | 302 |
| POST | `/dashboard/collections` | 302 | 302 | 422 | 302 |
| GET | `/dashboard/collections/{collection}` | 302 | 200 | 404 | 302 |
| PATCH | `/dashboard/collections/{collection}` | 302 | 302 | 404/422 | 302 |
| DELETE | `/dashboard/collections/{collection}` | 302 | 302 | 404 | 302 |
| POST | `/dashboard/collections/{collection}/copy` | 302 | 302 | 404/422 | 302 |
| POST | `/dashboard/collections/{collection}/follow` | 302 | 302 | 404/422 | 302 |
| POST | `/dashboard/collections/{collection}/items` | 302 | 302 | 404/422 | 302 |
| DELETE | `/dashboard/collections/{collection}/items/{item}` | 302 | 302 | 404 | 302 |
| PATCH | `/dashboard/collections/{collection}/order` | 302 | 302 | 404/422 | 302 |
| GET | `/dashboard/digital-materials` | 302 | 200 | — | 302 |
| GET | `/dashboard/fines` | 302 | 200 | — | 302 |
| GET | `/dashboard/history` | 302 | 200 | 422 filter | 302 |
| GET | `/dashboard/incidents` | 302 | 200 | — | 302 |
| GET | `/dashboard/incidents/{incident}` | 302 | 200 | 404 | 302 |
| ANY | `/dashboard/list` (legacy redirect) | 302 | 302 | — | 302 |
| GET | `/dashboard/loans` | 302 | 200 | — | 302 |
| POST | `/dashboard/loans/{loan}/renew` | 302 | 302 | 403/404/422 | 302 |
| GET | `/dashboard/messages` | 302 | 200 | — | 302 |
| POST | `/dashboard/messages` | 302 | 302 | 422 | 302 |
| GET | `/dashboard/messages/{message}` | 302 | 200 | 404 | 302 |
| GET | `/dashboard/notifications` | 302 | 200 | — | 302 |
| POST | `/dashboard/notifications/read-all` | 302 | 302 | 419 | 302 |
| POST | `/dashboard/notifications/{notification}/read` | 302 | 302 | 403/404 | 302 |
| GET | `/dashboard/profile` | 302 | 200 | — | 302 |
| PATCH | `/dashboard/profile` | 302 | 302 | 422 | 302 |
| GET | `/dashboard/reservations` | 302 | 200 | — | 302 |
| POST | `/dashboard/reservations` | 302 | 302 | 422/domain redirect | 302 |
| POST | `/dashboard/reservations/{reservation}/cancel` | 302 | 302 | 403/404/422 | 302 |
| GET | `/dashboard/search` | 302 | 200 | 422 filter | 302 |
| ANY | `/dashboard/ticket` (legacy redirect) | 302 | 302 | — | 302 |
| ANY | `/internal/dashboard` (legacy redirect) | 302 | 302 | — | 302 |

## Verified visible navigation

| Identity | Landing | Visible role links checked | Result |
|---|---|---:|---|
| demo-student | `/dashboard` | member navigation | all 200 |
| demo-teacher | `/dashboard` | member navigation | all 200 |
| demo-librarian | `/librarian` | permission-filtered operations | all 200 |
| demo-senior-librarian | `/librarian` | permission-filtered operations | all 200 |
| demo-director | `/librarian` | reports/review navigation | all 200 |
| demo-acquisitions | `/librarian` | catalogue/copies navigation | all 200 |
| demo-cataloguer | `/librarian` | catalogue/quality navigation | all 200 |
| demo-bibliographer | `/librarian` | catalogue/messages navigation | all 200 |
| demo-admin | `/admin` | complete admin navigation | all 200 |

Evidence commands:

```bash
php artisan route:list --json
php artisan library:smoke-roles --base-url=http://127.0.0.1 --json=/tmp/role-smoke-final.json
PLAYWRIGHT_BASE_URL=http://192.168.8.5:8080 npx playwright test tests/e2e/role-runtime-smoke.spec.ts --project=chromium
```

## Localization expansion

The same visible navigation was verified in `kk`, `ru`, and `en`. The final crawler evidence contains 339 requests (113 per locale), correct `<html lang>`, localized titles and shells, no raw translation keys, no prohibited branding, no HTTP 500, and no new ERROR/CRITICAL entries. The final Chromium matrix contains 27 passing sessions (9 roles × 3 locales). See `i18n-key-matrix.md` and `role-locale-smoke.json` in this directory.
