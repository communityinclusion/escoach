<?php

namespace Drupal\nodeaccess;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\BundlePermissionHandlerTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Permissions callback.
 */
final class NodeGrantPermissions implements ContainerInjectionInterface {

  use StringTranslationTrait, BundlePermissionHandlerTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new FilterPermissions instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * Returns an array of additional permissions.
   *
   * @return array
   *   An array of permissions.
   */
  public function permissions() {
    return $this->generatePermissions($this->entityTypeManager->getStorage('node_type')->loadMultiple(), [
      $this,
      'buildPermissions',
    ]);
  }

  /**
   * Returns a list of node permissions defined for a given node type.
   *
   * @param \Drupal\node\NodeTypeInterface $node_type
   *   The node type.
   *
   * @return array
   *   An associative array of permission names and descriptions.
   */
  protected function buildPermissions(NodeTypeInterface $node_type) {
    $permissions = [];
    $node_type_id = $node_type->id();
    $node_type_label = $node_type->label();
    // Prefix `nodeaccess` to avoid conflicting with `grant node permissions`,
    // Without the prefix, if the content type is `node`, the same permission as
    // `grant node permissions` will be returned here.
    $permissions["nodeaccess grant $node_type_id permissions"] = [
      'title' => $this->t('%label: grant node permissions', ['%label' => $node_type_label]),
      'description' => [
        '#prefix' => '<em>',
        '#markup' => $this->t('Access the Grants tab of @label and set grants per node with this content type', ['@label' => $node_type_label]),
        '#suffix' => '</em>',
      ],
    ];
    return $permissions;
  }

}
