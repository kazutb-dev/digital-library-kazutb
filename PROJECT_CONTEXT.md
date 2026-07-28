# KazUTB Library Platform — Full Canonical Project Context

## Vault Navigation

This document is the root canonical source of truth.
For decomposed navigable notes, see [[GRAPH_INDEX]].

**Quick access:**
- [[START_HERE]] — orientation
- [[CURRENT_STATE]] — where we are now
- [[RBAC_MATRIX]] — full permission matrix
- [[DATA_MODEL]] — entity structure
- [[STATUS_DICTIONARY]] — all status enums
- [[CIRCULATION_POLICY]] — loan and reservation rules
- [[PAGE_MAP]] — all routes
- [[AUTH_MODEL]] — login flow and CRM integration

> Version: 2.0 Final | Date: 2026-04-20 | Maintained by: aulykpan
> This document is the single source of truth for the KazUTB Library Platform project.
> All implementation, design, and architectural decisions must align with this document.
> In case of conflict between this document and any legacy design file, export, or prior note — THIS DOCUMENT TAKES PRECEDENCE.

---

## TABLE OF CONTENTS

1. Project Overview
2. Operational Model
3. Authentication Model
4. User Roles
5. RBAC — Full Permission Matrix
6. Database & Data Architecture
7. Catalog & Search
8. UDC — Universal Decimal Classification
9. Faculty & Department Structure
10. Personal Literature Shortlist
11. Personal Cabinet (Member Dashboard)
12. Reservation Policy & Rules
13. Loan / Circulation Policy & Rules
14. Status Dictionaries
15. Reservation, Issue & Return Workflows
16. News & Announcements Module
17. Homepage
18. External Resources Module
19. Digital Materials & Controlled Viewer
20. Scientific Works / Thesis / Dissertation Module
21. Contact / Feedback Module
22. Analytics & Reporting
23. Library Operational Workflows
24. Data Cleanup Panel
25. Notification Model
26. Audit Log Scope
27. File & Storage Model
28. Multilingual / Content Language Policy
29. Search Ranking & Result Presentation Rules
30. Module Boundaries & Page Map
31. Technical Considerations
32. Key Decisions Summary Table
33. Non-Goals
34. Future Roadmap

---

## 1. Project Overview

### 1.1 What This Project Is

This is not a decorative update or a simple UI refresh.
This is the creation of a **new independent digital library platform** for KazUTB —
a full-domain, production-oriented library system that replaces the old ABIS "MARC-SQL"
and becomes the real digital foundation of the university library.

**Goals:**
- Build a modern library platform from scratch
- Migrate and clean existing legacy data
- Support real library operations end-to-end
- Serve students, teachers, staff, librarians, and administrators
- Integrate with the university CRM system for authentication only
- Provide public APIs so that the CRM team can build parallel panels
- Be production-ready from day one
- Become the **best library platform in Kazakhstan**

### 1.2 Why a New System Is Needed

The old system (MARC-SQL for school libraries) only handled:
- Storing fund records
- Storing book records
- Letting librarians work on cataloging

It did **NOT**:
- Publish the catalog on the web
- Give users access to the catalog
- Support modern library practices
- Support modern data management or analytics
- Scale or integrate with other university systems

Additionally, when data was originally migrated from an even older database into MARC-SQL,
there were **errors, data leakage, and no proper migration process.** As a result:
- Many library records are incorrect
- Many rows contain bad, empty, or anomalous data
- The data quality is fundamentally unreliable

The new platform must include a dedicated data management panel allowing librarians and admins
to identify, correct, and improve anomalous, empty, or incorrect records over time.

### 1.3 Project Nature

This project is simultaneously:
- A product project
- A library project
- A digital transformation project
- A data reorganization and quality improvement project
- A new operational environment project
- A new user-facing library project
- A project that must destroy dependence on MARC-SQL while carefully inheriting its data

---

## 2. Operational Model

### 2.1 Library = Primary Full-Domain Operational System

The library platform is the **main system.** It is not a satellite of CRM.

The library owns:
- Guest UI
- Member UI
- Librarian UI
- Admin UI
- All library business logic
- All library data
- Its own PostgreSQL database
- Its own complete API layer
- Its own administrative environment

Even if CRM builds parallel panels using library APIs, the library **must always maintain
full equivalent UI internally.**

### 2.2 CRM Role

CRM **is:**
- Authentication provider (LDAP/AD validation)
- Parallel admin ecosystem for the university
- A consumer of library public APIs

CRM **is NOT:**
- The place where library logic lives
- A database that the library depends on
- A replacement for library panels
- A system that connects directly to the library database

### 2.3 API Strategy

- Library provides public REST APIs
- CRM developer uses these APIs to build parallel panels on the CRM side
- POST requests from library system go to CRM as needed
- CRM does GET/POST to library APIs — no direct DB access
- Library must maintain full equivalent UI regardless of what CRM builds

---

## 3. Authentication Model

### 3.1 Login Flow

```
User opens library login page
  → enters login + password
  → library sends credentials to CRM API (POST /api/login)
  → CRM validates against LDAP/AD
  → CRM returns bearer token + user data
  → library stores token securely (httpOnly cookie or secure session)
  → library uses GET /api/me with Authorization: Bearer <token>
  → user is now authenticated inside the library system
  → no redirect to CRM UI ever
  → UX stays fully inside the library platform
```

### 3.2 Known CRM Auth Endpoints

| Endpoint | Method | Purpose |
|---|---|---|
| `/api/login` | POST | Authenticate user via AD |
| `/api/admin/login` | POST | Admin authentication |
| `/api/me` | GET | Get current user info |
| `/api/logout` | POST | Logout |

- CRM host: `10.0.1.47`
- Base URL: `http://10.0.1.47`
- API base: `http://10.0.1.47/api`
- Auth model: Bearer Token

### 3.3 Token Management in Library

The library must:
- Securely store the bearer token (httpOnly cookie or secure session — never localStorage)
- Load user profile on page load via `/api/me`
- Handle logout (call `/api/logout`, clear local session)
- React correctly to invalid/expired tokens (show re-login prompt, no crash)
- Handle re-login flow gracefully
- Not assume refresh tokens exist — implement safe session fallback
- Never expose the bearer token in client-side JavaScript

### 3.4 CRM User Data Structure

```json
{
  "token": "...",
  "token_type": "Bearer",
  "user": {
    "id": "...",
    "name": "...",
    "first_name": "...",
    "last_name": "...",
    "initials": "...",
    "display_name": "...",
    "description": "...",
    "department": "...",
    "department_number": "...",
    "room": "...",
    "email": "...",
    "ad_login": "...",
    "role": "..."
  }
}
```

Library uses: `id`, `name`, `email`, `role`, `department`, `display_name`, `ad_login`

### 3.5 Access Rules

- **Only users who exist in AD** can access authenticated features
- Guests (non-AD users, anonymous visitors) get public read-only access only
- No guest can perform any library operation or access protected content
- Users not in AD cannot log in — they are treated as permanent guests
- The term "AD certificate" is **not used** — the actual model is LDAP/AD credentials via CRM bearer token

---

## 4. User Roles

### 4.1 Role Definitions

| Role | Who | Scope |
|---|---|---|
| **Guest** | Any anonymous / non-AD user | Public catalog, metadata, availability, resources, news |
| **Student** | University student in AD | Full member access |
| **Teacher** | University teacher in AD | Full member access (same as student) |
| **Employee** | University non-library staff in AD | Full member access (same as student/teacher) |
| **Librarian** | Library staff | Full operational library workflow |
| **Admin** | System administrator | Full system control |

### 4.2 Ordinary Users (Members)

All three — **student, teacher, employee** — share **identical access scope.**
There is no differentiation between them at the feature level.
Collectively called "ordinary users" or "members."

### 4.3 Guest — Detailed Access

