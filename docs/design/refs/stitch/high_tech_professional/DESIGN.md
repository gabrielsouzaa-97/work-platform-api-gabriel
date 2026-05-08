---
name: High-Tech Professional
colors:
  surface: '#0b1326'
  surface-dim: '#0b1326'
  surface-bright: '#31394d'
  surface-container-lowest: '#060e20'
  surface-container-low: '#131b2e'
  surface-container: '#171f33'
  surface-container-high: '#222a3d'
  surface-container-highest: '#2d3449'
  on-surface: '#dae2fd'
  on-surface-variant: '#c2c6d6'
  inverse-surface: '#dae2fd'
  inverse-on-surface: '#283044'
  outline: '#8c909f'
  outline-variant: '#424754'
  surface-tint: '#adc6ff'
  primary: '#adc6ff'
  on-primary: '#002e6a'
  primary-container: '#4d8eff'
  on-primary-container: '#00285d'
  inverse-primary: '#005ac2'
  secondary: '#bcc7de'
  on-secondary: '#263143'
  secondary-container: '#3e495d'
  on-secondary-container: '#aeb9d0'
  tertiary: '#ffb786'
  on-tertiary: '#502400'
  tertiary-container: '#df7412'
  on-tertiary-container: '#461f00'
  error: '#ffb4ab'
  on-error: '#690005'
  error-container: '#93000a'
  on-error-container: '#ffdad6'
  primary-fixed: '#d8e2ff'
  primary-fixed-dim: '#adc6ff'
  on-primary-fixed: '#001a42'
  on-primary-fixed-variant: '#004395'
  secondary-fixed: '#d8e3fb'
  secondary-fixed-dim: '#bcc7de'
  on-secondary-fixed: '#111c2d'
  on-secondary-fixed-variant: '#3c475a'
  tertiary-fixed: '#ffdcc6'
  tertiary-fixed-dim: '#ffb786'
  on-tertiary-fixed: '#311400'
  on-tertiary-fixed-variant: '#723600'
  background: '#0b1326'
  on-background: '#dae2fd'
  surface-variant: '#2d3449'
typography:
  display-lg:
    fontFamily: Inter
    fontSize: 36px
    fontWeight: '700'
    lineHeight: '1.2'
    letterSpacing: -0.02em
  headline-md:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '600'
    lineHeight: '1.3'
  body-base:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '400'
    lineHeight: '1.5'
  body-sm:
    fontFamily: Inter
    fontSize: 13px
    fontWeight: '400'
    lineHeight: '1.5'
  code-block:
    fontFamily: Fira Code
    fontSize: 13px
    fontWeight: '450'
    lineHeight: '1.6'
  label-caps:
    fontFamily: Inter
    fontSize: 11px
    fontWeight: '600'
    lineHeight: '1'
    letterSpacing: 0.05em
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  unit: 4px
  xs: 4px
  sm: 8px
  md: 16px
  lg: 24px
  xl: 48px
  gutter: 16px
  margin: 24px
---

## Brand & Style

The design system is engineered for precision, clarity, and technical authority. It targets a developer and sysadmin audience who require high information density without cognitive overload. The aesthetic is "High-Tech Professional"—a fusion of minimalist efficiency and corporate reliability.

The visual narrative centers on **Engineering Precision**. It avoids decorative flourishes in favor of functional geometry, subtle depth, and a color story that emphasizes status and action. The emotional response should be one of calm control, suggesting a powerful engine under a refined hood. 

We utilize a **Modern Corporate** style with **Minimalist** leanings:
- **Cleanliness:** Every pixel serves a purpose; whitespace is used as a tool for grouping rather than just "breathing room."
- **Clarity:** Distinct visual hierarchies ensure that critical system statuses are immediately identifiable.
- **Utility:** Design elements are built to support complex data visualization and long-term user focus.

## Colors

The palette is anchored in a dark, sophisticated environment to reduce eye strain during extended technical sessions. 

