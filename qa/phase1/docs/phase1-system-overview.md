# baseline QA layer (Phase 1) System Overview: KazUTB Digital Library

**Author:** Murat Almas
**Date:** 2026-05-13

## 1. Architecture Style and Modules

- **Architecture:** Monolithic Laravel (PHP) application with modular service structure
- **Key Modules:**
    - Authentication & Authorization
    - Catalog (search, browse, shortlist)
    - Circulation (reservations, issues, returns)
    - Admin (news, integrations, repository management)
    - API (REST endpoints for catalog, shortlist, etc.)
    - External Integrations (resource fetch, news, etc.)
- **Frontend:** Vite/Node.js, Blade templates, Playwright for E2E
- **Backend:** PHP 8.4, Laravel 13, PostgreSQL 18
- **Containerization:** Docker Compose (nginx+php-fpm, frontend-dev, postgres)

## 2. Critical User Flows

- User registration/login/logout
- Catalog search and shortlist
- Book detail and reservation
- Admin news publishing and repository management
- API access for catalog and shortlist

## 3. Route Groups and API Surface

- **Web routes:** `/`, `/catalog`, `/book/{id}`, `/dashboard/*`, `/admin/*`, `/librarian/*`
- **API routes:** `/api/v1/catalog`, `/api/v1/shortlist`, `/api/v1/auth`, `/api/v1/admin/*`
- **Role-based route gating:** Middleware enforces access by role (reader, librarian, admin)

## 4. Role/Permission Boundaries

- **Reader:** Catalog access, shortlist, reservations
- **Librarian:** Circulation, copies, import, reports, messages
- **Admin:** News, integrations, repository, system settings
- **Guest:** Limited catalog access, shortlist (session-based)

## 5. Data Layer and Key Models

- **Models:** User, LiteratureDraft, LiteratureDraftItem, LibraryNews, IdentityMatchLog
- **Database:** PostgreSQL, migrations for all core tables
- **Session:** File-based/session for guests, DB for users

## 6. Existing Tests and CI Status

- **Unit tests:** `tests/Unit/` (core services, models)
- **Feature tests:** `tests/Feature/` (routes, flows)
- **E2E tests:** `tests/e2e/` (Playwright)
- **CI:** GitHub Actions, composer validate, phpunit, Playwright, docker build

## 7. Summary

The KazUTB Digital Library is a modular, containerized Laravel system with clear role boundaries, REST API, and a robust test/CI setup. All analysis is based on the current main branch as of 2026-05-13.