Guests CAN see:
- Full-text catalog search
- Book detail page (full metadata)
- Availability of copies (how many, where)
- Branch/library point
- Sigla (shelf code)
- Precise shelf location
- Inventory number (display only)
- Barcode (display only)
- External resource cards/descriptions
- Digital material covers and previews (no content)
- News/announcements
- Homepage all public sections
- Scientific works repository — metadata only

Guests CANNOT:
- Log in with non-AD credentials
- Reserve books
- Access personal cabinet
- Access digital material content
- Submit feedback/messages
- Perform any operational action
- Access scientific works full text

Login prompts appear only when a restricted action is attempted.

### 4.4 Member Features

All ordinary users get:
- Login via library UI → CRM → AD → Bearer token
- Full catalog access with all metadata
- Book reservation (subject to policy limits)
- Access to permitted digital materials (role-based + access flags)
- Personal cabinet (shared member dashboard)
- Personal literature shortlist (wishlist-style)
- Borrowing history
- Notifications (in-app + email)
- Access to external licensed resources (with institutional credentials if required)
- Ability to submit feedback/messages to library head
- Scientific works repository browse (metadata + read with controlled viewer)

### 4.5 Librarian Features

Librarians work with the **full operational library workflow:**
- Catalog management (add, edit, delete bibliographic records)
- UDC classification management
- Edition and copy management
- Issue (выдача) of copies
- Return (возврат) of copies
- Reservation processing and confirmation
- Data import (CSV, MARC, etc.)
- Data correction and cleanup (anomaly resolution panel)
- Upload book cover images
- Upload digital versions of books (with rights-compliant restrictions)
- Reports and statistics
- Analytics panel (operational metrics)
- Fund and branch management
- News/announcements creation and management
- Scientific works upload (with author consent) and moderation
- Contact messages inbox (full visibility)
- QR code support for issue/return

Librarians do NOT have:
- User role management
- System settings
- Integration monitoring
- Log monitoring (system-level)
- External resource card management

### 4.6 Admin Features

Admin has maximum system control:
- All librarian features
- User management (create, edit, deactivate)
- Role management
- System settings
- Service/background operations
- System log monitoring
- Integration monitoring
- External resources management (add/edit/remove resource cards)
- Scientific works full control (approve/reject/publish/remove)
- News full control
- Contact messages full control
- Data cleanup tools (full access)
- Fund and location structure management
- Export controls
- Full analytics and reports access

---

## 5. RBAC — Full Permission Matrix

### 5.1 Core Catalog & Search Actions

| Action | Guest | Member | Librarian | Admin |
|---|---|---|---|---|
| Search catalog | ✅ | ✅ | ✅ | ✅ |
| View full book metadata | ✅ | ✅ | ✅ | ✅ |
| View UDC / author sign | ✅ | ✅ | ✅ | ✅ |
| View availability status | ✅ | ✅ | ✅ | ✅ |
| View branch/library point | ✅ | ✅ | ✅ | ✅ |
| View sigla / shelf location | ✅ | ✅ | ✅ | ✅ |
| View inventory number | ✅ | ✅ | ✅ | ✅ |
| View barcode (display only) | ✅ | ✅ | ✅ | ✅ |
| View digital cover/preview | ✅ | ✅ | ✅ | ✅ |

### 5.2 Reservation & Circulation Actions

| Action | Guest | Member | Librarian | Admin |
|---|---|---|---|---|
| Create reservation | ❌ | ✅ | ✅ | ✅ |
| Cancel own reservation | ❌ | ✅ | ✅ | ✅ |
| Cancel any reservation | ❌ | ❌ | ✅ | ✅ |
| Confirm/process reservation | ❌ | ❌ | ✅ | ✅ |
| Issue copy to user | ❌ | ❌ | ✅ | ✅ |
| Return copy | ❌ | ❌ | ✅ | ✅ |
| View own borrowing history | ❌ | ✅ | ✅ | ✅ |
| View any user's history | ❌ | ❌ | ✅ | ✅ |
| Renew loan | ❌ | ✅ (if policy allows) | ✅ | ✅ |
| Override circulation limits | ❌ | ❌ | ✅ | ✅ |

### 5.3 Personal Shortlist Actions

| Action | Guest | Member | Librarian | Admin |
|---|---|---|---|---|
| Add item to shortlist | ❌ | ✅ | ❌ | ❌ |
| Remove item from shortlist | ❌ | ✅ | ❌ | ❌ |
| View own shortlist | ❌ | ✅ | ❌ | ❌ |
| View any user's shortlist | ❌ | ❌ | ❌ | ✅ |

### 5.4 Digital Materials Actions

| Action | Guest | Member | Librarian | Admin |
|---|---|---|---|---|
| View cover image | ✅ | ✅ | ✅ | ✅ |
| View limited preview | ✅ | ✅ | ✅ | ✅ |
| Read full digital material | ❌ | ✅ (if access flag set) | ✅ | ✅ |
| Download digital file | ❌ | ❌ | ❌ | ❌ |
| Upload cover image | ❌ | ❌ | ✅ | ✅ |
| Upload digital file | ❌ | ❌ | ✅ | ✅ |
| Set access flags | ❌ | ❌ | ✅ | ✅ |
| Delete digital file | ❌ | ❌ | ❌ | ✅ |

### 5.5 Catalog & Metadata Management

| Action | Guest | Member | Librarian | Admin |
|---|---|---|---|---|
| Add new bibliographic record | ❌ | ❌ | ✅ | ✅ |
| Edit bibliographic record | ❌ | ❌ | ✅ | ✅ |
| Delete bibliographic record | ❌ | ❌ | ❌ | ✅ |
| Add/edit copies/items | ❌ | ❌ | ✅ | ✅ |
| Delete copies/items | ❌ | ❌ | ❌ | ✅ |
| Merge duplicate records | ❌ | ❌ | ✅ | ✅ |
| Import data (MARC, CSV) | ❌ | ❌ | ✅ | ✅ |
| Data cleanup panel access | ❌ | ❌ | ✅ | ✅ |
| Edit UDC classification | ❌ | ❌ | ✅ | ✅ |

### 5.6 News & Announcements

| Action | Guest | Member | Librarian | Admin |
|---|---|---|---|---|
| Read news | ✅ | ✅ | ✅ | ✅ |
| Create news post | ❌ | ❌ | ✅ | ✅ |
| Edit own news post | ❌ | ❌ | ✅ | ✅ |
| Edit any news post | ❌ | ❌ | ❌ | ✅ |
| Delete news post | ❌ | ❌ | ❌ | ✅ |
| Publish/unpublish news | ❌ | ❌ | ✅ | ✅ |

### 5.7 Scientific Repository Actions

| Action | Guest | Member | Librarian | Admin |
|---|---|---|---|---|
| Browse repository metadata | ✅ | ✅ | ✅ | ✅ |
| Read full scientific work | ❌ | ✅ | ✅ | ✅ |
| Upload scientific work | ❌ | ❌ | ✅ | ✅ |
| Submit work for approval | ❌ | ❌ | ✅ | ✅ |
| Approve/reject submission | ❌ | ❌ | ✅ (moderate) | ✅ (final) |
| Publish approved work | ❌ | ❌ | ❌ | ✅ |
| Remove published work | ❌ | ❌ | ❌ | ✅ |

### 5.8 External Resources

| Action | Guest | Member | Librarian | Admin |
|---|---|---|---|---|
| View resource cards/descriptions | ✅ | ✅ | ✅ | ✅ |
| Use external resource link | ✅ (public) / conditional (licensed) | ✅ | ✅ | ✅ |
| Add/edit resource card | ❌ | ❌ | ❌ | ✅ |
| Delete resource card | ❌ | ❌ | ❌ | ✅ |

### 5.9 User & System Management

| Action | Guest | Member | Librarian | Admin |
|---|---|---|---|---|
| Manage users | ❌ | ❌ | ❌ | ✅ |
| Manage roles | ❌ | ❌ | ❌ | ✅ |
| View system logs | ❌ | ❌ | ❌ | ✅ |
| View integration status | ❌ | ❌ | ❌ | ✅ |
| System settings | ❌ | ❌ | ❌ | ✅ |

