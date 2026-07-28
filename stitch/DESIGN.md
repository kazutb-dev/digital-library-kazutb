---
name: Academic Heritage
colors:
  surface: '#fcf9f8'
  surface-dim: '#dcd9d9'
  surface-bright: '#fcf9f8'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f6f3f2'
  surface-container: '#f0eded'
  surface-container-high: '#eae7e7'
  surface-container-highest: '#e5e2e1'
  on-surface: '#1c1b1b'
  on-surface-variant: '#44474e'
  inverse-surface: '#313030'
  inverse-on-surface: '#f3f0ef'
  outline: '#74777f'
  outline-variant: '#c4c6cf'
  surface-tint: '#465f88'
  primary: '#000a1e'
  on-primary: '#ffffff'
  primary-container: '#002147'
  on-primary-container: '#708ab5'
  inverse-primary: '#aec7f6'
  secondary: '#b22738'
  on-secondary: '#ffffff'
  secondary-container: '#fe5f6b'
  on-secondary-container: '#640014'
  tertiary: '#090b0c'
  on-tertiary: '#ffffff'
  tertiary-container: '#202222'
  on-tertiary-container: '#888989'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#d6e3ff'
  primary-fixed-dim: '#aec7f6'
  on-primary-fixed: '#001b3d'
  on-primary-fixed-variant: '#2d476f'
  secondary-fixed: '#ffdad9'
  secondary-fixed-dim: '#ffb3b3'
  on-secondary-fixed: '#40000a'
  on-secondary-fixed-variant: '#900723'
  tertiary-fixed: '#e2e2e2'
  tertiary-fixed-dim: '#c6c6c7'
  on-tertiary-fixed: '#1a1c1c'
  on-tertiary-fixed-variant: '#454747'
  background: '#fcf9f8'
  on-background: '#1c1b1b'
  surface-variant: '#e5e2e1'
typography:
  display-lg:
    fontFamily: Playfair Display
    fontSize: 48px
    fontWeight: '700'
    lineHeight: '1.1'
    letterSpacing: -0.02em
  display-lg-mobile:
    fontFamily: Playfair Display
    fontSize: 32px
    fontWeight: '700'
    lineHeight: '1.2'
  headline-md:
    fontFamily: Playfair Display
    fontSize: 32px
    fontWeight: '600'
    lineHeight: '1.3'
  headline-sm:
    fontFamily: Playfair Display
    fontSize: 24px
    fontWeight: '600'
    lineHeight: '1.4'
  body-lg:
    fontFamily: Source Sans 3
    fontSize: 18px
    fontWeight: '400'
    lineHeight: '1.6'
  body-md:
    fontFamily: Source Sans 3
    fontSize: 16px
    fontWeight: '400'
    lineHeight: '1.6'
  label-md:
    fontFamily: Source Sans 3
    fontSize: 14px
    fontWeight: '600'
    lineHeight: '1.2'
    letterSpacing: 0.05em
  caption:
    fontFamily: Source Sans 3
    fontSize: 12px
    fontWeight: '400'
    lineHeight: '1.4'
spacing:
  unit: 8px
  container-max: 1280px
  gutter: 24px
  margin-mobile: 16px
  margin-desktop: 48px
  section-padding: 80px
---

## Brand & Style

This design system embodies the prestige and enduring authority of world-class academic institutions. It is built upon the principles of intellectual rigor, tradition, and archival permanence. The aesthetic is "New Classic"—utilizing modern layout techniques to present traditional academic values.

The visual narrative is driven by structured professionalism. It avoids ephemeral trends in favor of a timeless, high-contrast editorial style. The target audience includes researchers, faculty, and students who require a focused, distraction-free environment that signals reliability and institutional depth. Every element should feel intentional, stable, and "expensive" through meticulous alignment and restrained ornamentation.

## Colors

The palette is rooted in historical collegiate identity. 

- **Primary (Oxford Blue):** Used for navigation bars, primary headers, and foundational structural elements to convey stability and trust.
- **Secondary (Harvard Crimson):** Reserved for high-value accents, call-to-action buttons, and indicating "active" states or critical notifications. 
- **Neutral/Background:** The primary canvas is pure white (#FFFFFF) to maximize legibility. #F5F5F5 (Light Gray) is used for section containers and subtle background shifts to define modular zones.
- **Typography:** Headlines and body text utilize a deep off-black (#1A1A1A) to maintain high contrast while appearing softer than pure black.

## Typography

The typography strategy employs a "Serif for Authority, Sans for Utility" approach. 

**Playfair Display** provides the editorial elegance required for a prestigious institution. It should be used for all major page headings and section titles. On mobile, display sizes must scale down aggressively to maintain the "contained" academic feel without excessive wrapping.

**Source Sans 3** is selected for its high legibility in dense information environments. It handles metadata, book descriptions, and search results with clinical clarity. Labels and utility links use uppercase styling with increased letter spacing to create a distinct visual hierarchy between content and interface controls.

## Layout & Spacing

The layout follows a strict 12-column modular grid. It prioritizes symmetry and vertical rhythm. 

- **Modular Grid:** Content is grouped into clearly defined blocks. Vertical divisions are handled by 1px solid lines in #E0E0E0 rather than shadows.
- **Whitespace:** Generous top and bottom padding (Section Padding) is mandatory to prevent the "cluttered" feel common in legacy library portals. 
- **Responsive Behavior:** 
  - **Desktop:** Fixed-width container (1280px) centered in the viewport.
  - **Tablet:** Fluid width with 32px margins.
  - **Mobile:** Single column stack with 16px margins; typography scales down, and secondary imagery is often hidden to prioritize search and access functionality.

## Elevation & Depth

This design system avoids physical depth metaphors like shadows or blurs. Instead, it uses **Tonal Layering** and **Line Work**.

- **Flat Architecture:** Depth is communicated by color blocks. Content sits on #FFFFFF; containers for secondary tools or filters sit on #F5F5F5.
- **Stark Outlines:** Elements like search inputs and cards are defined by 1px solid borders (#D1D1D1). 
- **Active States:** Depth is indicated by a "fill" change (e.g., a button moving from an outline to a solid Oxford Blue fill) rather than a shadow "lift."
- **Imagery:** High-quality photography provides the only "organic" depth in the system. Images should be framed in sharp-edged containers with no rounded corners.

## Shapes

The shape language is strictly **Sharp (0px)**. 

Every UI element—from buttons and input fields to image containers and dropdowns—must use 90-degree corners. This reinforces the institutional, "built-to-last" architecture of a library. The only circular elements permitted are standard radio buttons for form accessibility.

## Components

- **Buttons:** 
  - *Primary:* Solid Oxford Blue background, White text, 1px border. 
  - *Secondary:* Transparent background, Oxford Blue 1px border. 
  - *Accent:* Solid Harvard Crimson background, White text (use sparingly for "Reserve" or "Login").
- **Search Bar:** A prominent, full-width component with a 2px Oxford Blue bottom border. It should use the Serif font for the user's input to make the search feel like an "entry in a ledger."
- **Cards:** No shadows. 1px light gray border. Top-aligned photography, followed by a Label (Source Sans, Uppercase) and a Title (Playfair Display).
- **Navigation:** Top-tier global nav is minimalist. The "Active" state is marked by a 3px Harvard Crimson top-border on the nav item.
- **Lists:** Used for search results and citations. Divided by 1px horizontal rules (#E0E0E0). Ample vertical padding (16px-24px) between items.
- **Breadcrumbs:** Small, uppercase Source Sans text. Essential for navigating complex archival hierarchies.