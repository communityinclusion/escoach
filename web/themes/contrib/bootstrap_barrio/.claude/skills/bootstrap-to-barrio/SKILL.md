---
name: bootstrap-to-barrio
description: "Migrate an existing Drupal custom theme from the Bootstrap contrib base theme to Bootstrap Barrio and Bootstrap 5. Use for in-place Bootstrap 3/4 to Barrio migrations involving theme metadata, Twig, CSS/SCSS/LESS, JavaScript, framework assets, Drupal configuration, or validation."
---

# Drupal Bootstrap to Barrio Migration

Migrate an existing custom Drupal theme in place to `bootstrap_barrio` and a
compatible Bootstrap 5 asset source. Preserve the theme machine name, region
keys, content, block placements, routes, and intended design. Do not replace
the theme with Barrio or a generated starter theme.

Use this skill only when migration is requested. If the active custom theme
already uses Barrio, stop and confirm whether the requested scope is a cleanup
or a further Bootstrap upgrade.

## Project-specific guardrails

- Always use DDEV for development commands: `ddev drush`, `ddev composer`, and
  `ddev exec` for Node/build tools. Do not run PHP, Drush, or Node on the host.
- Preserve unrelated dirty changes. Never use a blanket restore or stage/export
  every file as a shortcut.
- Consult the repository's curated Drupal module list and reference-source map
  before recommending or adding contributed modules.
- For Drupal 11.3+ implementation work, load the Drupal Hook Attributes skill
  before writing code. Theme preprocess hooks remain procedural; do not move
  them into a module hook-attribute class mechanically.
- Do not edit Drupal core or contributed theme files. Do not make changes on
  production without explicit authorization.

## 1. Establish a safe baseline

1. Inspect `git status --short` and relevant diffs. Identify unrelated changes
   and preserve them.
2. Confirm the local environment is running and the database is imported:

   ```bash
   ddev describe
   ddev drush status
   ddev drush config:get system.theme
   ```

   Identify the frontend theme separately from the admin theme and check any
   route-specific theme negotiation or multisite URI.
3. Read the actual custom theme `.info.yml`, `.libraries.yml`, active theme
   settings, `.theme` file, all templates/SDCs, CSS/SCSS/LESS, JavaScript,
   build manifests, and config placements. Inventory active versus unused,
   generated, and historical assets.
4. Record the currently loaded Bootstrap CSS/JS source and version from a
   representative rendered page. A Composer package or a Barrio directory
   alone does not prove that Bootstrap 5 is active.
5. Capture the existing layout and representative desktop/mobile states.
   Inventory all existing region keys and blocks before making changes.
6. Use the project's normal snapshot/backup procedure before risky dependency
   or configuration changes. Do not point local migration commands at
   production.

## 2. Install/enable Barrio and preserve dependencies

Consult the local curated module list and external reference-source guidance
before introducing contrib dependencies. Check Composer and Drupal discovery:

```bash
ddev composer show drupal/bootstrap_barrio
ddev drush theme:list
```

If Barrio is absent, select a release compatible with the installed Drupal,
PHP, and Bootstrap major versions, then require it through DDEV. Enable Barrio
before using it as the base:

```bash
ddev composer require drupal/bootstrap_barrio
ddev drush theme:enable bootstrap_barrio -y
```

Use the actual pinned version policy for the project; do not treat the example
require command as authorization to change dependency constraints.

Change only the custom theme's base declaration:

```yaml
base theme: bootstrap_barrio
```

Replace an explicit dependency on `bootstrap:bootstrap` only if the theme
declares one. Retain all custom regions and unrelated dependencies.

Do not remove `drupal/bootstrap` or other old packages just because the custom
theme no longer inherits them. Check the Composer dependency graph (for
example, `ddev composer why drupal/bootstrap`) and all consumers first.

## 3. Migrate library and framework asset wiring

Review the installed Barrio version's actual library names, dependencies,
configuration schema, and supported Bootstrap source options. Do not perform a
global text replacement of `bootstrap`.

The custom theme should extend Barrio's styling library when it needs to add
its own assets:

```yaml
# custom_theme.info.yml
libraries-extend:
  bootstrap_barrio/global-styling:
    - custom_theme/framework
```

Declare only custom assets in the custom library. Use valid Drupal CSS groups,
declare actual Drupal dependencies, and keep plugin dependencies separate from
the Bootstrap framework:

```yaml
# custom_theme.libraries.yml
framework:
  css:
    component:
      css/custom.css: {}
      css/slick.css: {}
    theme:
      css/fontawesome.css: {}
  js:
    js/custom.js: {}
  dependencies:
    - core/jquery
    - core/drupal
    - core/once
```

