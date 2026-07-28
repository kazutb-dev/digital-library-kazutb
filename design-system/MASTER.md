# KazUTB Digital Library — UI/UX Pro Max Design System (MASTER)

## 1. Brand Naming (Do not mix languages)

- **Russian:**
    - Казахский университет технологии и бизнеса имени К. Кулажанова
    - Цифровая библиотека
- **Kazakh:**
    - Қ. Құлажанов атындағы Қазақ технология және бизнес университеті
    - Цифрлық кітапханасы
- **English:**
    - Kazakh University of Technology and Business named after K. Kulazhanov
    - Digital Library
- Never use “Smart” in public UI unless required for a specific asset.

## 2. Typography Rules

- Use academic, readable, modern, and stable font pairings.
- Prefer:
    - Headings: Newsreader, serif (or similar)
    - Body: Manrope, sans-serif (or similar)
- Font weights: 400–700 for headings, 400–500 for body.
- Avoid excessive font sizes or weight jumps.

## 3. Spacing Rules

- Use consistent vertical rhythm (multiples of 4/8px).
- Maintain generous but not excessive whitespace.
- Avoid layout drift and unstable spacing.

## 4. Color & Styling Tone

- Academic, premium, clean, and subtle.
- Use neutral backgrounds (#f8f9fa, #ffffff), deep navy/blue for primary (#001f3f, #000613), and teal/secondary (#006a6a).
- Avoid neon, harsh gradients, or AI purple/pink.
- Text contrast: 4.5:1 minimum (WCAG AA).

## 5. Hover/Focus/Transition Behavior

- All clickable elements: cursor-pointer, visible focus state.
- Hover: subtle color/opacity/underline, not aggressive.
- Transitions: 150–300ms, smooth, respects prefers-reduced-motion.

## 6. Layout Stability Rules

- No breaking of grid or flex layouts.
- Responsive: 375px, 768px, 1024px, 1440px.
- No image cropping issues.
- No layout shift on interaction.

## 7. Multilingual Brand Lockup Rules

- Always use the correct localized brand for each language.
- Never mix languages in a single label or block.
- Brand lockup must be compact, stable, and visually balanced.

## 8. Anti-Patterns (Never do)

- No flashy UI, over-animation, or unstable spacing.
- No mixed brand wording or inconsistent typography.
- No aggressive redesign or layout drift.

## 9. Pre-Delivery Checklist

- [ ] Brand and typography match rules above
- [ ] Spacing and color are stable and academic
- [ ] All interactive elements have proper states
- [ ] Layout is responsive and stable
- [ ] Multilingual brand lockup is correct
- [ ] No anti-patterns present

---

**How to use:**

- For every frontend/public UI task, read this MASTER.md first.
- If a page-specific override exists in design-system/pages/[page].md, use it for that page.
- Otherwise, follow these master rules for all public UI: homepage, navbar, footer, about, leadership, rules, contacts, discover, resources, news, events, catalog, book detail.

**Reference:**

- Inspired by UI UX Pro Max (https://github.com/nextlevelbuilder/ui-ux-pro-max-skill)
- This file is the local source of truth for design intelligence in this project.
