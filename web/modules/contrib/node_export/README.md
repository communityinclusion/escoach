# Node Export

This module allows users to export nodes and then import them it into another
site, or the same site.It allows users to export/import
nodes if they have the 'export nodes 'or 'export own nodes' permission, have node
access to view the node and create the node type, and the node type is not 
omitted in node export's settings. The module does not check access to the 
filter formats used by the node's fields; please keep this in mind when assigning 
permissions to user roles.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/node_export).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/node_export).


## Table of contents

- Requirements
- Installation
- Usage
- Configuration
- Maintainers
- Supporting organizations


## Requirements

This module requires no modules outside of Drupal core.


## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).


## Usage

1. To export nodes, either:
  - Use the 'Node export' tab on a node page.
  - Use the content tab (admin/content) and select the Export Option and then
    there are two ways:
    1. You can export all nodes of a particular content type.
    1. You can provide all the node ids you want to export
        wish to export and then choose 'Node export' under the
        'Update options'.

1. To import nodes that were exported with Node export, either:
  - Use the form at 'Node export: import' under 'Admin/content'.
  - Paste the export code you generated in export function and click submit.

1. You can also export the nodes using drush command.

  `drush ne-export 'nid' > 'filename'`

  - For example, If you want to export node with node id 10 and the filename is
    node.json then the command will be like:

   `drush ne-export 10 > node.json`

  - For more than one nodes, you can write their nids seprated by space.

    `drush ne-export  1  3  4 > node.json`


## Configuration

The module provides a configuration form under **Configuration > Content authoring > Node Export Settings**.


## Maintainers

- Gaurav Kapoor - [gaurav.kapoor](https://www.drupal.org/u/gauravkapoor)
- CG Monroe - [cgmonroe](https://www.drupal.org/u/cgmonroe)
- [danielb](https://www.drupal.org/u/danielb)
- Tushar Mahajan - [chia](https://www.drupal.org/u/chia)
- [James Andres](https://www.drupal.org/u/james-andres)


## Supporting organizations

- [OpenSense Labs](https://www.drupal.org/opensense-labs)
- [Axelerant](https://www.drupal.org/axelerant)
- [Cyber-Duck](https://www.drupal.org/cyber-duck)
