# Seeded role and permission matrix

This document summarizes the baseline produced by `PermissionSeeder` and `RoleSeeder` for the `web` guard. It does not replace controller/service ownership checks, and deployments can change grants after seeding.

There is no seeded `guest` role. An unauthenticated visitor is represented by the absence of a role and can only use public routes.

## Roles

| Role | Implemented baseline purpose and notable grants |
|---|---|
| `member` | Catalogue discovery, own cabinet/profile, own loans/renewal/reservations/fines/incidents/notifications, digital/repository reading, external resources and own messages/collections. |
| `librarian` | Full `member` grant plus catalogue/copy work, circulation desk, reservation handling, inventory view/scan, single barcode marking, fines/incidents, operational reports and official-report preparation, repository/digital/news and service queues. |
| `senior_librarian` | Full `librarian` grant plus circulation overrides, reservation extension/transfer/queue override, inventory create/review/approve, batch barcode, write-off, KSU resolution, acquisition intake/confirmation, operational-library settings, recovery review/resolution and data-quality approvals. |
| `acquisitions` | Order creation/receipt/management, intake/confirmation, creation of minimal bibliographic drafts, KSU view/manage, acquisition reports/export, copy creation and periodical management. |
| `cataloguer` | Catalogue search/read/create/edit/import/merge/raw MARC, copy edit and data-quality catalogue work; no circulation desk grant. |
| `bibliographer` | Catalogue read/search, external/repository discovery and bibliography-facing service work such as shortlist, messages, tasks, EDD and periodicals; no catalogue write grant. |
| `director` | Governance and approval role: full reports and official approval, publication/repository/digital approvals, analytics, incident oversight, queue override and service governance. It does not inherit the member or librarian sets. |
| `admin` | Explicitly enumerated broad operational/system grant, including users/roles/settings/logs, recovery control plane and integrations. It intentionally lacks official-report approval. |

`admin` is not a wildcard. Its permissions are listed individually in `RoleSeeder`, so newly introduced permissions are not implicitly granted.

## Operational separation matrix

The table records the seeded grant, not merely whether a screen is visible.

| Capability | member | librarian | senior | acquisitions | cataloguer | bibliographer | director | admin |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| Search/read catalogue | yes | yes | yes | yes | yes | yes | no | yes |
| Create/edit catalogue | no | yes | yes | create only | yes | no | no | yes |
| View raw MARC | no | no | yes | no | yes | no | no | yes |
| Issue/return staff circulation | no | yes | yes | no | no | no | no | yes |
| Circulation overrides | no | no | yes | no | no | no | no | yes |
| Inventory view/scan | no | yes | yes | no | no | no | no | yes |
| Inventory create/review/approve | no | no | yes | no | no | no | no | yes |
| Write off copies | no | no | yes | no | no | no | no | yes |
| Create/receive orders | no | no | no | yes | no | no | no | yes |
| Confirm intake batch | no | no | yes | yes | no | no | no | yes |
| KSU view/manage | no | no | yes | yes | no | no | no | yes |
| KSU resolve | no | no | yes | no | no | no | no | yes |
| Operational reports | no | yes | yes | acquisition only | no | no | full | full |
| Prepare/submit official report | no | yes | yes | no | no | no | no | yes |
| Approve official report | no | no | no | no | no | no | yes | no |
| Recovery technical view/manage | no | no | no | no | no | no | no | yes |
| Recovery review/resolve | no | no | yes | no | no | no | no | yes |
| User/role/system administration | no | no | no | no | no | no | no | yes |
| Operational library settings | no | no | yes | no | no | no | no | yes |

“Acquisition only” means the `acquisitions` role has `reports.view_acquisitions`, not `reports.view_ops` or `reports.view_full`. Report definitions still enforce their own permission expression.

“Create only” means that acquisitions staff can create an audited draft for an intake line and return to the acquisition workspace. They do not receive `catalog.edit_record` or `catalog.view_raw_marc`.

## Sensitive permission groups

### Member ownership

The member grants are deliberately scoped (`loans.view_own`, `loans.renew_own`, `reservation.cancel_own`, and similar). Controllers additionally compare authenticated `user_id`; possession of a member permission does not authorize access to another reader's row.

### Circulation and stock

- Desk operations: `circulation.issue`, `circulation.return`, `circulation.renew`.
- Exceptions: `circulation.override_limits`, `circulation.override_due_date`.
- Queue control: `reservation.extend`, `reservation.manage_transfer`, `reservation.override_queue`.
- Stock control: `inventory.create`, `inventory.scan`, `inventory.review`, `inventory.approve`, `copies.movements.create`, `copies.write_off`.

The services still require reasons, state eligibility, ownership and row locks where applicable.

### Reports

- Definition access: `reports.view_ops`, `reports.view_acquisitions`, `reports.view_full` and domain-specific analytics permissions.
- General export: `reports.export`.
- Official workflow: `reports.official.create`, `.submit`, `.approve`, `.archive`, `.export`, `.delete_draft`.

Official approval is stronger than a permission check: the policy also requires the `director` role and a person other than the creator and submitter. Only `director` is seeded `reports.official.approve`; `admin` is deliberately not.

### Recovery

- Technical/admin reading: `legacy_recovery.view`.
- Technical control-plane management gate: `legacy_recovery.manage`.
- Librarian review and decision: `legacy_recovery.review`, `legacy_recovery.resolve`.

Raw legacy MARC is immutable regardless of role. Recovery permissions authorize review workflows, not package reruns or raw-record edits.

## Seeder integrity

`RoleSeeder::run` checks every role permission against `PermissionSeeder::PERMISSIONS` and throws before sync if a referenced permission is missing. It then uses `syncPermissions`, making the role constants the baseline truth for a fresh reseed.

## Code references

- [`PermissionSeeder`](../../database/seeders/PermissionSeeder.php)
- [`RoleSeeder`](../../database/seeders/RoleSeeder.php)
- [`OfficialReportSnapshotPolicy`](../../app/Policies/OfficialReportSnapshotPolicy.php)
- [`CirculationService`](../../app/Services/Catalog/CirculationService.php)
- [`InventoryService`](../../app/Services/Catalog/InventoryService.php)
