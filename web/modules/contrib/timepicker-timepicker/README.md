# CONTENTS OF THIS FILE

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Maintainers


## INTRODUCTION

The Date Timepicker module adds one of 3 jQuery plugins to any DateTime field in
the Widget settings.

Features:
- Available for the DateTime default widget
- Custom options for each plugin supported
- Can be extended (add own jQuery Plugins) by custom module with the
  hook_timepicker_libraries()

- For a full description of the module visit:
  https://www.drupal.org/project/timepicker

- To submit bug reports and feature suggestions, or to track changes visit:
  https://www.drupal.org/project/issues/timepicker


## REQUIREMENTS

The Date Timepicker module requires the following libraries:

- [The Jonthornton jQuery Timepicker](https://github.com/jonthornton/jquery
-timepicker)
- [jQuery UI Timepicker](https://fgelinas.com/code/timepicker/)
- [jQuery Timepicker Addon](https://github.com/trentrichardson/jQuery
-Timepicker-Addon)


## INSTALLATION

- Install the Date Timepicker module as you would normally install a
  contributed Drupal module. Visit https://www.drupal.org/node/1897420 for
  further information.


## CONFIGURATION

- Navigate to Administration > Extend and enable the module.
- Extract the Jonthornton jQuery Timepicker library into the
  "/libraries/jonthornton-jquery-timepicker" folder.
- Extract the jQuery UI Timepicker library into the
  "/libraries/jquery-ui-timepicker" folder.
- Extract the jQuery Timepicker Addon library into the
  "/libraries/jquery-timepicker-addon" folder.
- Navigate to the Manage form display page of the entity type with Date
  field (the Date field type should have the "Date and Time" Date type).
- Select the "Date and Time" widget type.
- Use widget settings to enable the Timepicker, choose the Timepicker type
  and apply custom options.


MAINTAINERS
-----------

- [Ivan Tibezh (tibezh)](https://www.drupal.org/u/tibezh)
- [Lilian Catanoi (liliancatanoi90)](https://www.drupal.org/u/liliancatanoi90)

Supporting organization:

- [OPTASY](https://www.drupal.org/optasy)