### 5.10 Reports & Analytics

| Action | Guest | Member | Librarian | Admin |
|---|---|---|---|---|
| View analytics dashboard | ❌ | ❌ | ✅ (library ops) | ✅ (full) |
| Export reports | ❌ | ❌ | ✅ | ✅ |
| View acquisition reports | ❌ | ❌ | ✅ | ✅ |
| View user activity reports | ❌ | ❌ | ✅ (aggregated) | ✅ (full detail) |

### 5.11 Contact / Messages

| Action | Guest | Member | Librarian | Admin |
|---|---|---|---|---|
| Submit contact message | ❌ | ✅ | ✅ | ✅ |
| View all received messages | ❌ | ❌ | ✅ | ✅ |
| Mark message as resolved | ❌ | ❌ | ✅ | ✅ |
| Delete message | ❌ | ❌ | ❌ | ✅ |

### 5.12 Fund / Branch / Location Management

| Action | Guest | Member | Librarian | Admin |
|---|---|---|---|---|
| View branch info | ✅ | ✅ | ✅ | ✅ |
| Manage branch/fund structure | ❌ | ��� | ❌ | ✅ |
| Assign copy to fund/branch | ❌ | ❌ | ✅ | ✅ |
| Manage sigla/storage codes | ❌ | ❌ | ✅ | ✅ |

---

## 6. Database & Data Architecture

### 6.1 Current Data State

- Data has already been migrated from MARC-SQL to **PostgreSQL**
- New schemas, entities, tables, and attributes exist
- BUT data quality is still poor — post-migration cleanup is an active ongoing process
- This is a **post-migration data quality improvement phase**

### 6.2 Data Quality Problems (Inherited)

Known issues from original MARC-SQL migration:
- Incorrect records
- Empty required fields
- Duplicate records
- Malformed metadata
- Bad author/title/UDC data
- Missing inventory links
- Missing or wrong barcodes
- No clean migration audit trail

### 6.3 Critical Preservation Rules

Must never be lost:
- Correct metadata structure
- Entity relationships (bibliographic record → edition → copy)
- Reporting compatibility
- Library meaning of records
- Analytical data integrity
- Operational reproducibility
- Change history (who changed what, when, why)
- Journaling / audit trail
- No data loss during any cleanup operation

### 6.4 Core Bibliographic Record Fields

| Field | Required | Notes |
|---|---|---|
| Control number | ✅ | Unique identifier |
| Correction date | ✅ | Last modified timestamp |
| Country code | ✅ | |
| Language | ✅ | kk / ru / en + others |
| UDC index | ✅ | Primary classification, shown on book cover UI |
| Catalog index | ✅ | |
| Author sign (Авторский знак) | ✅ | Хавкина table, shown on book cover UI |
| Main author | ✅ | |
| Additional authors | Optional | |
| Title | ✅ | |
| Physical medium | ✅ | |
| Additional notes | Optional | |
| Place of publication | ✅ | |
| Publisher | ✅ | |
| Year of publication | ✅ | |
| Series | Optional | |
| Annotation / abstract | Recommended | |
| Keywords | Recommended | |
| Main rubric | ✅ | |

### 6.5 Copy / Inventory Item Fields

| Field | Required | Notes |
|---|---|---|
| Type of accounting (Вид учёта) | ✅ | |
| Inventory number | ✅ | Key unit of accounting |
| Barcode | ✅ | Key operational identifier |
| Quantity | ✅ | |
| KSU record number | ✅ | Книга суммарного учёта |
| Sigla / storage code | ✅ | Location identifier |
| Price | ✅ | |
| Source of acquisition | ✅ | |
| Verification mark | ✅ | |
| Registration date | ✅ | |
| Fund ownership | ✅ | college / univ-eco / univ-tech / general |
| Branch / library point | ✅ | |
| Precise shelf location | ✅ | Стеллаж / полка |
| Notes | Optional | |

### 6.6 Entity Hierarchy

```
Bibliographic Record
  └── Document / Edition
        └── Copy / Item
              ├── Inventory Number
              ├── Barcode
              ├── Sigla / Storage Code
              ├── Branch / Library Point
              ├── Room / Shelf / Precise Location
              ├── Fund Ownership Tag
              ├── Availability Status
              └── Acquisition Metadata
```

### 6.7 Location & Fund Model

The university library has two physical locations:
- **Economic campus / library**
- **Technology campus / library**

There is also a **college fund** (historically connected, currently being separated from university fund).

Each copy must be tagged with:

| Field | Values / Notes |
|---|---|
| Fund type | college / university-economic / university-technology / general |
| Branch | Economic Campus Library / Technology Campus Library |
| Room/Cabinet | Specific room identifier |
| Shelf location | Precise стеллаж / полка reference |
| Source of acquisition | Purchase, donation, transfer, etc. |
| Reporting contour | For financial and reporting compatibility |

**What public users see:**
- Branch name
- Sigla
- Precise shelf location
- Availability status

**What staff additionally see:**
- Full inventory data
- Acquisition metadata
- Fund accounting details

---

## 7. Catalog & Search

### 7.1 Catalog Purpose

The catalog is the **core of the user-facing system.** It is a real entry point to the library fund, not a decorative list.

### 7.2 Book Card (Карточка книги)

Every book card must display:
- Title
- Author(s)
- Publisher
- Year
- Language
- **UDC index** (displayed prominently on/near the book cover UI element)
- **Author sign / Авторский знак** (displayed on the book cover UI element)
- Category / subject rubric
- Status (available / reserved / issued / etc.)
- Number of available copies
- Branch/library point per copy
- Sigla and precise shelf location per copy
- Digital material (cover image, controlled viewer link if permitted)
- Additional materials
- Related entities (series, related books)
- User actions: Reserve / Add to shortlist / Read (if permitted) / Request

### 7.3 Search Capabilities (Current)

| Feature | Status |
|---|---|
| Search by title | ✅ Required now |
| Search by author | ✅ Required now |
| Search by keywords | ✅ Required now |
| Search by metadata fields | ✅ Required now |
| Full-text search | ✅ Required now |
| Filter by language | ✅ Required now |
| Filter by year range | ✅ Required now |
| Filter by UDC | ✅ Required now |
| Filter by availability | ✅ Required now |
| Filter by branch/fund | ✅ Required now |
| Filter by resource type | ✅ Required now |
| Sort by relevance / year / title / author | ✅ Required now |
| Faceted sidebar filters | ✅ Required now |

### 7.4 Search Capabilities (Future Roadmap)

- Autocomplete / suggestions
- Typo correction / fuzzy matching
- Related books recommendations
- AI-assisted intelligent search layer

### 7.5 Catalog UX Requirements

- Modern, clean, clear design
- Fast and responsive filtering
- No decorative-only elements — every element must be functional
- Real search results from real data
- Clear availability indicators (green/yellow/red status chips)
- Filter state persistence during session
- Accessible on all screen sizes
- Clear separation between local fund, digital materials, and external resources

---

## 8. UDC — Universal Decimal Classification

### 8.1 UDC as Primary Discovery Axis

UDC is **the primary semantic and navigation axis** for this project.
It is not just a metadata field — it is the backbone of resource discovery.

Through UDC the system builds:
- Thematic sections
- Intelligent navigation
- Recommended collections
- Teacher-facing resource discovery
- Subject-based literature selection
- Related resource filters
- Future: semi-automatic syllabus-oriented discovery

### 8.2 UDC Reference Database

Available: **126,441 UDC codes** as a reference database.

| Top Block | Area |
|---|---|
| 00 | General science / IT |
| 33 | Economics |
| 34 | Law |
| 37 | Education |
| 5 | Natural sciences |
| 6 | Applied sciences / Technology |
| 7 | Arts |
| 8 | Language / Literature |
| 9 | Geography / History |

### 8.3 UDC on Book Cover UI

