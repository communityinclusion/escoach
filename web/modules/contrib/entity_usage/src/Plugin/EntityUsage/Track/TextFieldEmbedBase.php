<?php

namespace Drupal\entity_usage\Plugin\EntityUsage\Track;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\entity_usage\EmbedTrackInterface;
use Drupal\entity_usage\EntityUsageTrackBase;

/**
 * Base class for plugins tracking usage in entities embedded in WYSIWYG fields.
 */
abstract class TextFieldEmbedBase extends EntityUsageTrackBase implements EmbedTrackInterface {

  /**
   * {@inheritdoc}
   */
  public function getTargetEntities(FieldItemInterface $item): array {
    $item_value = $item->getValue();
    if (empty($item_value['value'])) {
      return [];
    }
    $text = $item_value['value'];
    if ($item->getFieldDefinition()->getType() === 'text_with_summary') {
      $text .= $item_value['summary'];
    }
    $entities_in_text = $this->parseEntitiesFromText($text);
    $valid_entities = [];

    $uuids_by_type = [];
    foreach ($entities_in_text as $uuid => $entity_type) {
      // If the entity's existence has already been checked then do not recheck
      // this.
      if (str_starts_with($entity_type, self::VALID_ENTITY_ID_PREFIX)) {
        $valid_entities[] = substr($entity_type, strlen(self::VALID_ENTITY_ID_PREFIX));
      }
      else {
        $uuids_by_type[$entity_type][] = $uuid;
      }
    }

    foreach ($uuids_by_type as $entity_type => $uuids) {
      $target_type = $this->entityTypeManager->getDefinition($entity_type);
      // Check if the target entity exists since text fields are not
      // automatically updated when an entity is removed.
      $query = $this->entityTypeManager->getStorage($entity_type)
        ->getQuery()
        ->accessCheck(FALSE)
        ->condition($target_type->getKey('uuid'), $uuids, 'IN');
      $valid_entities = array_merge($valid_entities, array_values(array_unique(array_map(fn ($id) => $entity_type . '|' . $id, $query->execute()))));
    }

    return $valid_entities;
  }

}
