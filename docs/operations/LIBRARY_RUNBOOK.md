# Library recovery operations runbook

## Purpose and safety boundary

The **Library data recovery** administration area is a read-only technical view of the already completed MARC-SQL recovery. It is intended for reconciliation, incident diagnosis, and evidence lookup. It is not an import console.

The web control plane must never:

- rerun a recovery package load or application;
- start a new MARC import;
- update or delete `legacy_marc_records`, `legacy_marc_fields`, or `legacy_marc_copies`;
- normalize `fund_raw` automatically;
- synthesize a legacy KSU value for a copy that did not have one in `INV.T990t`;
- enable KSU number allocation merely because a sequence is visible;
- resolve a source/current conflict or link an orphan outside the audited librarian workflow.

All dashboard and detail endpoints require `legacy_recovery.view`. The hand-off endpoint to the librarian review queue additionally requires `legacy_recovery.manage`. Decisions remain subject to the permissions and audit rules of the librarian recovery workflow.

## What to inspect

### Import batches

For every row in `legacy_import_batches`, verify:

1. `package_sha256` identifies the intended immutable package.
2. `documents_expected = documents_loaded`.
3. `copies_expected = copies_loaded`.
4. `fields_expected = fields_loaded`.
5. `validation`, `reconciliation`, and `apply_stats` contain the expected evidence for that package.
6. The batch status and timestamps agree with the completed operation.

A mismatch is a diagnostic condition. Do not respond by rerunning the package. Preserve the hash and evidence, identify the failing stage, and obtain an approved recovery procedure before any command is executed.

### Raw MARC evidence

Use the raw lookup by source document ID, control number, catalogue record ID, source hash, or MARC tag. The record and its fields are tied by both `legacy_import_batch_id` and `source_doc_id`; the source document ID alone is not sufficient across multiple batches.

Raw and canonical JSON displayed by the page are evidence. Do not edit them. Corrections belong in canonical catalogue records through the normal cataloguing workflow, with the immutable source retained for comparison.

### Quarantine and conflicts

Group quarantine rows by `kind` and `status`, then open the detail to inspect the batch hash, source IDs, reason, and payload. Group source/current conflicts by entity, field, and status, then compare the current and incoming values.

The admin view performs no decision. An administrator with `legacy_recovery.manage` can hand the item to the existing librarian review queue:

- orphan copy links use the audited orphan-resolution service;
- supported source/current conflicts use the audited librarian conflict workflow;
- unsupported or ambiguous cases stay open and are escalated to a senior librarian/cataloguer;
- `T090w` / `fund_raw` is always reviewed manually and is never normalized automatically.

### KSU

`INV.T990t` is the authoritative legacy KSU receipt/entry value. `ksu1` is the materialized Part 1 view, while `ksu2` and `ksu3` have their separate legacy meanings. Do not reinterpret `dbo.STATES` as `INV.STATE`.

The KSU section displays configuration and sequences only. Automatic allocation must remain disabled where `requires_manual_decision` is true or where the numbering evidence is incomplete. Missing and duplicate observed numbers are diagnostic evidence, not unused numbers available for allocation.

## Incident response

1. Record the affected batch ID, package name, package SHA-256, source document/inventory IDs, and visible status.
2. Capture the expected/loaded counters and relevant validation or reconciliation evidence.
3. Determine whether the issue is a display/query problem, an open review item, or a proven data-integrity failure.
4. For a normal review item, use the librarian recovery queue and supply a factual resolution note.
5. For a data-integrity failure, stop before any package/load/import command. Escalate with the captured evidence and obtain a verified backup and approved change procedure.
6. After an audited librarian decision, confirm that the source evidence is unchanged and the queue/conflict status reflects the decision.

## Safe verification

Automated tests for this area must use an isolated SQLite database. They should verify authorization, rendering of batch/hash/count evidence, raw field lookup, quarantine/conflict details, and that GET requests leave all recovery tables unchanged.

Do not run tests against the live PostgreSQL connection. Before any test command, confirm that the effective connection is SQLite with an isolated database such as `:memory:`. Web requests against the admin control plane are safe reads; import, load, apply, migration, and recovery console commands are outside this runbook's read-only verification scope.
