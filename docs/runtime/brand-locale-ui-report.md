# KazUTB brand and locale UI restoration report

Date: 2026-08-03  
Working tree: `ac7c621` plus existing uncommitted platform work  
Commit/push: not performed

## 1–8. Audit and implementation

1. The old switcher was lost when the inline public-navbar locale disclosure was replaced by a new shared i18n component. The new component inserted the emoji `🌐` and its own generic styling; role layouts also started rendering `brand.compact`, producing inconsistent brands.
2. The earlier line-globe implementation was found in `HEAD:resources/views/partials/navbar.blade.php` at the former lines 599–605. Git commit `ac7c621` contains the exact three-path SVG: circle, horizontal equator and curved meridian.
3. The implementation is now centralized in `resources/views/components/locale-switcher.blade.php`. The public navbar no longer owns a second locale menu.
4. The icon is the exact inline SVG path geometry from the earlier public navbar. It is not an emoji, Material Symbol, newly drawn icon or external asset.
5. It matches the old implementation because the `viewBox`, circle and both paths were copied unchanged from the version stored in Git. The compact white pill, blue stroke, fixed height and short `ҚАЗ / РУС / ENG` label restore the requested earlier visual treatment.
6. The shared component is mounted by public, login, forced-password/auth, member, librarian and admin layouts. Error pages 401/403/404/419/422/429/500/503 inherit the public layout and therefore use the same component.
7. Removed duplication: the old inline public dropdown and the commented legacy admin dropdown are no longer active source. A rendered page has exactly one `data-locale-switcher`.
8. Updated layouts/entry points: `layouts/public`, public navbar, `auth`, `auth/password-change`, `layouts/member`, `layouts/librarian`, `layouts/admin`, plus public error layouts through inheritance.

## 9–15. Result by surface

9. Desktop: a 40 px high white rounded pill aligns with header actions, has a blue globe, stable minimum width, border/shadow and a compact dropdown.
10. Mobile: the same control stays visible. The public media rule that hid it was removed; admin mobile now has a visible compact brand in the header and the full official lockup in the drawer. Chromium found no horizontal overflow at 390 px.
11. Login: the left hero uses logo + localized library name + full university name; the top-right uses the shared pill.
12. Public navbar: its established size, spacing and transparent/solid behavior are retained. Its brand markup now comes from the common brand component; the switcher is the restored pill.
13. Member cabinet: the header uses the official mark and lockup. The sidebar identifies `Жұмыс орны / Оқырман кабинеті` separately.
14. Librarian and specialist cabinets: the sidebar/header use the same official brand. `Кітапханашы`, `Кітапхана директоры`, `Жетекші кітапханашы`, `Комплектатор`, `Каталогизатор` or `Библиограф` is rendered in a separate `data-workspace-role` block.
15. Admin: the official library lockup replaces the initial/avatar as the main brand. `Жүйелік басқару / Әкімші` is separate; the user initial remains only as the profile control.

## 16–22. Asset, naming and roles

16. The selected logo is `public/logo.png`, 512×512 RGBA PNG, 256,243 bytes, SHA-256 `b6ec4db0a3975c3de7d24d9dcb6fc595ba8d47dc8f4c805aed7243462d69b888`.
17. This is the exact same asset previously referenced directly by the good public navbar. All brand variants now resolve `config('library_branding.logo')` to `/logo.png`.
18. Removed from rendered layouts/translations: `KazUTB кітапханасы`, `KazUTB Smart Library`, `My Library`, `Your personal reading workspace`, `Operations`, and `Librarian Console`. Historical negative assertions/comments remain only in tests/documentation.
19. KK brand: `Ғылыми кітапхана` / `Қ. Құлажанов атындағы Қазақ технология және бизнес университеті`.
20. RU brand: `Научная библиотека` / `Казахский университет технологии и бизнеса имени К. Кулажанова`.
21. EN brand: `Scientific Library` / `K. Kulazhanov Kazakh University of Technology and Business` (the project-approved value already present in configuration).
22. Brand identity is emitted only by `x-library-brand`; the workspace/role is a neighboring block using `brand.workspace.*` keys and never replaces the brand.

## 23–30. Verification

23. Feature tests: `BrandAndLocaleSwitcherUiTest` + `LocalizationArchitectureTest` — **14 passed, 376 assertions**. This covers public/login/member/librarian/admin, one switcher per page, all labels/names, exact logo, role separation, URL retention and 403/404/419/500/503.
24. Browser E2E: role runtime — **27/27 passed**; visual/accessibility/responsive suite — **3/3 passed**. Escape, outside click, `aria-expanded`, visible current locale, dropdown viewport bounds, mobile visibility and horizontal overflow are checked.
25. KK/RU/EN: i18n audit — **2936 / 2936 / 2936**, critical 0, warnings 0. Role crawler exercised all three locales.
26. Runtime role crawler: **339 requests**, HTTP 500 = **0**, exceptions = **0**, new application log errors = **0**. Machine-readable result: `docs/runtime/brand-locale-role-smoke.json`.
27. Browser JavaScript errors: **0** in the role and visual suites.
28. Vite: **PASS**, 2,580 modules transformed. The existing non-blocking >500 kB chunk-size warning remains.
29. Blade compilation: **PASS** (`php artisan view:cache`).
30. Pint: all 20 touched PHP files — **PASS**. Repository-wide `pint --test` still reports **60 pre-existing style issues in unrelated files**. Those files were deliberately not reformatted in this focused UI correction.

Additional baseline findings: the old `PublicShellIATest` still expects a removed `/discover` navbar item; legacy member/librarian overview tests still boot an empty SQLite database without a `users` table. These are unrelated to this change. The live role crawler and new isolated control-plane tests are green.

## 31. Screenshots

Before (6): `docs/runtime/screenshots/brand-locale-before/`

- `public-desktop.png`, `public-mobile.png`
- `login-desktop.png`
- `member-desktop.png`
- `librarian-desktop.png`
- `admin-desktop.png`

After (13): `docs/runtime/screenshots/brand-locale-after/`

- public desktop/mobile, login desktop, member desktop/mobile
- librarian, senior librarian, director, acquisitions, cataloguer and bibliographer desktop
- admin desktop/mobile

## 32. Decisions requiring leadership confirmation

No blocking UI decision is outstanding. Optional confirmation: whether the English legal name should remain the already configured `K. Kulazhanov Kazakh University of Technology and Business`, and whether a separate official vector/print logo will be supplied later. No logo artwork or legal name was invented in this change.