- **Primary Interface:** We use "Deep Slate" (#0F172A) for the primary background and "Navy Blue" (#1E293B) for container elements like sidebars and cards. This creates a layered, recessed feel.
- **Action Colors:** "Electric Blue" (#3B82F6) is our primary action color, providing high contrast against the dark background.
- **Semantic Status:** We use "Emerald Green" (#10B981) for active states and success messages, and "Amber" (#F59E0B) for warnings. These are saturated to ensure they pop against the desaturated slate tones.
- **Text:** High-contrast off-whites are used for primary text, while muted grays are reserved for secondary metadata.

## Typography

Typography in this design system is split between interface navigation and technical data presentation.

- **UI Elements:** We use **Inter** for all standard UI components. It is chosen for its exceptional legibility at small sizes and its neutral, systematic character. We utilize a tighter letter-spacing for headlines to maintain a "tech" feel.
- **Technical Data:** For API keys, log streams, and terminal outputs, a monospaced font (Fira Code or JetBrains Mono) is mandatory. This ensures character alignment and prevents confusion between similar characters (like '0' and 'O').
- **Hierarchy:** We prioritize a clear vertical rhythm. Small caps are used sparingly for section headers in sidebars to create distinct categories without needing excessive font size increases.

## Layout & Spacing

This design system employs a **Fluid Grid** model to maximize the utility of varying screen sizes, from laptop screens to large monitoring displays. 

- **Grid System:** A 12-column layout with 16px gutters. For dense data views, gutters can be reduced to 8px to maximize horizontal space.
- **Spacing Rhythm:** We use a strict 4px/8px incremental scale. This mathematical consistency reinforces the "High-Tech" aesthetic and ensures alignment across complex dashboard widgets.
- **Density:** The design leans towards a "Compact" density. We use generous margins (24px+) around major layout containers to prevent the UI from feeling claustrophobic, but internal component padding remains tight to keep data visible.

## Elevation & Depth

Depth in this design system is achieved through **Tonal Layering** and **Subtle Shadows**, rather than aggressive skeuomorphism.

- **Background (Level 0):** The deepest slate (#0F172A).
- **Surface (Level 1):** Cards and navigation bars use a slightly lighter navy (#1E293B). These elements use a 1px border (#334155) to define their edges against the background.
- **Elevated (Level 2):** Modals and dropdowns use a lighter tint and a soft, diffused shadow (0px 10px 15px -3px rgba(0, 0, 0, 0.5)) to appear as if they are floating above the main interface.
- **Backdrop Blur:** For overlays and modals, we apply a subtle backdrop blur (8px) to maintain context while focusing the user's attention on the foreground task.

## Shapes

The shape language is disciplined and geometric. 

- **Corner Radii:** We use a **Soft (4px)** radius for standard components like buttons, inputs, and cards. This provides a modern touch while maintaining a sharp, professional edge that aligns with a grid-heavy technical UI.
- **Buttons:** Follow the 4px standard. Fully rounded "pill" shapes are avoided to keep the interface looking like a tool rather than a consumer app.
- **Visual Markers:** Indicators (like status dots) are perfect circles to differentiate them from interactive rectangular elements.

## Components

### Buttons
Primary buttons use the Electric Blue fill with white text. Secondary buttons use a "Ghost" style: a subtle slate border that brightens on hover. High-priority "Destructive" actions use a subtle red outline rather than a solid red fill to avoid overwhelming the dashboard.

### Input Fields & Search
Inputs feature a dark background (darker than the card surface) to create an "inset" look. The focus state is a 2px Electric Blue border. Search bars in the header should include a "CMD+K" shortcut hint in a monospaced font.

### Data Tables
Tables are the heart of this design system. They use zebra striping (subtle tonal shifts) and "hover-row" highlights in a faint navy. Headers are sticky and use the `label-caps` typography style.

### Status Chips
Chips for "Success," "Warning," or "Error" use a "low-contrast fill" approach: a background with 10% opacity of the status color and a 100% opacity text color for maximum readability without visual noise.

### Code Snippets
Code containers use a distinct black background, syntax highlighting, and a "Copy to Clipboard" icon that appears on hover. These always use the `code-block` typography.

### Progress & Sparklines
For real-time metrics, we use thin 2px sparklines in Electric Blue or Emerald Green. Progress bars are flat, non-animated, and use the primary action color.