<?php

namespace Drupal\ai_sorting\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Provides UCB2 calculation service for AI Sorting.
 */
class UCB2Calculator {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new UCB2Calculator object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   */
  public function __construct(Connection $database, StateInterface $state, ConfigFactoryInterface $config_factory) {
    $this->database = $database;
    $this->state = $state;
    $this->configFactory = $config_factory;
  }

  /**
   * Calculates UCB2 values for all nodes.
   *
   * @return array
   *   An array of UCB2 values keyed by node ID.
   */
  public function getUCB2Values() {
    $alpha = $this->configFactory->get('ai_sorting.settings')->get('alpha') ?? 0.01;
    $query = $this->database->select('node_counter', 'nc');
    $query->fields('nc', ['nid', 'totalcount', 'ai_sorting_trials']);
    $results = $query->execute()->fetchAll();

    $ucb2_values = [];
    $total_trials = array_sum(array_column($results, 'ai_sorting_trials'));

    foreach ($results as $result) {
      $ucb2_values[$result->nid] = $this->calculateUCB2(
        $result->totalcount,
        $result->ai_sorting_trials,
        $total_trials,
        $alpha
      );
    }

    return $ucb2_values;
  }

  /**
   * Calculates the UCB2 value for a single node.
   *
   * @param int $rewards
   *   The number of rewards (views) for the node.
   * @param int $trials
   *   The number of trials for the node.
   * @param int $total_trials
   *   The total number of trials across all nodes.
   * @param float $alpha
   *   The alpha parameter for the UCB2 algorithm.
   *
   * @return float
   *   The calculated UCB2 value.
   */
  protected function calculateUCB2($rewards, $trials, $total_trials, $alpha) {
    if ($trials == 0) {
      return PHP_FLOAT_MAX; // Ensure new items are tried
    }
    $exploitation = $rewards / $trials;
    $exploration = sqrt((1 + $alpha) * log($total_trials) / (2 * $trials));
    return $exploitation + $exploration;
  }
}