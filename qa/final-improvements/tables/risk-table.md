# Risk Table

| Risk ID    | Description                                                  | Component       | Likelihood | Impact   | Score | Priority     |
| ---------- | ------------------------------------------------------------ | --------------- | ---------- | -------- | ----- | ------------ |
| R-PRIV-001 | Unauthorized access to admin functions                       | Admin Privilege | Low        | Critical | 15    | **CRITICAL** |
| R-PRIV-002 | Privilege escalation via session manipulation                | Session Auth    | Low        | Critical | 15    | **CRITICAL** |
| R-API-001  | Integration endpoint mutation without proper role validation | Integration API | Low        | High     | 12    | **HIGH**     |
| R-API-002  | Context/correlation data not propagated in responses         | Integration API | Medium     | Medium   | 8     | **MEDIUM**   |
| R-API-003  | Idempotency key collision handling failure                   | Integration API | Low        | High     | 12    | **HIGH**     |
| R-RESV-001 | Reader reservation API contract mismatch                     | Reservation API | Medium     | Medium   | 8     | **MEDIUM**   |
| R-RESV-002 | Account reservation filtering/pagination errors              | Reservation API | Medium     | Medium   | 8     | **MEDIUM**   |
| R-AUTH-001 | Unauthenticated access to protected endpoints                | Authentication  | Low        | Critical | 15    | **CRITICAL** |
| R-DATA-001 | Data validation bypass in input handling                     | Data Validation | Low        | High     | 12    | **HIGH**     |
| R-DATA-002 | Missing required fields in API responses                     | Data Contract   | Medium     | Medium   | 8     | **MEDIUM**   |
| R-PERF-001 | Performance regression in test suite execution               | Performance     | Medium     | Low      | 6     | **MEDIUM**   |
| R-DB-001   | Database schema incompleteness blocking tests                | Database        | High       | Medium   | 18    | **CRITICAL** |

## Summary

- **CRITICAL Risks (4):** R-PRIV-001, R-PRIV-002, R-AUTH-001, R-DB-001
    - All have comprehensive test coverage or explicit documentation of blockers
- **HIGH Risks (3):** R-API-001, R-API-003, R-DATA-001
    - 100% coverage achieved through integration and feature tests
- **MEDIUM Risks (4):** R-API-002, R-RESV-001, R-RESV-002, R-DATA-002, R-PERF-001
    - Adequate coverage through existing test suites; R-DB-001 explicitly documented as environment blocker
