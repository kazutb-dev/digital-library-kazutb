# Environment Table

| Variable      | Value/Status     | Notes                               |
| ------------- | ---------------- | ----------------------------------- |
| APP_ENV       | local            | Set in .env, matches Docker Compose |
| DB_CONNECTION | pgsql            | PostgreSQL 18, healthy              |
| DB_HOST       | postgres         | Docker Compose service name         |
| DB_PORT       | 5432             | Exposed as 5433 on host             |
| DB_DATABASE   | kazutb_library   | Present, migrations up to date      |
| DB_USERNAME   | kazutb_library   | Present                             |
| DB_PASSWORD   | (set)            | Present                             |
| FRONTEND_PORT | 5173             | Vite dev server, running            |
| APP_URL       | http://localhost | App responds with HTTP 200          |
| ...           | ...              | ...                                 |

**Note:** All required environment variables are present and valid. No missing or misconfigured values detected during runtime bring-up and verification.
