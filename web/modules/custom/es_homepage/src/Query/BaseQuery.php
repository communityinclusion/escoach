<?php

namespace Drupal\es_homepage\Query;

use Drupal\taxonomy\Entity\Term;
use function GuzzleHttp\Psr7\str;

/**
 * Base Query class.
 */
class BaseQuery {

  const BASE_TABLE = 'surveycampaign_results';

  const QUESTION_ID = 0;

  /**
   * The query object.
   *
   * @var \Drupal\Core\Database\Query\SelectInterface
   */
  protected $query;

  /**
   * The email address for the current user.
   *
   * @var string
   */
  protected $email;

  /**
   * The provider name of the current user.
   *
   * @var string
   */
  protected $provider;


  /**
   * The current placeholder index.
   *
   * @var int
   */
  private $valueIndex = 0;

  /**
   * Constructor.
   */
  public function __construct($email, $provider) {
    $this->email = $email;
    $this->provider = $provider;

    $database = \Drupal::database();
    $this->query = $database->select(self::BASE_TABLE, self::BASE_TABLE);

  }

  /**
   * Execute the query.
   */
  public function execute() {
    $results = $this->query->execute();
    $recordSet = [];
    foreach ($results as $result) {
      $recordSet[] = (array) $result;
    }

    return $recordSet;
  }

  /**
   * Keep a running count of the placeholder deltas.
   */
  protected function getValueIndex() {
    return $this->valueIndex++;
  }

}