Both **UDC index** and **Авторский знак (author sign)** must be visually displayed on the book cover/card element in the UI, as they are in physical library cataloging practice.

---

## 9. Faculty & Department Structure

### 9.1 Navigation Model

| Layer | Priority |
|---|---|
| UDC-based discovery | **Primary** |
| Faculty → Department → UDC → Books | Secondary (browsable entry point) |

Faculty/Department is a **visible secondary browsing entry point**, not the main navigation tree.

### 9.2 University Faculty & Department Structure

**Технологический факультет**
- Кафедра «Технология и стандартизация»
- Кафедра «Технология лёгкой промышленности и дизайна»
- Кафедра «Социально-гуманитарные дисциплины»

**Факультет экономики и бизнеса**
- Кафедра «Туризм и сервис»
- Кафедра «Экономика и управление»
- Кафедра «Финансы и учёт»
- Кафедра «Государственный и иностранные языки»

**Факультет инжиниринга и информационных технологий**
- Кафедра «Информационные технологии»
- Кафедра «Компьютерная инженерия и автоматизация»
- Кафедра «Химия, химическая технология и экология»

**Военная кафедра**

Each faculty/department page or filter leads to relevant UDC codes and associated books.

### 9.3 Knowledge Map & Current Directions Requirement

The public discovery layer must provide a **knowledge map** experience that is visually clear and meaningful, not only a flat filter list.

Required behavior:
- Users can enter discovery through faculty/department context and then pivot to UDC-aligned collections.
- The interface should expose "current directions" (актуальные направления) per faculty/department so users quickly understand where to start.
- Knowledge-map navigation remains a secondary axis under UDC primary discovery, but it must be first-class in UX quality and clarity.
- This requirement is product-level truth and must be carried into future public discovery implementations.

---

## 10. Personal Literature Shortlist

### 10.1 Who Has This Feature

**All ordinary users (student, teacher, employee).**
Librarians and admins do **NOT** have this feature.

### 10.2 What It Is

This is **NOT** a formal syllabus module. No approval workflow. No submission process.

It works exactly like a **wishlist / saved items / draft reading list:**
- User adds any book or external resource to their personal list
- User removes items from the list
- User views the list in their personal cabinet
- Used informally to draft reading lists, research shortlists, or syllabi outlines
- Personal, non-binding, informal

### 10.3 Displayed In

Member dashboard section:
> 📋 Моя подборка литературы
> Сохранённые книги и ресурсы.

---

## 11. Personal Cabinet (Member Dashboard)

### 11.1 One Shared Dashboard

One shared member dashboard for all ordinary users with conditional content blocks.

### 11.2 Dashboard Sections

| Section | Visible to |
|---|---|
| User profile (name, department, email, role from AD) | All members |
| Active reservations with statuses | All members |
| Borrowing history | All members |
| Personal literature shortlist | All members |
| Accessible digital materials | All members |
| Notifications feed | All members |
| Quick access to catalog | All members |
| Quick access to external resources | All members |
| Contact/feedback link | All members |

---

## 12. Reservation Policy & Rules

### 12.1 Business Rules

| Rule | Value |
|---|---|
| Maximum active reservations per user | 3 (configurable by admin) |
| Can user reserve an unavailable copy? | No — only available copies can be reserved |
| Reservation level | Title-level OR specific copy — librarian confirms exact copy |
| Reservation lifespan | 3 days after confirmation (configurable) |
| Auto-expire? | Yes — auto-expires if not picked up within lifespan |
| Can user cancel their own reservation? | Yes, anytime before confirmation |
| Can librarian cancel any reservation? | Yes |
| Different limits for teachers? | Currently no — same scope |
| Can librarian override limits? | Yes |
| Notification on reservation update? | Yes — see Notification Model |

### 12.2 Reservation Status Flow

```
[pending] → [confirmed] → [ready for pickup] → [fulfilled]
                       ↘ [cancelled]
[pending/confirmed] → [expired] (auto after deadline)
```

---

## 13. Loan / Circulation Policy & Rules

### 13.1 Business Rules

| Rule | Value |
|---|---|
| Standard loan period | 14 days (configurable by admin) |
| Loan period for reference materials | 1 day (in-library only, configurable) |
| Maximum active loans per user | 5 items (configurable by admin) |
| Can user renew a loan? | Yes — once, if no active reservation on that copy |
| Renewal period | Same as original loan period |
| Overdue tracking | Yes — system tracks overdue days |
| Debt/fine system | Recorded — fine policy configurable by admin |
| Blocking on overdue? | Yes — user cannot reserve new items if overdue loans exist |
| Can librarian override block? | Yes |
| Different rules per fund type? | Configurable — reference vs lending fund can have different rules |
| Can digital items be "loaned" separately? | No — digital access is access-flag based, not circulation-based |

### 13.2 Circulation Status Flow

```
Copy: [available] → [reserved] → [issued] → [available]
                              ↘ [overdue] → [returned]
```

---

## 14. Status Dictionaries

### 14.1 Copy / Item Status

| Status | Meaning |
|---|---|
| `available` | Copy is on shelf, can be borrowed/reserved |
| `reserved` | Copy is reserved by a user, awaiting pickup |
| `issued` | Copy is currently borrowed by a user |
| `overdue` | Copy was issued and return deadline has passed |
| `in_processing` | Copy is being cataloged, labeled, or processed |
| `lost` | Copy reported as lost |
| `under_repair` | Copy sent for binding/repair |
| `digitization_pending` | Copy sent for scanning |
| `restricted` | Copy access restricted (special collection, reference-only) |
| `archived` | Copy removed from active fund but retained in system |

### 14.2 Reservation Status

| Status | Meaning |
|---|---|
| `pending` | Submitted by user, awaiting librarian confirmation |
| `confirmed` | Confirmed by librarian, copy being prepared |
| `ready_for_pickup` | Copy available at desk, awaiting user |
| `fulfilled` | User picked up the copy, reservation closed |
| `cancelled` | Cancelled by user or librarian |
| `expired` | User did not pick up within deadline, auto-expired |

### 14.3 Scientific Work Status

| Status | Meaning |
|---|---|
| `draft` | Uploaded by librarian, not yet submitted |
| `under_review` | Submitted for review by library head / admin |
| `approved` | Approved by admin/owner |
| `rejected` | Rejected — librarian notified |
| `published` | Publicly visible to all authenticated users |
| `archived` | Removed from active view but retained |

### 14.4 News / Announcement Status

| Status | Meaning |
|---|---|
| `draft` | Created but not published |
| `scheduled` | Set to publish at a future date/time |
| `published` | Visible to all users |
| `archived` | Removed from active feed but retained |

### 14.5 Contact Message Status

| Status | Meaning |
|---|---|
| `open` | Newly received, unread |
| `in_review` | Being handled by librarian/admin |
| `resolved` | Issue addressed, closed |
| `archived` | Archived for record-keeping |

### 14.6 Digital Material Access Level

| Level | Meaning |
|---|---|
| `none` | No digital material attached |
| `cover_only` | Only cover image available |
| `preview` | Limited preview accessible to guests and members |
| `member_access` | Full read access for authenticated members |
| `staff_only` | Only librarian/admin can read |
| `restricted` | Access restricted by rights/policy |

---

## 15. Reservation, Issue & Return Workflows

### 15.1 Reservation Workflow

```
Member submits reservation
  → Status: pending
  → Librarian notified
  → Librarian confirms + assigns copy
  → Status: confirmed → ready_for_pickup
  → Member notified
  → Member picks up at desk
  → Librarian issues copy
  → Status: fulfilled
  → Copy status: issued
```

### 15.2 Issue (Выдача) Workflow

```
Librarian opens issue panel
  → Finds user (by name, ID, barcode scan)
  → Finds copy (by barcode or inventory number)
  → Confirms issue
  → Copy status: issued
  → Loan record created with due date
  → User history updated
  → Librarian action logged in audit trail
```

