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

  public function addExpression($sql, $alias, $args = []) {
    $this->query->addExpression($sql, $alias, $args);
  }

  public function condition($field, $value, $operator = '=') {
    return $this->query->condition($field, $value, $operator);
  }

  /**
   * Add total sums for me and provider.
   */
  public function addSelectedSumsTotal($scope, $state = NULL) {
    $args = [];
    if ($scope == 'Me') {
      $and = 'email = :email';
      $args[':email'] = $this->email;
    }
    elseif ($scope == 'Provider') {
      $and = 'provider = :provider';
      $args[':provider'] = $this->provider;
    }
    elseif ($scope == 'State') {
      $and = 'state = :state';
      $args[':state'] = $state;
    }
    elseif ($scope == 'Observer') {
      $and = "(regcode <= 10000 OR regcode IS NULL)";
    }
    elseif ($scope == 'All') {
      $and = '1 = 1';
    }
    else {
      return;
    }

    $sql = sprintf('sum(case when (%s) then 1 else 0 end)',
      $and
    );

    $this->query->addExpression($sql, 'Total' . $scope, $args);
  }
}
