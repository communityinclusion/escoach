---
name: bootstrap-3-to-5
description: "Use when migrating a website, Drupal theme, or frontend directly from Bootstrap 3 to Bootstrap 5; assess legacy grid, components, forms, JavaScript, data attributes, utilities, assets, and custom styles."
---

# Bootstrap 3 to Bootstrap 5 Migration

Plan and carry out a staged, evidence-based migration from Bootstrap 3 to
Bootstrap 5. Bootstrap 5 is a major framework change, not a drop-in CSS/JS
replacement. Audit templates, custom code, dependencies, and generated markup;
preserve intended layout and behavior rather than applying blind class-name
replacements.

Use the [Bootstrap 3 documentation](https://getbootstrap.com/docs/3.4/),
the [Bootstrap 5 migration guide](https://getbootstrap.com/docs/5.0/migration/),
and the [Bootstrap 5.3 introduction](https://getbootstrap.com/docs/5.3/getting-started/introduction/)
as primary references. Check migration notes for the exact Bootstrap 5 minor
version in the project; do not mix 5.0 changes with features added in 5.1–5.3
without confirming version support.

## Scope and baseline

Before editing:

1. Confirm the precise Bootstrap 3 version, planned Bootstrap 5 version, how
   assets are installed and compiled, and whether a framework integration or
   theme provides Bootstrap markup.
2. Search application templates, CMS templates, JavaScript, stylesheets,
   Sass/Less sources, generated markup, and package manifests for Bootstrap
   classes, component data attributes, plugin calls, and overrides.
3. Inventory all used components and interactions, including navigation,
   dropdowns, modals, tabs, tooltips, carousels, forms, tables, and responsive
   layouts. Identify any third-party plugins tied to Bootstrap 3 or jQuery.
4. Record supported browsers and assistive technology requirements. Bootstrap
   5 does not support Internet Explorer; treat any browser-support change as an
   explicit product decision.
5. Capture representative desktop, tablet, and mobile pages, plus form errors,
   keyboard focus, and important interactive states, as a comparison baseline.

Do not replace framework files or add a CDN reference before establishing how
the current project manages assets. Pin a deliberate Bootstrap 5 version and
avoid loading Bootstrap 3 and 5 CSS/JavaScript together on the same page.

## Bootstrap 3 changes to audit

These are review prompts, not a complete mapping. Confirm each case against
Bootstrap's documentation and the project's design and interaction behavior.

### Grid and responsive helpers

- Bootstrap 3 uses `.col-xs-*`; Bootstrap 5 omits the `xs` infix and uses
  `.col-*` for the default tier. Re-check the intended behavior at every
  breakpoint rather than changing only the class spelling.
- Migrate `.col-*-offset-*` to the corresponding `.offset-*` utilities.
- Bootstrap 3 push/pull classes (`.col-*-push-*` and `.col-*-pull-*`) have no
  direct class-name replacement. Recreate the intended visual order with
  Bootstrap 5 order utilities or a suitable layout, and check the DOM reading
  and keyboard order remains logical.
- Bootstrap 3 responsive visibility classes (`.visible-*`, `.hidden-*`) are
  not Bootstrap 5 utilities. Recreate visibility with responsive display
  utilities only after confirming the exact show/hide behavior at each range.
- Bootstrap 5 adds the `xxl` responsive tier; do not assume Bootstrap 3's
  largest breakpoint has the same range or behavior.
- Review container widths, gutters, nested rows, floats, and columns that
  relied on Bootstrap 3 defaults. Preserve responsive behavior rather than
  assuming the new grid is geometrically identical.

### Components and markup

- Glyphicons are not included in Bootstrap 5. Replace them with an approved
  icon source or another intentional visual treatment; provide accessible
  names for meaningful icons and hide decorative icons from assistive
  technology.
- Bootstrap 3 panels, wells, and thumbnails do not have direct Bootstrap 5
  equivalents. Rebuild their hierarchy and styling with cards, layout
  utilities, or project components according to their actual content and
  behavior.
- Navbar markup and styling have changed substantially. Rebuild the navbar
  structure, responsive toggler, expansion behavior, and color/background
  styling using Bootstrap 5 component markup; do not only rename
  `.navbar-default` or `.navbar-inverse`.
- Review removed or changed components and classes, including `.media`,
  `.jumbotron`, button size/variant classes such as `.btn-xs`, and any
  project-specific or plugin-provided component. Decide on a supported
  replacement for each actual use.
- Bootstrap 5 links are underlined by default outside selected components.
  Review link styling, hover and focus behavior, and visual affordance.
- Tables, typography, Reboot defaults, and spacing can render differently.
  Compare them in context instead of relying solely on selector compatibility.

### Forms and accessibility

- Rebuild form layout using Bootstrap 5's form markup. Bootstrap 5 removes
  `.form-group`, `.form-horizontal`, and `.control-label`; use layout/spacing
  classes and `.form-label` where appropriate.
- Replace legacy help and validation styling with the corresponding Bootstrap
  5 form text, validation, and feedback patterns. Verify actual invalid,
  valid, disabled, and help-text states.
- Preserve explicit label associations, accessible names, descriptions,
  required-state information, error identification, and focus behavior.
  Bootstrap styling changes alone do not make a form accessible.
- Test all relevant form controls, including custom select/file inputs,
  checkboxes, radio buttons, and input groups against Bootstrap 5 examples.

### JavaScript and data attributes

- Bootstrap 3 JavaScript plugins depend on jQuery. Bootstrap 5 has no jQuery
  dependency and uses native JavaScript plugin APIs. Migrate code that invokes
  Bootstrap 3 jQuery plugins, their lifecycle events, and event handlers.
  Keep jQuery if application code or another supported dependency still uses
  it; do not assume Bootstrap 5 will preserve the old jQuery plugin API.
- Bootstrap 3 component attributes such as `data-toggle`, `data-target`, and
  `data-dismiss` become Bootstrap 5's namespaced attributes, including
  `data-bs-toggle`, `data-bs-target`, and `data-bs-dismiss`. Search all
  templates and dynamically generated markup.
- Check plugin initialization, event names and payloads, transition timing,
  and any custom JavaScript that depends on Bootstrap 3 internals.
- Bootstrap 5 uses Popper 2 for components that need positioning. Check the
  dependency tree and asset order; avoid duplicate or incompatible Popper and
  Bootstrap bundles.
- Test each plugin used by the project, especially collapse/navbar, modal,
  dropdown, tab, carousel, tooltip, and popover interactions.

### Utilities and custom styles

- Bootstrap 5 offers a larger utility set, including spacing and logical
  direction utilities. Use utilities where they preserve a coherent design;
  do not replace every custom rule with utility classes by default.
- Review directional CSS and classes for left/right assumptions. Bootstrap 5
  uses logical start/end utilities in many places; confirm both left-to-right
  and right-to-left behavior if RTL is in scope.
- Audit custom selectors and overrides that target Bootstrap 3 DOM structure,
  component class names, Sass variables, mixins, or Less variables. Rebuild
  them against Bootstrap 5's documented customization API.
- Keep design tokens and brand values intentional. Bootstrap 5 supports Sass
  customization and CSS custom properties, but these do not automatically
  preserve legacy styling.
- Search for stale Bootstrap 3 CSS/JS references and selectors after each
  migration batch. Verify specificity and stylesheet order when removing
  compatibility rules.

## Migration sequence

1. **Map usage:** inventory versions, assets, templates, plugins, custom
   styling, breakpoints, and essential user journeys.
2. **Prepare the target:** update the dependency/build setup to one pinned
   Bootstrap 5 version. Confirm the selected distribution and optional Popper
   are loaded consistently.
3. **Migrate layout and components:** update the grid and responsive behavior,
   rebuild legacy components, and migrate navigation and content structures.
4. **Migrate forms and helpers:** update controls, validation states, visibility,
   icons, and accessible names and associations.
5. **Migrate JavaScript:** update data attributes, plugin setup, event handling,
   and third-party integrations.
6. **Migrate custom styles:** adapt Sass/Less and CSS overrides in small,
   reviewable batches while maintaining a record of intentional visual changes.
7. **Verify each batch:** compile, inspect browser console and network assets,
   compare representative pages and breakpoints, and test interactions and
   keyboard operation.
8. **Remove legacy code:** remove Bootstrap 3 assets and compatibility styles
   only after a repository-wide search and regression checks confirm there are
   no remaining consumers.

For a large codebase, automate only mappings that are unambiguous. Review
codemod and search/replace diffs in context: class names can be used outside
Bootstrap, and an apparent class equivalent may not preserve layout, semantics,
or accessibility.

## Validation and completion criteria

- Only the intended Bootstrap 5 version is loaded on migrated pages, with
  compatible dependencies and correct asset ordering.
- No migrated templates or scripts rely on Bootstrap 3 selectors, plugin
  methods, or component data attributes.
- Build and runtime checks show no Sass/JavaScript errors, console exceptions,
  duplicate initialization, or failed asset requests.
- Representative pages and interactions behave as expected at supported
  breakpoints and browsers; visual differences are intentional and recorded.
- Forms, navigation, overlays, and other affected controls retain accessible
  names, labels, keyboard operation, visible focus, and perceivable states.
- The project's supported-browser policy explicitly accounts for Bootstrap 5's
  lack of Internet Explorer support.

Compilation success or an automated scan is not, by itself, proof of visual
parity or accessibility.

## Drupal projects

For Drupal, inspect theme inheritance, contributed themes/modules, Twig
templates, libraries, component assets, and build tooling before changing
framework assets. Do not replace assets owned by a base or contributed theme
without confirming the supported integration. Follow repository-specific
environment rules; in this workspace run Node tooling through `ddev exec`,
Drush through `ddev drush`, and clear Drupal cache after theme or library
changes.

For the Bootstrap 4 to 5 portion of a direct 3-to-5 upgrade, also use the
[Bootstrap 4 to 5 migration skill](../bootstrap-4-to-5/SKILL.md). For Sass
customization, use [Bootstrap customization](../bootstrap-customize/SKILL.md);
for Barrio integration, use the
[Bootstrap Barrio subtheme skill](../bootstrap-barrio-subtheme/SKILL.md).

## References

- [Bootstrap 3.4 documentation](https://getbootstrap.com/docs/3.4/)
- [Bootstrap 5.0 migration guide](https://getbootstrap.com/docs/5.0/migration/)
- [Bootstrap 5.3 introduction and setup](https://getbootstrap.com/docs/5.3/getting-started/introduction/)
- [W3Schools: Bootstrap 5 vs. Bootstrap 3](https://www.w3schools.com/bootstrap5/)
- [Bootstrap 4 to 5 migration skill](../bootstrap-4-to-5/SKILL.md)
