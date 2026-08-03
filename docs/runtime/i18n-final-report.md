# Final RU / KK / EN localization report

Date: 2026-08-03 UTC. Live target: `http://192.168.8.5:8080`. No commit or push was performed.

1. **Previous architecture.** Russian was the application/fallback default; locale input was split across query/session and layouts embedded their own language copy.
2. **Previous resolution.** Query/session handling was inconsistent and did not provide one authenticated-user/session/cookie policy.
3. **Cause of mixing.** Member/librarian shell literals, page-local query parsing, incomplete dictionaries, and operator-locale notification rendering bypassed a central resolver.
4. **Hardcoded English inventory.** Twelve occurrences of the specifically reported shell phrases were found in the baseline source inventory; they are no longer rendered in RU/KK.
5. **Hardcoded Russian inventory.** System-shell Russian copy was consolidated into translation dictionaries; Russian article/bibliographic/user content remains content.
6. **Hardcoded Kazakh inventory.** The same rule was applied to Kazakh system labels; curated/content values remain data.
7. **Missing/incomplete keys.** Key parity was structurally complete, but 90 EN/KK data-quality/incident values (59 + 31) were incomplete and were translated.
8. **Layouts.** Public, member, librarian, admin, auth and shared embedded/error shells now receive the resolved locale, brand and switcher.
9. **Member pages.** Dashboard, loans, ticket, reservations, collections, history, fines, incidents, notifications, digital materials, messages, profile and search are crawler/browser-covered in all locales.
10. **Librarian pages.** Dashboard, circulation, visits, reservations, catalog, copies, fines, incidents, repository, news, reports, messages and permission-dependent pages are covered.
11. **Admin pages.** Dashboard, users, roles, audit logs, news, messages, reports, integrations, branches, resources, settings and profile are covered.
12. **Public pages.** Home, catalog, book, resources, repository, news, events, contacts and institutional pages use the resolved locale; page-local query overrides were removed.
13. **Auth pages.** Login uses the official brand, localized text, validation, title and global switcher.
14. **Errors.** 401, 403, 404, 419, 422, 429, 500 and 503 use the localized shared error page with safe request ID and navigation.
15. **LocaleResolver.** Canonical locales are `kk`, `ru`, `en`; safe legacy aliases are normalized only on read.
16. **Guest persistence.** Session plus one-year HttpOnly SameSite=Lax `library_locale` cookie; default is `kk`.
17. **User persistence.** The existing `users.locale` field is reused as the preference and accepts `null|kk|ru|en`; a real change is audited.
18. **Login/logout.** A guest preference initializes a null account preference; an explicit account preference remains authoritative; logout preserves the locale cookie.
19. **Default confirmation.** `.env`, `.env.example`, config default/fallback, Carbon, new users and settings resolve to `kk`.
20. **Switcher placement.** Public, auth, member, librarian, admin and error layouts use the shared POST/CSRF component.
21. **Mobile/accessibility.** The dropdown exposes the active language, keyboard-compatible native disclosure, localized `aria-label`, and `aria-current`; it is hidden when printing.
22. **Official brand.** The task-supplied full university names and scientific-library names were adopted.
23. **Brand provenance.** No conflicting approved English variant was found; the exact fallback specified in the brief is documented in `config/library_branding.php`.
24. **Variants.** Full, compact and mark/print-capable forms are provided by the shared library-brand component/config.
25. **Old label removal.** Baseline source contained 157 occurrences of the prohibited product label; the final application/view/route/seeder/translation scan returns zero.
26. **Roles.** All ten canonical role labels are translated without altering permission or database codes.
27. **Statuses.** Reservation, circulation, incident, message, repository and data-quality codes remain canonical and are translated at presentation.
28. **Dates.** `LocalizedText` provides KK/RU/EN long-date and datetime rendering for new notification parameters; operational numeric tables retain unambiguous compact dates where appropriate.
29. **Numbers/currency.** Existing locale-specific public number copy is preserved; monetary UI uses the tenge symbol policy.
30. **Validation.** Complete parity files exist under all three locale directories and middleware sets locale before validation rendering.
31. **Notifications.** New rows store `_i18n` title/body keys and raw parameters in payload; the cabinet translates them at view time.
32. **Email.** Subject/body are rendered inside the recipient locale and the worker locale is restored in `finally`; this is feature-tested.
33. **JavaScript.** Browser smoke checks console errors, failed 500 responses, raw keys and mixed shell phrases; page-specific copy remains scoped rather than loading all translations.
34. **QR/Code 128.** Canonical identifiers remain unchanged; surrounding reader-card labels use current-locale dictionaries.
35. **CSV.** Existing export safety and canonical identifiers are unchanged; localized operational headers are sourced from dictionaries where the export surface supports locale.
36. **Legacy content.** Existing ready-text notifications, user messages, names and bibliographic values are preserved unchanged.
37. **Audit result.** `kk=2920 ru=2920 en=2920 critical=0 warnings=0`.
38. **Parity.** Missing, extra, empty, raw-key and placeholder-mismatch counts are all zero.
39. **Role matrix.** Machine evidence contains 339 requests: 113 per locale.
40. **Crawler KK.** 113 requests, no exception, no HTTP 500.
41. **Crawler RU.** 113 requests, no exception, no HTTP 500.
42. **Crawler EN.** 113 requests, no exception, no HTTP 500.
43. **Browser E2E.** Student, teacher, librarian, senior librarian, director, acquisitions, cataloguer, bibliographer and admin each pass KK/RU/EN: 27/27 sessions.
44. **HTTP 500.** Zero in crawler and browser matrix.
45. **Logs.** Crawler delta reports zero new ERROR/CRITICAL entries.
46. **PHPUnit.** Final relevant clean-PostgreSQL suite: 65 tests, 394 assertions, pass.
47. **Repeated PHPUnit.** The identical clean-PostgreSQL suite passed again: 65 tests, 394 assertions.
48. **Pint.** All localization-stage PHP files pass targeted Pint. Repository-wide `pint --test` still reports 61 pre-existing style issues across unrelated legacy files.
49. **Blade.** `php artisan view:cache` passes.
50. **Vite.** Production build passes; the existing large-chunk advisory remains non-fatal.
51. **User data.** No real reader/catalog record was rewritten. The nine demo identities were exercised and left at `kk`; login timestamps and audit records changed. Live counts remain users 9, records 9,562, copies 50,907.
52. **Intentionally untranslated.** Names, user collection titles, messages, bibliographic originals, identifiers, uploaded files and international terms in the allowlist.
53. **Library enrichment required.** Bibliographic records without dedicated `title_kk/title_ru/title_en` or equivalent need cataloguer-supplied multilingual metadata.
54. **Management confirmation.** The task-supplied English institution wording and localized compact-brand forms should receive final institutional brand-office approval.

Current-state database backup: `/tmp/kazutb-i18n-final-20260803.dump` (4.4 MB), SHA-256 `b1750b223ddb2c86c2076b792f493e7499ebd8f402529076a339eb02c28b4c2c`.

## Quality-gate qualification

The localization-specific runtime, PostgreSQL, browser, Blade, Vite and targeted-style gates are green. The unfiltered historical repository suite is not green: its SQLite run currently reports 288 failures, 148 skips and 755 passes, dominated by pre-existing tests that require removed legacy tables/routes/layout contracts or assume Russian default. Repository-wide Pint likewise exposes 61 pre-existing style issues. Those results are recorded explicitly and were not hidden by excluding tests or mass-formatting unrelated user work.

Evidence files: `i18n-audit.md`, `i18n-key-matrix.md`, `i18n-audit.json`, `role-route-matrix.md`, and `role-locale-smoke.json` in this directory.
