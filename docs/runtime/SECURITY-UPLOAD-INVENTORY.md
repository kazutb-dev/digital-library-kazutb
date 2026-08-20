# Security upload inventory

Verified: 2026-08-20 UTC. Production files were not uploaded or modified.

| Flow | Accepted input | Limit | Authorization | Storage / access |
|---|---|---:|---|---|
| News cover | JPEG, PNG, WebP; MIME plus decoded-image validation; re-encoded | 8 MiB | News create/edit permission | Public UUID-derived image path; raster content re-encoded |
| Message attachment | JPEG, PNG, WebP, PDF, DOCX; extension, MIME and structural validation | Runtime setting, capped at 50 MiB; max 10 request files | Message owner or authorized staff | Private `local` disk; parent-ticket ownership and attachment visibility checked on download |
| Scholarly repository PDF | PDF MIME, passive-PDF structure, checksum, immutable versions | 50 MiB | Repository policy and workflow role | Private `local` disk; controlled policy stream/download only |
| Electronic material | Material-type MIME allowlist, executable-extension denylist, passive-PDF validation | 50 MiB | Digital upload/workflow permission | Private `local` disk; access policy, licence and role checked by backend |
| External-resource logo | JPEG, PNG, WebP; extension, MIME and decoded-image validation | 3 MiB | External-resource management | Public hashed path; image-only contract |
| External-resource contract | PDF/JPEG/PNG plus executable-extension and passive-PDF guard | 10 MiB | Contract-management permission | Private `local` disk; no direct public URL |
| Data-quality import | CSV or JSON mapping fixture parsed in memory; no uploaded executable is published | 10 MiB | Data-quality import permission | Parsed into isolated staging rows; source file is not served |

Global targeted guards reject executable original extensions including `.php`,
`.phtml`, `.phar`, `.sh`, and `.exe`. Private storage keys reject encoded or
plain parent traversal, absolute Unix paths, and Windows-style traversal.
