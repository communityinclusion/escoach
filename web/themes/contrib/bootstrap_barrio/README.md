## Bootstrap 5 theme for Drupal 10

Bootstrap Barrio is a theme based on
Bootstrap 5, a sleek, intuitive, and powerful front-end framework
for faster and easier web development.
Bootstrap Barrio focuses on layout flexibility
and customization using theme parameters.

- Layout is handled by Bootstrap.
- Two columns, with configurable widths.
- Fixed and fluid layouts.
- Configurable header behavior, using Bootstrap "fixed to top".
- Any block could be defined as "modal" with one click using Skinr.
- Full screen width slider region.

## Installation

Install Bootstrap theme with `composer require drupal/bootstrap`
Install Bootstrap Library module to load the libraries.

## SASS

We strongly recommend using https://www.drupal.org/project/bootstrap_sass
as a subtheme starter

## AI Agent Skills

This repository includes Claude Code skills for creating and maintaining
subthemes and for migrating Bootstrap-based themes:

- [`.claude/skills/bootstrap-barrio-subtheme/SKILL.md`](.claude/skills/bootstrap-barrio-subtheme/SKILL.md) -
  Bootstrap Barrio subtheme specialist for Drupal 11-12. Use it when creating a
  custom subtheme from the Bootstrap 5 Sass subtheme base and installation
  shell script, configuring Sass/Gulp assets, wiring libraries, and
  troubleshooting Bootstrap Barrio theme setup.
- [`.claude/skills/bootstrap-to-barrio/SKILL.md`](.claude/skills/bootstrap-to-barrio/SKILL.md) -
  Migrates an existing Drupal custom theme in place from the Bootstrap contrib
  base theme to Bootstrap Barrio and Bootstrap 5, covering theme metadata,
  Twig, CSS/SCSS/LESS, JavaScript, framework assets, Drupal configuration, and
  validation.
- [`.claude/skills/bootstrap-3-to-5/SKILL.md`](.claude/skills/bootstrap-3-to-5/SKILL.md) -
  Plans and carries out a staged migration directly from Bootstrap 3 to
  Bootstrap 5, auditing legacy grid, components, forms, JavaScript, data
  attributes, utilities, assets, and custom styles.
- [`.claude/skills/bootstrap-4-to-5/SKILL.md`](.claude/skills/bootstrap-4-to-5/SKILL.md) -
  Guides an incremental, verifiable migration from Bootstrap 4 to Bootstrap 5,
  auditing breaking changes in markup, utilities, JavaScript, Sass, forms,
  components, and asset loading.