### 15.3 Return (Возврат) Workflow

```
Librarian opens return panel
  → Scans barcode or enters inventory number
  → System identifies copy + current borrower
  → Confirms return
  → Copy status: available
  → Loan record closed
  → User history updated
  → Librarian action logged in audit trail
  → If overdue: debt/fine note added
```

### 15.4 QR Code Support

QR code support is implemented for physical issue/return operations where it improves convenience. Each copy can have a printable QR code linked to its inventory number/barcode for quick scanning.

---

## 16. News & Announcements Module

### 16.1 Purpose

A managed content module for library communications. Controlled by librarians and admins.

### 16.2 Content Types

- Events (ивенты)
- Announcements
- Updates / Changes
- Schedules and meeting notices
- Other library communications

### 16.3 Who Controls It

| Action | Librarian | Admin |
|---|---|---|
| Create post | ✅ | ✅ |
| Edit own post | ✅ | ✅ |
| Edit any post | ❌ | ✅ |
| Publish/unpublish | ✅ | ✅ |
| Delete | ❌ | ✅ |
| Schedule post | ✅ | ✅ |

Ordinary users and guests: **read only.**

### 16.4 Where It Appears

- Homepage (dedicated news/announcements section)
- Separate `/news` page
- Member dashboard notifications feed

### 16.5 Boundary with Events Module

News and events are related but **not the same module**.

- News/announcements remain editorial communication posts.
- Events/calendar is a distinct planned public surface with its own listing and detail flow.
- Event content may be referenced from news, but events are not to be blindly merged into the news module.

---

## 17. Homepage

### 17.1 Philosophy

The homepage must create **maximum wow effect** and attract users.
It is the face of the new library platform.

### 17.2 Required Sections

1. **Hero section** — strong visual, prominent search bar, main CTA (Login / Browse Catalog)
2. **Global search / discovery entry** — fast, prominent, smart
3. **Featured collections** — by UDC subject, new arrivals, popular books
4. **News / Announcements** — recent posts from librarians
5. **External resources highlights** — key licensed and open platforms
6. **Library services** — what the library offers
7. **Branch / location info** — where the libraries are physically
8. **CTA for login / member tools** — personalized experience prompt
9. **Institutional trust / metrics** — e.g. "X books in the fund, Y resources available"
10. **Quick links** — catalog, repository, resources, contact
11. **Latest additions / New arrivals** — recently added materials and collections with clear freshness signals

### 17.4 Latest Arrivals Requirement

The homepage/public layer must explicitly surface **recently added content**.

Minimum requirement:
- Dedicated "Latest Arrivals" (or equivalent) block on homepage and/or discover entry.
- Items should represent recent additions to local collections, digital materials, or repository-adjacent holdings.
- This is a core public-facing feature, not an optional decorative widget.

### 17.3 Visual & Animation Strategy

**Current phase (must deliver now):**
- Premium, clean, modern design
- Strong typography and spacing
- High-quality structure
- Placeholder architecture for visual richness
- No heavy motion-dependent functionality

**Future enhancement phase:**
- Background video animation
- 3D entrance and scroll animations
- Motion design pass throughout
- Rich interactive UI elements
- Full immersive visual experience

**Rule:** Base functionality must never depend on animations. Animations are an enhancement layer only.

---

## 18. External Resources Module

### 18.1 Philosophy

External resources are **not just a list of links.**
This is a full, guided, informative page where users understand:
- What each resource is
- What it contains
- Who it is for
- How to access it
- What credentials are needed
- When access is valid (if applicable)
- Whether it is licensed, open, or partner

### 18.2 Visibility

- Visible to **all users including guests**
- Public metadata, descriptions, and general access info: everyone
- Actual access to licensed resources may require institutional login
- Clear visual distinction between public-access and auth-required resources

### 18.3 Resource Types

| Type | Description |
|---|---|
| **Licensed / Contracted** | Resources with active university contract (e.g. IPR SMART until 09.09.2026) |
| **Open Access** | Free resources, no fee or registration required |
| **Partner / Integration** | Partner platforms with institutional-level access |

### 18.4 Resource Card Content