This is an illustrative shape, not a copy-ready inventory; preserve the
project's real plugin files and library order.

Choose exactly one Bootstrap 5 provider:

- Barrio's supported CDN library/source, selected with the active theme
  setting; or
- a verified local Bootstrap 5 build supported by that Barrio release and the
  project's existing build pipeline.

Do not load Bootstrap CSS or JavaScript a second time from a custom library,
Twig `<link>`/`<script>` elements, a CDN, or a separately copied
`bootstrap.min.js`. Barrio's global library may already provide the Bootstrap
bundle and Popper; verify the dependency chain rather than adding another one.
Never copy assets into or patch `core/` or `themes/contrib/`.

Move custom plugin CSS/JS from hard-coded `html.html.twig` tags into Drupal
libraries so Drupal can order and aggregate them. Keep third-party assets such
as Slick or Fancybox if the site still uses them; replacing Bootstrap does not
automatically replace those plugins.

## 4. Migrate Twig and preserve custom page behavior

Inventory every Twig template, component, Views custom text field, and markup
string in configuration that emits Bootstrap-specific classes or attributes.
Compare overrides with the installed Barrio/core template version; migrate the
existing markup in context. Do not overwrite bespoke templates wholesale.

Common Bootstrap 3-to-5 changes (validate behavior before applying):

| Legacy pattern | Typical Bootstrap 5 direction |
|---|---|
| `navbar-default` | Rebuild with the actual Bootstrap 5 navbar structure or retain a clearly named custom class and style it directly |
| `navbar-btn pull-left` | Brand/link markup with `navbar-brand` and, if appropriate, `float-start` |
| `pull-left` / `pull-right` | `float-start` / `float-end` |
| `glyphicon glyphicon-search` | A maintained icon system already loaded by the site, such as Font Awesome |
| `well` | A card or a custom class when the legacy visual treatment is bespoke |
| `data-toggle`, `data-target`, `data-dismiss` | `data-bs-toggle`, `data-bs-target`, `data-bs-dismiss` |
| `hidden-*` / `visible-*` | Responsive `d-*` utilities chosen to preserve the original display at each breakpoint |
| Bootstrap 3 plugin jQuery calls | Bootstrap 5 JavaScript API or data attributes |

Do not translate classes mechanically where Bootstrap 5 changed the component
structure. In particular, a Bootstrap 3 navbar is not a valid Bootstrap 5
navbar simply because `navbar-default` was renamed. Preserve rendered Drupal
variables, attributes, translation, contextual links, title prefixes/suffixes,
cache metadata, and accessible names. Avoid adding `|raw` as a migration
shortcut.

### Page template example

For a custom `page.html.twig`, keep the custom regions and layout; change only
legacy Bootstrap markup that the active Bootstrap 5 CSS/API no longer supports.
Do not copy Barrio's default page template over a working, bespoke template.

SITE page template's header migration example:

```twig
{# Before: Bootstrap 3 navbar styles and pull utility. #}
<header id="navbar" role="banner" class="navbar navbar-default">
  <a class="logo navbar-btn pull-left" href="{{ front_page }}">
    <img src="{{ logo }}" alt="{{ 'Home'|t }}">
  </a>
</header>

{# After: keep SITE-specific branding; use current utilities. #}
<header id="navbar" role="banner" class="navbar site-navbar">
  <a class="logo navbar-brand float-start" href="{{ front_page }}">
    <img src="{{ logo }}" alt="{{ 'Home'|t }}">
  </a>
</header>
```

In the actual template, desktop and mobile header variants, menus, social
links, breadcrumbs, custom banners, sidebars, main content, activities, and
footer remain custom. Existing regions such as `menufish`, `menufishmovil`,
`breadcrumbsunat`, `sidebar_first`, `sidebar_second`, `content`, and `footer`
must continue to render from those same keys. Verify each region renders once,
in the intended position, with both empty and populated states. Do not rename
regions to Barrio defaults as a shortcut.

The `html.html.twig` migration should let Drupal's placeholders and libraries
own asset output. Remove hard-coded copies only after moving/confirming each
asset in a library. Keep `page_top`, `page`, `page_bottom`, the skip link, and
`js-bottom-placeholder`. Remove stale framework-only classes such as
`has-glyphicons` only after confirming nothing still depends on them. Move
inline initialization into an appropriately ordered behavior/library instead
of running it in the document head before its plugin is available.

Example: replace inline links and scripts in the head with library attachment:

```twig
<head>
  <css-placeholder token="{{ placeholder_token|raw }}">
  <js-placeholder token="{{ placeholder_token|raw }}">
</head>
<body{{ attributes }}>
  {{ page_top }}
  {{ page }}
  {{ page_bottom }}
  <js-bottom-placeholder token="{{ placeholder_token|raw }}">
</body>
```

Drupal prints the assets attached through theme libraries; do not add literal
Bootstrap tags alongside these placeholders.

### Component, Views, and icon examples

Preserve a bespoke Bootstrap 3 `.well` look without depending on a removed
framework class by introducing a custom component class:

```css
.site-sidebar-panel {
  min-height: 20px;
  padding: 19px;
  margin-bottom: 20px;
  background-color: #f5f5f5;
  border: 1px solid #e3e3e3;
  border-radius: 4px;
  box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.05);
}
```

```twig
<aside class="site-sidebar-panel" role="complementary">
  {{ page.sidebar_first }}
</aside>
```

Replace a Bootstrap-provided icon only after confirming the replacement
library is loaded. For example, in Views custom text or menu block config:

```html
<!-- Old Glyphicons dependency -->
<span class="glyphicon glyphicon-search" aria-hidden="true"></span>

<!-- Existing Font Awesome library in the SUNAT theme -->
<span class="fa fa-search" aria-hidden="true"></span>
```

Keep decorative icons hidden from assistive technology and make the control
itself have an accessible name. If using Bootstrap 5 collapse/modal attributes,
use the correct `data-bs-*` names and test focus, keyboard operation, expanded
state, and dismissal behavior.

## 5. Update CSS, SCSS, or LESS sources

First identify the actual source of the loaded stylesheet. Do not edit
generated CSS in place if there is a maintained Sass/Less source and a build
pipeline. Do not create a Sass build step solely because a migration guide
mentions Sass.

For every active stylesheet:

- Replace Bootstrap 3/4 selectors with Bootstrap 5 utilities/components or
  stable project-owned classes.
- Recreate bespoke look-and-feel with custom classes rather than relying on
  removed framework selectors.
- Review grid widths, offsets, floats, navbar layouts, forms, wells/panels,
  visibility, icon fonts, and all responsive breakpoints.
- Preserve custom brand tokens; do not silently adopt Barrio or Bootstrap
  defaults.
- Use the existing build pipeline and test output. Route all Node/Sass/Less
  commands through `ddev exec`.
- Do not modify vendor/contrib framework sources. Retire an old source only
  after proving it is not loaded or imported.

For an existing SCSS build, update its maintained token and breakpoint
overrides before the matching Bootstrap 5 imports, and use the installed
Barrio/Bootstrap version's documented import pipeline. The following shows
the kind of project-owned values to review; it is not a complete, universal
Bootstrap import sequence:

```scss
$primary: #0157a4;
$grid-breakpoints: (
  xs: 0,
  sm: 576px,
  md: 768px,
  lg: 992px,
  xl: 1200px,
  xxl: 1400px
);

// Keep the installed Barrio/Bootstrap release's supported import/build order.
```

Update old `@media` rules or legacy breakpoint names only when the Bootstrap 5
breakpoint reproduces the design. Confirm the actual emitted CSS at boundary
widths. Do not load a second full Bootstrap CSS build if Barrio already emits
the framework.

## 6. Update JavaScript and third-party plugin integration

Search for Bootstrap's jQuery plugins, old constructor APIs, event names,
data attributes, and custom selectors that target removed markup. Use the
installed Bootstrap 5 API. Keep application jQuery where other Drupal or
theme plugins still need it.

For example, replace a Bootstrap 3 jQuery modal invocation with the Bootstrap
5 bundle API:

```js
const modalElement = document.getElementById("exampleModal");
if (modalElement && window.bootstrap?.Modal) {
  window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
}
```

For exmple custom script migrated modal display to
`window.bootstrap.Modal.getOrCreateInstance(...).show()` and replaced search
and fullscreen Glyphicon markup with Font Awesome. Load the custom script only
after the Barrio Bootstrap bundle and retain Drupal behaviors/`once` patterns
where AJAX can reattach content.

Audit third-party plugins independently. For example, jQuery
4.0.0-beta.2 was present while Slick 1.8.1 expected the removed `jQuery.type`
utility. A compatibility shim was present in the custom script. Verify the
actual loaded jQuery/Slick versions and the shim's ordering on routes using
Slick; do not attribute that compatibility issue to Barrio or conceal runtime
errors with broad catches.

## 7. Migrate Drupal configuration deliberately

Check the installed version's real config names and schema. Configuration
needed by a typical migration can include:

| Configuration | Migration purpose |
|---|---|
| `composer.json` / `composer.lock` | Install and lock a compatible Barrio release; review indirect dependency changes |
| `config/sync/core.extension.yml` | Record `bootstrap_barrio` as an installed theme while retaining the existing custom theme and any still-required `bootstrap` package/theme |
| `config/sync/<custom_theme>.settings.yml` | Select the verified Barrio Bootstrap source and retain valid custom theme settings |
| `config/sync/bootstrap_barrio.settings.yml` | Track Barrio-owned settings only when intentionally part of the project's sync |
| `block.block.*.yml` | Preserve block placement and existing custom region associations |
| `views.view.*.yml` and other config with embedded markup | Replace obsolete Bootstrap/icon classes in exported markup only where necessary |

For example, the actual sync changes included:

- Added Barrio to the `theme` list in `core.extension.yml`; the existing
  `bootstrap` theme entry remained.
- Set `bootstrap_barrio_source: bootstrap_barrio/bootstrap_cdn` in
  `sunat.settings.yml`. Verify the installed Barrio library ID and its actual
  CDN version; this site's selected Barrio CDN library supplied Bootstrap
  5.2.0 during validation.
- Added/updated Barrio theme-owned settings and default
  `block.block.bootstrap_barrio_*` config after enabling Barrio.
- Updated menu block and Views custom markup that directly emitted
  Glyphicons.

Do not change the frontend/admin theme selection unintentionally. Retain the
current custom frontend theme, and verify any admin theme stays unchanged.
Check config schema and setting names in the installed package; do not copy
configuration from another Barrio release.

### Config import/export caution

An imported database may already differ widely from `config/sync`. Enabling a
theme can also create config entities for default blocks/view modes. A
`ddev drush config:export -y` can therefore change or add many unrelated
files. Before and after export:

```bash
git status --short
ddev drush config:status
git diff -- config/sync/
```

Review every export hunk, separate intended migration settings/entities from
database drift or unrelated local changes, and never use a broad `git restore`
or blindly commit the entire export. The SUNAT export included broad unrelated
config reserialization and an unrelated favicon URL change. These are a warning
to inspect carefully, not changes to reproduce as part of a Barrio migration.
Do not run another export just to make status appear clean. Run
`ddev drush config:status` after deliberate reconciliation.

For edits made in the Drupal UI, follow the repository rule to export config;
the export still needs review before staging. After config/module/theme
changes, clear cache:

```bash
ddev drush cr
```

## 8. Validate exact behavior

Run scoped scans over maintained sources, exported config, library declarations,
and representative rendered HTML. Search for at least:

- `bootstrap/framework` and old direct Bootstrap asset URLs/files;
- `glyphicon`, `navbar-default`, `navbar-btn`, `pull-left/right`,
  `.well`, `.panel`, `.btn-default`, `form-group`, `col-xs-*`,
  `hidden-*` / `visible-*`, and Bootstrap 3 `data-*` plugin attributes;
- Bootstrap JS jQuery APIs, duplicate Bootstrap assets, and stale selectors in
  custom JS/CSS.

Review matches individually. A match in historical content or an unreferenced
legacy file is not proof that runtime migration failed, but it must not be
confused with a completed migration if it is still loaded or imported.

Use the actual build scripts if there is an active source/build pipeline:

```bash
ddev exec "cd web/themes/custom/<theme> && <existing-build-command>"
```

Do not invent a Gulp task. Then validate:

1. `ddev drush cr` succeeds.
2. `ddev drush config:status` has only expected differences.
3. The local site returns successfully; representative anonymous and
   authenticated routes have no migration-related PHP/watchdog or browser
   console errors.
4. Browser network/DOM checks show one intended Bootstrap 5 CSS/JS source,
   correct custom assets, no legacy Bootstrap runtime files, and no migration
   asset 404s.
5. Test menus, breakpoints around old/new widths, sidebars, forms/errors,
   Views, modal/lightbox/collapse controls, AJAX attachment, keyboard/focus,
   and empty/populated regions.
6. Review recent logs:

   ```bash
   ddev drush watchdog:show --count=20
   ```

   Separate pre-existing imported-content or missing-public-file warnings from
   migration regressions. In the SUNAT database import, public image files
   were missing locally and caused image warnings/404s; this was a files
   import issue, not a Barrio asset failure.
7. Run `git diff --check`; review the final diff and keep unrelated worktree
   changes untouched.

Do not claim full migration completion while active Bootstrap 3/4 framework
assets or active legacy markup remain. Report unverified routes, historical
files, missing media, dependency constraints, and config drift explicitly.
