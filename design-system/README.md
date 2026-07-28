# design-system/README.md

## KazUTB Digital Library — Design Intelligence Workflow

This project uses a local design system inspired by [UI UX Pro Max](https://github.com/nextlevelbuilder/ui-ux-pro-max-skill) as the **source of truth** for all frontend/public UI work.

### How to Use

1. **Before any frontend/public UI task:**
    - Read `design-system/MASTER.md` for global rules (brand, typography, spacing, color, layout, anti-patterns, etc).
    - If a page-specific file exists in `design-system/pages/`, read it for overrides.
2. **Apply these rules** to all public-facing UI: homepage, navbar, footer, about, leadership, rules, contacts, discover, resources, news, events, catalog, book detail.
3. **Never mix languages or break brand rules.**
4. **Do not introduce anti-patterns** (see MASTER.md).

### Where to Find the Rules

- **Global rules:** `design-system/MASTER.md`
- **Page-specific overrides:** `design-system/pages/[page].md` (create as needed)

### Workflow

- All contributors must check the design-system before making UI changes.
- All PRs/tickets for frontend/public UI must reference the design-system.
- This workflow replaces the need for a global skill install and is project-local, safe, and versioned.

---

**Limitations:**

- Direct global install of UI UX Pro Max skill is not available in this environment.
- This workflow provides equivalent design intelligence, fully project-local.

**Reference:**

- [UI UX Pro Max Skill](https://github.com/nextlevelbuilder/ui-ux-pro-max-skill)
- [MASTER.md](./MASTER.md)
