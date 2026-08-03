# Translation key matrix

Generated and verified on 2026-08-03 with `php artisan library:i18n:audit`.

| Locale | Scalar keys | Missing | Empty | Raw-key values | Placeholder mismatch | Suspicious identical |
|---|---:|---:|---:|---:|---:|---:|
| KK | 2,920 | 0 | 0 | 0 | 0 | 0 |
| RU | 2,920 | 0 | 0 | 0 | 0 | 0 |
| EN | 2,920 | 0 | 0 | 0 | 0 | 0 |

All locale groups have the same file/key structure. The command exits non-zero for a missing/empty/non-scalar/raw-key translation, mismatched placeholder set, or prohibited branding in application, view, JavaScript, route, seeder or translation source.

## Domains

| Domain | Representative keys | Consumer |
|---|---|---|
| Brand | `brand.university`, `brand.library`, `brand.compact`, `brand.logo_alt` | all layouts, auth, errors, print |
| Shared shell | `shell.language_switcher`, `shell.navigation.*`, `common.actions.*` | public/member/librarian/admin |
| Member | `librarian.member.nav.*`, `librarian.member.notifications.*` | reader cabinet |
| Staff | `librarian.nav.*`, `librarian.roles.*`, `librarian.notifications.*` | operational console |
| Admin | `admin.*`, `roles.*`, `permissions.*`, `settings.*`, `reports.*` | control plane |
| Workflow status | `librarian.reservations.statuses.*`, `incidents.statuses.*`, `messages.statuses.*` | presentation only; raw codes stay in DB |
| Validation/errors | `validation.*`, `errors.pages.*` | request validation and 401–503 pages |
| Notifications | stored `_i18n.title_key`, `_i18n.body_key`, `_i18n.parameters` | current UI locale and recipient email locale |

## Resolver contract

Authenticated priority: user `locale` → session → signed/encrypted application cookie → `kk`.

Guest priority: session → cookie → `kk`.

Accepted canonical values are only `kk`, `ru`, `en`. Resolver-only legacy normalization supports `kz|kaz|kk-KZ → kk`, `ru-RU|rus → ru`, and `en-US|eng → en` without admitting those values to new writes.

## Runtime matrix

| Identity | KK | RU | EN | Requests per locale |
|---|---:|---:|---:|---:|
| Student | pass | pass | pass | 13 |
| Faculty | pass | pass | pass | 13 |
| Librarian | pass | pass | pass | 21 |
| Senior librarian | pass | pass | pass | 20 |
| Director | pass | pass | pass | 5 |
| Acquisitions | pass | pass | pass | 3 |
| Cataloguer | pass | pass | pass | 8 |
| Bibliographer | pass | pass | pass | 3 |
| Admin | pass | pass | pass | 24 |

The crawler additionally checks three protected guest boundaries per locale. Total: 339 requests, zero HTTP 500, zero new error/critical log entries.

