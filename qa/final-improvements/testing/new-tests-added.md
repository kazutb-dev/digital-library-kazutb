# New Tests Added

## tests/Feature/Api/Integration/ReservationMutateTest.php

### test_approve_response_propagates_context_fields

- **Scenario:** Approve mutation response must propagate all context/traceability fields (request_id, correlation_id, timestamp) in the response envelope.
- **Risk Addressed:** Weak context propagation and traceability in integration-boundary mutation responses.
- **Why Added:** Ensures all required context fields are present for auditability and integration contract compliance.
- **Evidence Value:** Increases behavioral sensitivity and mutation resistance for context mapping.

### test_invalid_operator_org_context_structure_returns_400

- **Scenario:** Malformed X-Operator-Org-Context header (not JSON) must return 400 with correct error code.
- **Risk Addressed:** Integration-boundary input validation and negative-path coverage.
- **Why Added:** Ensures robust input validation and error reporting for integration consumers.
- **Evidence Value:** Strengthens negative-path and contract evidence for integration boundary.
