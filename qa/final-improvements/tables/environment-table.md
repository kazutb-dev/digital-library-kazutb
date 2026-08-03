# Environment Matrix Table

| Component                   | Version          | Status       | Role              | Notes                                      |
| --------------------------- | ---------------- | ------------ | ----------------- | ------------------------------------------ |
| **Framework**               |                  |              |                   |                                            |
| Laravel                     | 13.x             | ✅ Active    | HTTP Framework    | Production-grade                           |
| PHP                         | 8.4.21           | ✅ Active    | Runtime           | Production-compatible                      |
| PHP Unit                    | 12.5.14          | ✅ Active    | Test Framework    | Latest stable                              |
| **Database**                |                  |              |                   |                                            |
| PostgreSQL                  | 18.x             | ⚠️ Partial   | Production DB     | Missing User table in test schema          |
| SQLite                      | 3.x              | ✅ Active    | Test DB           | In-memory (:memory:) for fast execution    |
| **Container Orchestration** |                  |              |                   |                                            |
| Docker                      | Latest           | ✅ Active    | Build/Run         | docker compose used                        |
| Docker Compose              | Latest           | ✅ Active    | Orchestration     | Multi-service: app, postgres, frontend-dev |
| **Frontend**                |                  |              |                   |                                            |
| Node.js                     | 20.x             | ✅ Active    | Frontend Runtime  | For TypeScript/Playwright builds           |
| TypeScript                  | 5.x              | ✅ Active    | Frontend Language | Playwright config                          |
| Playwright                  | Latest           | ✅ Available | E2E Testing       | Browser system deps required in container  |
| **Authentication**          |                  |              |                   |                                            |
| Laravel Sanctum             | Latest           | ✅ Active    | API Auth          | Bearer token + session management          |
| Session Middleware          | Laravel Built-in | ✅ Active    | Web Auth          | library.user session key                   |
| **Testing Tools**           |                  |              |                   |                                            |
| Mockery                     | Latest           | ✅ Active    | Mocking           | Integration test support                   |
| testdox Formatter           | PHPUnit Built-in | ✅ Active    | Reporting         | Human-readable test output                 |

## Environment Compatibility

- **Test Execution:** ✅ Fully operational (947 tests completed)
- **Schema Compatibility:** ⚠️ PostgreSQL test schema incomplete (missing User table)
- **Container Stability:** ✅ Stable across 190s runtime
- **Database Failover:** Not tested (single-instance environment)
- **API Availability:** ✅ All endpoints operational for authenticated calls
