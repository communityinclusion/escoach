---
name: bootstrap-4-to-5
description: "Use when migrating a website, Drupal theme, or frontend from Bootstrap 4 to Bootstrap 5; audit breaking changes in markup, utilities, JavaScript, Sass, forms, components, and asset loading."
---

# Bootstrap 4 to Bootstrap 5 Migration

Guide an incremental, verifiable migration from Bootstrap 4 to Bootstrap 5.
Treat the official, version-specific migration guide as the source of truth:
[Bootstrap 5.0 migration guide](https://getbootstrap.com/docs/5.0/migration/).
This skill summarizes common compatibility work; it is not a substitute for
checking the exact source and target versions or every migration note.

For a direct migration from Bootstrap 3, first use the
[Bootstrap 3 to 5 migration skill](../bootstrap-3-to-5/SKILL.md) to cover
Bootstrap 3-specific components, grid utilities, and markup. Then apply the
Bootstrap 4-to-5 checks below to the Bootstrap 4-to-5 portion of the upgrade.

## Scope and version checks

Before changing code:

1. Confirm the installed Bootstrap 4 version, intended Bootstrap 5 target
   (including minor version), and whether the project uses Bootstrap directly,
   a framework integration, or a theme such as Drupal Bootstrap Barrio.
2. Locate all Bootstrap CSS, JavaScript, Sass imports, compiled assets,
   templates, components, plugins, and package-manager declarations. Include
   generated or CMS-managed markup only where it is in the migration scope.
3. Check Bootstrap-dependent third-party libraries, custom JavaScript, browser
   support requirements, build tooling, and the site's use of jQuery and
   Popper.
4. Read the official migration documentation for the exact target version.
   Bootstrap 5.0-specific guidance must not be silently mixed with features or
   replacements introduced in 5.1, 5.2, or 5.3.
5. Identify representative pages, breakpoints, forms, tables, overlays, and
   interactive states. Capture a baseline before editing.

Do not replace the existing framework assets or upgrade unrelated dependencies
before confirming how Bootstrap is installed and built in this project.

## Breaking-change checklist

Use these as search and review prompts, not as blind global substitutions.
Confirm each use and its behavior in context.

### Runtime, JavaScript, and data attributes

- Bootstrap 5 no longer depends on jQuery. Rework project code that calls
  Bootstrap plugins through jQuery into the Bootstrap 5 JavaScript API or
  native DOM behavior. Keep jQuery if application code or other libraries still
  need it.
- Bootstrap 5 uses Popper 2 where required. Check dependency versions and avoid
  loading multiple Popper or Bootstrap bundles.
- Rename Bootstrap plugin data attributes from `data-toggle`, `data-target`,
  `data-dismiss`, and related forms to `data-bs-toggle`, `data-bs-target`,
  `data-bs-dismiss`, and the corresponding `data-bs-*` names.
- Bootstrap events are native DOM events rather than jQuery events. Update
  event listeners, event names, and code that reads event details accordingly.
- Update plugin construction and static API usage to the Bootstrap 5 API.
  Review removed or renamed methods (including the removal of leading
  underscores from public static methods) against the official migration guide.
- Audit any initialization code, Drupal behaviors, event handlers, and
  dynamically inserted markup for double initialization or missing
  reattachment.

### Grid, utilities, and helpers

- `.col-xs-*` loses the `xs` infix; default-width columns use `.col-*`.
- `.no-gutters` becomes `.g-0`. Bootstrap 5 adds gutter utilities such as
  `.g-*`, `.gx-*`, and `.gy-*`; verify the intended spacing at each breakpoint.
- Directional utility names become writing-direction-aware logical names:
  examples include `.ml-*` to `.ms-*`, `.mr-*` to `.me-*`,
  `.pl-*` to `.ps-*`, `.pr-*` to `.pe-*`, `*-left`/`*-right` text alignment to
  `*-start`/`*-end`, and left/right border, float, and rounded utilities to
  start/end variants.
- `.sr-only` and `.sr-only-focusable` become `.visually-hidden` and
  `.visually-hidden-focusable`.
- `.embed-responsive-*` patterns become the `.ratio` helper and its aspect
  ratio classes.
- `.font-weight-*`, `.font-italic`, and `.text-monospace` become `.fw-*`,
  `.fst-italic`, and `.font-monospace`.
- `.text-justify` and `.text-hide` are removed. Do not replace them mechanically
  without determining their original purpose and accessible alternative.
- `.btn-block` is removed; use an appropriate layout utility or custom
  responsive style.
- Review changed positioning assumptions, including grid columns no longer
  being positioned relatively by default.
- Bootstrap 5 underlines links by default outside selected components. Check
  link styling and interaction states against the design.

### Forms

- `.form-group`, `.form-row`, and `.form-inline` are removed. Use spacing and
  grid/flex layout appropriate to the form.
- Bootstrap 4 custom form controls are replaced by Bootstrap 5 patterns:
  `.custom-select` becomes `.form-select`; checkbox, radio, and switch markup
  use `.form-check` patterns; `.custom-range` becomes `.form-range`.
- Rework custom file input and form-control-file markup using the Bootstrap 5
  supported file input pattern.
- Remove `.input-group-prepend` and `.input-group-append` wrappers; place
  addons as direct input-group children.
- Check validation, help text, labels, feedback, sizing, and disabled states
  against the Bootstrap 5 form structure. Preserve explicit programmatic
  labels, descriptions, and error associations; a CSS class change alone does
  not establish accessible labeling.

### Components and content

- `.media` has been removed; rebuild its layout with flex/grid utilities or
  project styles.
- `.card-deck` and `.card-columns` are removed. Use grid/row-column layouts or
  another suitable layout rather than assuming cards will keep the same sizing.
- `.jumbotron` is removed; reproduce the intended visual treatment with
  appropriate layout and utility classes or project styles.
- Bootstrap 4 badge color and pill classes change. Use Bootstrap 5 background
  utilities and pill rounding as appropriate, and verify text contrast.
- `.close` becomes `.btn-close`; update its markup and accessible name.
- Bootstrap 5 introduces a dedicated accordion component. Migrate
  card-based accordion markup according to the Bootstrap 5 component guidance.
- Review table changes, including `.thead-light`/`.thead-dark` replacement by
  table variants, changed nested table styling, and updated table Sass APIs.
- Review navbar and dropdown markup, active-state placement, color utilities,
  and responsive behavior against Bootstrap 5 examples.
- Audit changes to typography, display headings, Reboot defaults, and
  responsive font sizing for unintended wrapping or layout shifts.

### Sass and build integration

- Review custom Sass for renamed or removed variables, functions, mixins, maps,
  and component-specific variables. Use the official Sass migration section
  rather than assuming Bootstrap 4 overrides remain valid.
- Check import order and whether custom overrides are applied before the
  Bootstrap Sass modules that consume them.
- Verify the project's Sass compiler and dependencies can compile the selected
  Bootstrap 5 version; resolve compiler deprecations deliberately rather than
  hiding errors.
- Inspect emitted CSS and asset paths. Update library declarations and
  dependency bundles together, keeping generated CSS in the destination
  expected by the app or CMS.
- If using Bootstrap's Sass functions for custom colors or utilities, verify
  contrast and all states after the upgrade.

## Recommended migration sequence

1. **Create a baseline:** record loaded asset versions and order; capture
   representative desktop, tablet, and mobile pages and key interactions.
2. **Update dependencies:** change Bootstrap CSS, JavaScript, Popper, and Sass
   dependencies consistently. Keep one active Bootstrap version on each page.
3. **Migrate runtime behavior:** update plugin APIs, data attributes, event
   listeners, and initialization patterns. Test modals, dropdowns, tooltips,
   popovers, collapses, and any used plugin.
4. **Migrate templates and components:** address structural removals and
   replacements for forms, cards, navbars, tables, helpers, and content.
5. **Migrate utilities and Sass:** update logical-direction utilities and
   project Sass overrides component by component.
6. **Compile and verify each batch:** inspect compiler output, browser console,
   network assets, responsive layouts, form validation, focus behavior, and
   visual parity before proceeding.
7. **Remove obsolete references:** delete Bootstrap 4 assets or compatibility
   shims only after searches and tests confirm no remaining consumers.
8. **Document exceptions:** record any intentionally changed design, unsupported
   third-party integration, or work deferred from this migration.

For large projects, use codemods or search-and-replace only for well-understood
syntax patterns. Review diffs carefully: a class can have different intent in
different contexts, and migration tools cannot determine layout, accessibility,
or JavaScript behavior.

## Validation and completion criteria

- The intended Bootstrap 5 CSS and JavaScript versions load once, in the
  expected order, with compatible Popper where needed.
- No migrated component still depends on Bootstrap 4 selectors, markup,
  jQuery plugin APIs, or old data attributes.
- Builds complete without Sass or JavaScript errors; runtime errors and failed
  asset requests are resolved.
- Representative pages and interactions work at supported breakpoints and in
  the project's supported browsers.
- Forms remain labeled and usable; keyboard operation, focus visibility, and
  validation feedback are checked for affected controls.
- Any visual differences are intentional, reviewed, and recorded. A successful
  compilation or automated scan alone is not evidence of migration parity.

## Drupal-specific notes

- Inspect the active theme, base theme, contributed modules, Twig templates,
  libraries, component assets, and build scripts before modifying framework
  assets. Avoid overriding assets owned by contributed themes or modules
  without confirming the supported integration.
- Run Node tooling through `ddev exec` and Drush through `ddev drush` in
  repositories whose instructions require DDEV. Clear Drupal cache after
  theme/library/configuration changes.
- For Bootstrap Barrio Sass subtheme creation or integration, also use the
  [Bootstrap Barrio subtheme skill](../bootstrap-barrio-subtheme/SKILL.md).

## Related skills

- [Bootstrap overview](../bootstrap-overview/SKILL.md) — setup, versions, and
  framework foundations.
- [Bootstrap customization](../bootstrap-customize/SKILL.md) — Sass variables,
  CSS custom properties, and theming.
- [Bootstrap components](../bootstrap-components/SKILL.md) — component markup
  and behavior.
- [Bootstrap layout](../bootstrap-layout/SKILL.md) — grid and layout patterns.
- [Bootstrap utilities](../bootstrap-utilities/SKILL.md) — utility class
  migration and usage.
- [Bootstrap forms](../bootstrap-forms/SKILL.md) — form controls and validation.
- [SCSS best practices](../scss-best-practices/SKILL.md) — maintainable Sass
  structure and refactoring.
- [Bootstrap Barrio subthemes](../bootstrap-barrio-subtheme/SKILL.md) — Drupal
  Barrio-specific Sass setup.
