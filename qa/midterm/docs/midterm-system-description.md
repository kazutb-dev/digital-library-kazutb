# Intermediate Empirical Review System Description

## Architecture

KazUTB Digital Library operates as a Laravel-based modular monolith with role-separated surfaces:

- Public discovery and news surfaces.
- Reader/member dashboard and account APIs.
- Librarian/internal circulation and review operations.
- Integration API boundary for CRM-facing access.

The platform uses middleware-based boundary controls for permissions, integration authentication, and route governance.

## Technologies

- Backend: PHP 8.4, Laravel.
- Data access: PostgreSQL (runtime), SQLite (testing paths).
- Frontend build: Node.js + Vite.
- Automation: PHPUnit/Laravel test runner, Playwright, PowerShell smoke scripts.
- CI/CD: GitHub Actions workflow at .github/workflows/ci.yml.

## Key functionalities under QA

- Authentication/session protection.
- Catalog/public route reliability.
- Circulation and admin guard behavior.
- Integration API boundary enforcement.
- Risk-based quality gate governance and artifact completeness.