Each external resource card must include:
- Resource name + logo
- Description (what it is, what it contains, what it's useful for)
- Resource type label (Licensed / Open / Partner)
- Subject areas / UDC-aligned topics
- Who can use it (all members / staff only / specific faculties)
- How to access (direct link / institutional login / specific credentials)
- Access validity period if applicable (badge: "Valid until DD.MM.YYYY")
- "Go to resource" button/link

### 18.5 Contract Awareness

The system must respect:
- License terms
- Access validity periods
- Usage restrictions per agreement
- Clear distinction between owned fund and licensed external content

Future: deeper contract-aware management module with expiry alerts.

### 18.6 Public Collection Narrative (Fund Content Truth)

The platform must preserve and communicate the library's major fund categories as public domain truth.

Core categories:
- **Негізгі қор** — the main physical collection foundation for учебная/научная литература.
- **Электрондық қор** (including МЭҚҚ / ҚазҰЭК context) — structured electronic holdings and partner-connected digital access layers.
- **Ресми және нормативтік басылымдар қоры** — official and regulatory publications used in governance/compliance-oriented study.
- **Диссертациялар мен авторефераттар қоры** — dissertations and author abstracts for advanced academic research.
- **Сирек кітаптар қоры** — rare books collection of high heritage and research value.

These are large, meaningful public-facing collection categories and must be reflected in informational and discovery surfaces.

---

## 19. Digital Materials & Controlled Viewer

### 19.1 Current State

- Library currently has digital versions for approximately **~100 books** (very small number)
- A scanner is available for digitizing physical books
- This number will grow gradually

### 19.2 Core Rules (Non-Negotiable)

| Rule | Value |
|---|---|
| Users can download digital files freely | ❌ NEVER |
| Direct file URLs are exposed | ❌ NEVER |
| Text selection/copying allowed | ❌ Restricted as much as technically possible |
| Full read requires auth | ✅ Always |
| Access is role-based | ✅ Always |
| Guests see cover + limited preview only | ✅ Always |

### 19.3 Access Level by Role

| User Type | Cover | Preview | Full Read |
|---|---|---|---|
| Guest | ✅ | Limited | ❌ |
| Member | ✅ | ✅ | Only if access flag = `member_access` |
| Librarian | ✅ | ✅ | ✅ |
| Admin | ✅ | ✅ | ✅ |

### 19.4 Current Phase Deliverables

Build **foundation and architecture** now:
- File storage structure (protected bucket, no public URLs)
- Metadata model for digital items (title, file ref, access level, upload date, uploader)
- Access flag system
- Cover image upload by librarian
- Digital version upload by librarian
- Basic controlled viewer skeleton (iframe-based or custom viewer with disabled right-click, download block, copy block)
- Watermarking infrastructure (to be enabled in future phase)

Full protected digital reading flow = **future phase**, but architecture must support it.

### 19.5 Librarian Capabilities

- Upload cover images
- Upload digital file versions
- Set access level flag per item
- View list of digitized items

---

## 20. Scientific Works / Thesis / Dissertation Module

### 20.1 Status

This is **current design scope.** Must be designed and implemented now, not deferred.

### 20.2 Who Can Access

| Access Type | Who |
|---|---|
| Browse metadata | All users (including guests) |
| Read full work | All AD-authenticated users (members + staff) |
| Guests (non-AD) | Metadata only, no full read |

### 20.3 Work Types

- Bachelor's thesis / Diploma work
- Master's dissertation
- PhD dissertation
- Scientific article / paper
- Research report
- University journal publication

### 20.4 Upload & Approval Workflow

```
Author (student/teacher/staff) agrees with librarian
  ��� Librarian uploads the work on behalf of author
  → Librarian fills metadata: title, author, year, department, UDC, type, abstract, keywords
  → Work status: draft
  → Librarian submits for approval
  → Work status: under_review
  → Library head / admin reviews
  → Approved → status: approved → admin publishes → status: published
  → OR Rejected → librarian notified, can revise and resubmit
```

### 20.5 Access Control

| Feature | Value |
|---|---|
| Guest access | Metadata browse only |
| Member access | Full read via controlled viewer |
| Free download | ❌ NEVER |
| Upload by author directly | ❌ Never — always through librarian |
| Librarian moderation | ✅ Required |
| Admin/owner final approval | ✅ Required |
| Published work removal | Admin only |

### 20.6 Metadata Fields per Work

- Title
- Author(s)
- Type (thesis / dissertation / article / etc.)
- Year
- Department / Faculty
- UDC code
- Abstract
- Keywords
- Language
- File (protected, no direct URL)
- Upload date
- Uploaded by (librarian)
- Approved by (admin)
- Publication date

---

## 21. Contact / Feedback Module

### 21.1 Purpose

Authenticated users can send messages, requests, complaints, or improvement suggestions to the **library head (Жанерке Панкейкызы)** directly from the platform.

### 21.2 Access Rules

| Who | Can Submit |
|---|---|
| Guest | ❌ No |
| Member (student/teacher/employee) | ✅ Yes |
| Librarian | ✅ Yes |
| Admin | ✅ Yes |

### 21.3 Mechanics

1. User opens contact form (section/page/modal)
2. Selects message category
3. Writes message
4. Submits
5. Message sent via user's corporate email (from their AD email automatically)
6. Email arrives to library head with subject prefix tag for categorization
7. Message stored in library system for librarian/admin review

### 21.4 Message Categories

- 📥 Запрос / Request
- 🚨 Жалоба / Complaint
- 💡 Предложение по улучшению / Improvement suggestion
- ❓ Вопрос / Question
- 📋 Другое / Other

### 21.5 Message Status Workflow

```
[open] → [in_review] → [resolved]
                     ↘ [archived]
```

### 21.6 Visibility & Response

| Feature | Value |
|---|---|
| Visible in librarian panel | ✅ Yes — full inbox |
| Visible in admin panel | ✅ Yes — full inbox |
| Can librarian mark as resolved | ✅ Yes |
| Can admin delete messages | ✅ Yes |
| Can user see their submitted messages | ✅ Yes — in member dashboard |
| Email response from library head | Manual (outside system, via email) |
| In-app response to user | Future enhancement |

### 21.7 Public Location & Wayfinding Requirement

The public informational layer must clearly answer **how to find the library** and where physical collections are located.

Required location truth:
- `1/200` — технологический фонд
- `1/202` — фонд колледжа
- `1/203` — экономический фонд библиотеки

A public map/location component is required in the future contact/location implementation.

### 21.8 Public Informational Pages (Standalone)

The public informational layer includes dedicated standalone pages:
- **Library Leadership / Руководство библиотеки** — public-facing leadership and administration presentation.
- **Library Usage Rules / Правила пользования библиотекой** — clear reader policy and usage rules.

These are planned as first-class public surfaces, not minor subsections hidden inside unrelated pages.

---

## 22. Analytics & Reporting

### 22.1 No Separate Analyst Role

No "Analyst" user role exists. Analytics is a **functional layer** built into librarian and admin panels.

### 22.2 Librarian Analytics

- Most popular books (by issue count, by reservation count)
- User activity statistics (aggregated)
- Fund usage statistics (by branch, by UDC, by fund type)
- Process dynamics (issues/returns/reservations per day/week/month)
- Monthly reports
- Annual reports
- New arrivals reports
- Invoice/acquisition reports (накладные)
- Library data reconciliation reports
- Fund-level analytics (college vs university, by branch)

### 22.3 Admin Analytics

All librarian analytics plus:
- System-wide user activity (by role, by department)
- Role distribution reports
- Integration status monitoring
- Cross-fund and cross-branch comparative reports
- Scientific repository activity
- News engagement metrics
- Contact message volume and resolution rate

### 22.4 Report Export Requirements

| Feature | Value |
|---|---|
| Export to PDF | ✅ Required |
| Export to Excel/CSV | ✅ Required |
| Filterable by time period | ✅ Required |
| Filterable by branch/fund | ✅ Required |
| Compatible with existing reporting practice | ✅ Required |
| Comparison with accounting data | Optional — configurable |

---

## 23. Library Operational Workflows

### 23.1 Cataloging & Acquisition (Комплектование и каталогизация)

- Add new bibliographic records
- Edit existing records
- Manage editions
- Manage copies/items with full inventory fields
- UDC classification
- Author sign calculation (Хавкина table — auto-calculate)
- Barcode assignment
- KSU (Книга суммарного учёта) management
- Fund movement tracking
- New acquisitions processing (накладная / invoice)

### 23.2 Reader Services (Отдел обслуживания)

- Issue books (выдача)
- Return books (возврат)
- Log library visits
- Book replacement notes (тетрадь замены)
- Bypass sheet tracking (обходной лист)
- Reservation processing

### 23.3 Reporting (Отчётность)

- Book summary accounting (Книга суммарного учёта)
- Inventory accounting reports
- Faculty/department-linked reports
- Fund-by-branch reports
- Annual reconciliation
- Statistical usage reports

---

## 24. Data Cleanup Panel

### 24.1 Purpose

Given the poor data quality inherited from MARC-SQL, the system must have a **dedicated data management and anomaly correction panel** for librarians and admins.

### 24.2 Capabilities

- View all records with detected anomalies (empty required fields, suspected duplicates, malformed data)
- Filter records by anomaly type:
  - Empty title
  - Empty UDC
  - No copies linked
  - Missing author
  - Missing year
  - Suspected duplicate
  - Invalid barcode format
  - Other
- Inline editing of records directly from the panel
- Bulk correction tools for common issues
- Duplicate detection with merge capability
- Validation rules display (what's wrong and why)
- Change history visible per record
- AI-assisted correction suggestions (architecture ready now, full feature = future)

### 24.3 Critical Rules

| Rule | Value |
|---|---|
| No data loss during cleanup | ✅ Mandatory |
| Every change logged in audit trail | ✅ Mandatory |
| Rollback capability for critical fields | ✅ Mandatory |
| Library meaning of records preserved | ✅ Mandatory |
| Bulk operations require confirmation step | ✅ Mandatory |

---

## 25. Notification Model

### 25.1 Channels

| Channel | Status |
|---|---|
| In-app notifications | ✅ Required now |
| Email notifications | ✅ Required now (using user's corporate email from AD) |

### 25.2 Notification Events

| Event | Who Gets Notified | Channel |
|---|---|---|
| Reservation created | Member (confirmation) | In-app + Email |
| Reservation confirmed by librarian | Member | In-app + Email |
| Reservation ready for pickup | Member | In-app + Email |
| Reservation expired (auto) | Member | In-app + Email |
| Reservation cancelled by librarian | Member | In-app + Email |
| Book due soon (2 days before) | Member | In-app + Email |
| Book overdue | Member | In-app + Email |
| Loan renewed | Member | In-app |
| New digital material access granted | Member | In-app |
| Scientific work approved/rejected | Librarian who uploaded | In-app + Email |
| Scientific work published | Librarian + Author (if email stored) | Email |
| New news/announcement published | All members (optional — configurable) | In-app |
| New contact message received | Librarian + Admin | In-app |
| Message status changed | Member who submitted | In-app |

### 25.3 Notification Management

- Users can view all notifications in their dashboard
- Users can mark notifications as read
- Admin can configure which notification types generate emails

---

## 26. Audit Log Scope

### 26.1 What Must Be Logged (Mandatory)

| Category | Events to Log |
|---|---|
| **Authentication** | Login (success/fail), Logout, Token refresh |
| **Reservations** | Create, Confirm, Cancel, Expire, Fulfill |
| **Circulation** | Issue, Return, Renew, Overdue mark |
| **Metadata edits** | Any field change on bibliographic record (before/after values) |
| **Copy edits** | Any field change on copy/item record |
| **Duplicate merge** | Which records merged, by whom |
| **Digital materials** | Upload, Access flag change, Delete |
| **Repository** | Upload, Submit, Approve, Reject, Publish, Remove |
| **News** | Create, Edit, Publish, Delete |
| **User management** | Create user, Edit role, Deactivate user |
| **External resources** | Add, Edit, Delete resource card |
| **Settings changes** | Any system setting change |
| **Contact messages** | Received, Status change |
| **Reports** | Export events (who, when, what report) |
| **Data cleanup** | Record corrections, bulk operations |

### 26.2 Log Entry Structure

Each log entry must capture:

| Field | Required |
|---|---|
| Actor (who) | ✅ user ID + name + role |
| Timestamp (when) | ✅ UTC datetime |
| Action type | ✅ |
| Entity type | ✅ (book, copy, user, reservation, etc.) |
| Entity ID | ✅ |
| Previous value | ✅ for edits |
| New value | ✅ for edits |
| IP address | ✅ |
| Reason / comment | Optional (required for sensitive actions like delete/merge) |

### 26.3 Log Access

| Who | Access Level |
|---|---|
| Admin | Full audit log access, filterable, exportable |
| Librarian | Own actions + library operations log |
| Member | Own activity only (in dashboard history) |
| Guest | No log access |

---

## 27. File & Storage Model

### 27.1 File Categories

| Category | Description | Access |
|---|---|---|
| Book cover images | Cover photos/scans for books | Public (no auth required) |
| Protected digital book files | Full digital versions of books | Protected — never public URL |
| Scientific work files | Uploaded theses/dissertations | Protected — auth required |
| Report exports | PDF/Excel generated reports | Staff only — session-based |
| Import files | CSV/MARC import files | Staff only |

### 27.2 Storage Rules

| Rule | Value |
|---|---|
| Public covers stored in | Public bucket / public path |
| Protected digital files stored in | Private bucket — no direct URL access |
| File served via | Signed URL or streaming proxy (never direct link) |
| Scientific work files stored in | Private bucket |
| Naming convention | `{entity_type}/{entity_id}/{timestamp}_{filename}` |
| Duplicate uploads | Versioned — old version retained |
| Deletion policy | Soft delete only (admin hard delete) |
| Retention policy | Retain all files including deleted (archive) |
| Preview/thumbnail generation | Required for covers, optional for documents |
| Watermarking | Architecture ready now, feature enabled in future phase |

### 27.3 Controlled Viewer Architecture

- Protected files are **never served via direct URL**
- Viewer loads file content via authenticated API endpoint
- Viewer is rendered in a sandboxed iframe or custom reader component
- Right-click disabled
- Download button disabled
- Keyboard shortcuts for save/print disabled where possible
- Copy/paste of text restricted
- URL of the actual file is never exposed to the client browser

---

## 28. Multilingual / Content Language Policy

### 28.1 UI Languages

| Language | Status |
|---|---|
| Russian (ru) | ✅ Primary UI language |
| Kazakh (kk) | ✅ Required (secondary) |
| English (en) | ✅ Required (tertiary) |

Language switcher must be visible on all pages.

### 28.2 Metadata Language Policy

| Field | Multilingual? |
|---|---|
| Book title | Yes — can have kk, ru, en versions |
| Author name | Yes |
| Annotation/abstract | Yes — multilingual versions preferred |
| Keywords | Yes — multilingual |
| Publisher | As stored in record |
| UDC description | Pulled from UDC reference in UI language |
| News/announcements | Written in chosen language by librarian — not auto-translated |
| External resource descriptions | Multilingual preferred, minimum ru |

### 28.3 Search Language Policy

- Search must work across all three languages
- Cyrillic (ru/kk) and Latin (en) queries both supported
- No forced transliteration — search as typed
- Future: cross-language search suggestions

### 28.4 Default Language

- Default: Russian
- User language preference saved to session/profile
- Guest language: auto-detect from browser, default to Russian if unclear

---

## 29. Search Ranking & Result Presentation Rules

### 29.1 Default Ranking Logic

| Priority | Signal |
|---|---|
| 1st | Exact title match |
| 2nd | Exact author match |
| 3rd | Keyword match |
| 4th | UDC / subject rubric match |
| 5th | Full-text / annotation match |
| Boost | Available copies (available items ranked above unavailable) |
| Boost | Exact phrase match > partial match |

### 29.2 Result Presentation Rules

| Rule | Value |
|---|---|
| Default sort | Relevance |
| Alternative sorts | Year (new → old), Year (old → new), Title A-Z, Author A-Z |
| Results per page | 20 (configurable in settings) |
| Show availability on result card | ✅ Yes |
| Show cover thumbnail on result card | ✅ Yes |
| Show UDC on result card | ✅ Yes (small badge) |
| Show branch on result card | ✅ Yes |
| Local fund vs external resources | Separated — tabs or clear section labels |
| Repository works in global search | Optional separate tab, not mixed with books |
| News in global search | No — news has its own page |
| Filter state shown as active tags | ✅ Yes (removable filter chips) |
| Empty state message | Clear, helpful, with suggestions |

---

## 30. Module Boundaries & Page Map

### 30.1 Public Module

```
/                       → Homepage
/catalog                → Catalog / Search
/catalog/{id}           → Book detail card
/resources              → External resources
/news                   → News & Announcements
/news/{id}              → Single news post
/events                 → Events calendar/list (distinct from news)
/events/{id}            → Single event detail
/leadership             → Library Leadership / Руководство библиотеки
/rules                  → Library Usage Rules / Правила пользования библиотекой
/login                  → Login page
/repository             → Scientific works repository (metadata browsable by all)
/repository/{id}        → Single scientific work metadata page
```

### 30.2 Member Module (Auth Required)

```
/dashboard                          → Member dashboard (overview)
/dashboard/reservations             → My reservations + statuses
/dashboard/history                  → My borrowing history
/dashboard/list                     → My personal literature shortlist
/dashboard/notifications            → My notifications
/dashboard/contact                  → Contact/feedback form
/dashboard/messages                 → My submitted messages + statuses
/repository/{id}/read               → Read scientific work (controlled viewer)
/catalog/{id}/read                  → Read digital book (controlled viewer, if access granted)
```

### 30.3 Librarian Module (Librarian Auth Required)

```
/librarian                          → Librarian dashboard + key metrics
/librarian/catalog                  → Bibliographic records management
/librarian/catalog/create           → Add new record
/librarian/catalog/{id}/edit        → Edit record
/librarian/copies                   → Copy/item management
/librarian/copies/{id}              → Single copy detail + edit
/librarian/issue                    → Issue a book (выдача)
/librarian/return                   → Return a book (возврат)
/librarian/reservations             → Reservation queue + processing
/librarian/import                   → Data import (MARC, CSV)
/librarian/data-cleanup             → Data anomaly correction panel
/librarian/news                     → News/announcements management
/librarian/news/create              → Create new post
/librarian/news/{id}/edit           → Edit post
/librarian/repository               → Scientific works moderation queue
/librarian/repository/{id}/review   → Review single submission
/librarian/reports                  → Reports and analytics dashboard
/librarian/reports/{type}           → Specific report view + export
/librarian/messages                 → User messages / contact inbox
/librarian/external-resources       → View external resources (read-only)
```

### 30.4 Admin Module (Admin Auth Required)

```
/admin                              → Admin dashboard + system overview
/admin/users                        → User management (list, view, edit roles)
/admin/roles                        → Role management
/admin/settings                     → System settings (loan limits, reservation limits, etc.)
/admin/logs                         → Full audit log viewer
/admin/integrations                 → CRM integration status + monitoring
/admin/external-resources           → External resource cards management
/admin/external-resources/create    → Add resource card
/admin/external-resources/{id}/edit → Edit resource card
/admin/news                         → Full news management (all posts)
/admin/reports                      → System-wide analytics and reports
/admin/reports/{type}               → Specific report
/admin/messages                     → All contact messages
/admin/repository                   → Full scientific repository control (approve/publish/remove)
/admin/data-cleanup                 → Full data cleanup access
/admin/branches                     → Branch and fund structure management
```

---

## 31. Technical Considerations

### 31.1 Core Stack

| Layer | Technology |
|---|---|
| Backend | Laravel (PHP) |
| Frontend | Blade templates + HTML/CSS/JS from design export |
| Database | PostgreSQL |
| Authentication | CRM API → Bearer Token (LDAP/AD) |
| File Storage | Local or S3-compatible storage (protected buckets) |
| Search | PostgreSQL full-text search (Elasticsearch as future upgrade path) |
| Queue | Laravel Queue (for emails, notifications, report generation) |
| Cache | Redis (for session, token cache, search cache) |

### 31.2 Removed / Deprecated

**`athenaeum_digital`** — This is old garbage from a prior system. It must be completely and permanently removed from the codebase. It is not used and serves no purpose in the new system.

### 31.3 Implementation Discovery Process

When integrating exported HTML into Blade templates, the implementing agent must:

1. Identify and remove all UI elements that do not correspond to actual platform features (as defined in this document)
2. Identify missing functional components for each role/page
3. Verify which routes, Blade files, and layouts are affected by each design page
4. Clarify placeholder content vs real content sections
5. Report and resolve conflicts between design export and product decisions in this document
6. Document any UX elements from design that conflict with product decisions
7. This verification happens **during implementation**, not before or after

### 31.4 API Design Principles

- RESTful JSON API
- All protected endpoints require `Authorization: Bearer <token>` header
- Token validated against CRM `/api/me` on each request (or cached with TTL)
- All write operations return the updated resource
- All list endpoints support pagination, filtering, sorting
- Consistent error response format: `{ "error": "...", "message": "...", "code": "..." }`
- All endpoints that modify data produce an audit log entry

### 31.5 Design Reference Interpretation Rule

Externally provided example pages, screenshots, and exports are **inspiration/reference only**.

They are not canonical product truth by themselves. Canonical truth is defined by this document and ratified decision memory.

Implementation rule:
- Use external references to inform direction and quality.
- Do not copy them blindly or treat them as immutable product contracts.
- Prefer cohesive KazUTB Smart Library product integrity over one-to-one mimicry.

---

## 32. Key Decisions Summary Table

| Decision | Final Value |
|---|---|
| Primary system | Library platform |
| CRM role | Auth provider + parallel ecosystem only |
| Auth model | Library UI → CRM API → LDAP/AD → Bearer Token |
| Login location | Library login page (no CRM redirect) |
| Token storage | httpOnly cookie or secure server session — never localStorage |
| Guest access | Public catalog, metadata, availability, resources — read only |
| Ordinary user scope | Student = Teacher = Employee (exactly same scope) |
| Staff roles | Librarian + Admin |
| Literature shortlist | All ordinary users (not librarian, not admin) |
| Shortlist nature | Wishlist/saved items — not a formal syllabus module |
| Primary discovery axis | UDC |
| Secondary discovery | Faculty → Department → UDC |
| Faculty structure | Stored and browsable as secondary navigation layer |
| News module | Yes — managed by librarians/admins |
| Homepage | Full featured, high visual impact, all key sections |
| Animations | Premium structure now, motion/3D as later enhancement |
| Digital materials | Controlled viewer, no download, role-based, foundation now |
| Scientific works module | Current scope — librarian upload, admin approval, auth users read |
| Scientific works guests | Metadata only — no full read |
| Contact form | Authenticated users only — sends to library head email with tag |
| Contact messages visible to | Both librarian panel and admin panel |
| Analyst role | Does not exist — analytics is functional layer only |
| Approval workflow (general) | Later (not current scope) |
| athenaeum_digital | Permanently deleted — it is garbage |
| Faculty nav | Secondary, not primary |
| Production readiness | Production-oriented from day one |
| API strategy | Library provides APIs — CRM consumes them |
| Library full UI | Always maintained internally regardless of CRM panels |
| Data cleanup | Dedicated panel for librarians/admins |
| Audit log | Required for all data-modifying operations |
| External resources | Public to all — full guided informational page |
| Location/shelf info | Visible to all users including guests |
| QR codes | Supported for issue/return |
| College fund separation | Tracked via ownership/fund type fields |
| Max active reservations | 3 per user (admin configurable) |
| Reservation lifespan | 3 days after confirmation (admin configurable) |
| Max active loans | 5 per user (admin configurable) |
| Standard loan period | 14 days (admin configurable) |
| Loan renewal | Once allowed if no active reservation on copy |
| Overdue blocking | User blocked from new reservations if overdue loans exist |
| Primary UI language | Russian |
| Required UI languages | Russian, Kazakh, English |
| Search languages | All three — Cyrillic + Latin |
| File protection | All digital content served via authenticated proxy — no direct URLs |
| Watermarking | Architecture ready now, feature = future |
| Notification channels | In-app + Email (corporate AD email) |

---

## 33. Non-Goals

This system is **NOT**:
- A CRM system
- A free file hosting platform
- A simple UI update of MARC-SQL
- A mockup or demo prototype
- A system where CRM is the library
- A faculty/department-first navigation system
- A system that depends on MARC-SQL forever
- A system with a separate Analyst role
- A system where guests can perform any operational action
- A system that allows free download of digital materials
- A system that allows direct file URL access to protected content
- A system where the library database is shared directly with CRM

---

## 34. Future Roadmap

The following are **confirmed future features** — not in current scope but architecture must not block them:

| Feature | Notes |
|---|---|
| AI-assisted search and recommendations | Smart search layer, related books |
| AI data correction assistant | Suggests fixes for anomalous records |
| Full protected digital reading flow | Advanced controlled viewer, DRM-like features |
| Formal syllabus builder with approval workflow | Teacher builds official syllabus, admin approves |
| 3D / video / motion design enhancement | Homepage and key pages visual upgrade |
| QR-code mobile-first issue/return | Dedicated mobile flow |
| Deeper CRM integration features | Shared dashboard widgets, event hooks |
| Advanced barcode/RFID integration | Physical stock management upgrade |
| Integration with national library databases | RUSMARC, MARC21 exchange |
| Inter-library resource sharing | Consortium lending |
| Full Linked Open Data / MARC21 export | Open data compliance |
| Contract management module | Full lifecycle of external resource contracts |
| Mobile application | iOS/Android native or PWA |
| Elasticsearch integration | Replace PostgreSQL FTS for large-scale search |
| Self-service kiosk mode | Barcode scanner station for self-issue/return |

---

*This document is the single canonical source of truth for the KazUTB Library Platform.*
*Version: 2.0 Final | 2026-04-20*
*All implementation, design, architecture, and product decisions must align with this document.*
*In case of any conflict — this document wins.*

---

## Vault Links

See decomposed master notes:
[[PRODUCT_IDENTITY]] · [[OPERATIONAL_MODEL]] · [[AUTH_MODEL]] · [[TECH_STACK]]
[[ROLES_AND_ACCESS]] · [[RBAC_MATRIX]] · [[DATA_MODEL]] · [[STATUS_DICTIONARY]]
[[CIRCULATION_POLICY]] · [[CIRCULATION_WORKFLOWS]] · [[CATALOG_AND_SEARCH]]
[[DIGITAL_MATERIALS]] · [[SCIENTIFIC_REPOSITORY]] · [[EXTERNAL_RESOURCES]]
[[NEWS_MODULE]] · [[CONTACT_MODULE]] · [[ANALYTICS_AND_REPORTING]]
[[NOTIFICATIONS]] · [[AUDIT_LOG]] · [[DATA_CLEANUP_PANEL]]
[[HOMEPAGE]] · [[MEMBER_DASHBOARD]] · [[PAGE_MAP]]

Memory layer:
[[CURRENT_STATE]] · [[DECISIONS]] · [[OPEN_QUESTIONS]] · [[TASK_LOG]]