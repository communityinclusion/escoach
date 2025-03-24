<?php

namespace Drupal\entity_usage\Plugin\EntityUsage\Track;

use Drupal\Component\Utility\Html;
use Drupal\entity_usage\EntityUsageTrackUrlUpdateInterface;

/**
 * Tracks usage of entities referenced from regular HTML Links.
 *
 * @EntityUsageTrack(
 *   id = "html_link",
 *   label = @Translation("HTML links"),
 *   description = @Translation("Tracks relationships created with standard links inside formatted text fields."),
 *   field_types = {"text", "text_long", "text_with_summary"},
 *   source_entity_class = "Drupal\Core\Entity\FieldableEntityInterface",
 * )
 */
class HtmlLink extends TextFieldEmbedBase implements EntityUsageTrackUrlUpdateInterface {

  /**
   * {@inheritdoc}
   */
  public function parseEntitiesFromText($text) {
    $dom = Html::load($text);
    $xpath = new \DOMXPath($dom);
    $entities = [];

    // Loop trough all the <a> elements that don't have the LinkIt attributes.
    $xpath_query = "//a[@href != '']";
    foreach ($xpath->query($xpath_query) as $element) {
      /** @var \DOMElement $element */
      try {
        // Get the href value of the <a> element.
        $href = $element->getAttribute('href');
        $entity_info = $this->urlToEntity->findEntityIdByUrl($href);
        // If no entity info could be retrieved from this URL, skip this link.
        if (empty($entity_info)) {
          continue;
        }
        ['type' => $entity_type_id, 'id' => $entity_id] = $entity_info;
        $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
        /** @var array<int, array{uuid: string}> $result */
        $result = $this->entityTypeManager->getStorage($entity_type_id)
          ->getAggregateQuery()
          ->accessCheck(FALSE)
          ->condition($entity_type->getKey('id'), $entity_id)
          ->groupBy($entity_type->getKey('uuid'))
          ->execute();
        if (empty($result)) {
          // Entity does not exist.
          continue;
        }
        if ($element->hasAttribute('data-entity-uuid')) {
          // Normally the Linkit plugin handles when a element has this
          // attribute, but sometimes users may change the HREF manually and
          // leave behind the wrong UUID.
          $data_uuid = $element->getAttribute('data-entity-uuid');
          // If the UUID is the same as found in HREF, then skip it because
          // it's LinkIt's job to register this usage.
          if ($data_uuid === $result[0]['uuid']) {
            continue;
          }
        }
        // Inform the method registering this usage that it's not necessary to
        // check existence of this entity again, by adding a prefix.
        $entities[$result[0]['uuid']] = self::VALID_ENTITY_ID_PREFIX . $entity_type_id . '|' . $entity_id;
      }
      catch (\Exception $e) {
        // Do nothing.
      }
    }

    return $entities;
  }

}
