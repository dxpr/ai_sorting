<?php

namespace Drupal\ai_sorting\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;

/**
 * Service for managing total trials state.
 */
class TotalTrialsService {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs a new TotalTrialsService object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   */
  public function __construct(Connection $database, DateFormatterInterface $date_formatter) {
    $this->database = $database;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * Retrieves the total trials for a specific view and display.
   *
   * @param string $view_id
   *   The ID of the view.
   * @param string $display_id
   *   The ID of the display.
   *
   * @return int|null
   *   The total trials count or NULL if not found.
   */
  public function getTotalTrials(string $view_id, string $display_id): ?int {
    $record = $this->database->select('ai_sorting_total_trials', 'ast')
      ->fields('ast', ['total_trials'])
      ->condition('ast.view_id', $view_id)
      ->condition('ast.display_id', $display_id)
      ->execute()
      ->fetchField();

    return $record !== FALSE ? (int) $record : NULL;
  }

  /**
   * Sets the total trials for a specific view and display.
   *
   * @param string $view_id
   *   The ID of the view.
   * @param string $display_id
   *   The ID of the display.
   * @param int $total_trials
   *   The total trials count.
   */
  public function setTotalTrials(string $view_id, string $display_id, int $total_trials): void {
    $existing = $this->getTotalTrials($view_id, $display_id);

    if ($existing !== NULL) {
      $this->database->update('ai_sorting_total_trials')
        ->fields(['total_trials' => $total_trials, 'changed' => REQUEST_TIME])
        ->condition('view_id', $view_id)
        ->condition('display_id', $display_id)
        ->execute();
    }
    else {
      $this->database->insert('ai_sorting_total_trials')
        ->fields([
          'view_id' => $view_id,
          'display_id' => $display_id,
          'total_trials' => $total_trials,
          'created' => REQUEST_TIME,
          'changed' => REQUEST_TIME,
        ])
        ->execute();
    }
  }

  /**
   * Increments the total trials for a specific view and display.
   *
   * @param string $view_id
   *   The ID of the view.
   * @param string $display_id
   *   The ID of the display.
   * @param int $increment
   *   The amount to increment by.
   *
   * @return int
   *   The new total trials count.
   */
  public function incrementTotalTrials(string $view_id, string $display_id, int $increment = 1): int {
    $this->database->merge('ai_sorting_total_trials')
      ->key(['view_id' => $view_id, 'display_id' => $display_id])
      ->fields(['total_trials' => $increment, 'created' => REQUEST_TIME, 'changed' => REQUEST_TIME])
      ->expression('total_trials', 'total_trials + :inc', [':inc' => $increment])
      ->execute();

    return $this->getTotalTrials($view_id, $display_id) ?? $increment;
  }

  /**
   * Calculates and updates the total trials for a specific view and display.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view executable object.
   *
   * @return int
   *   The total number of trials.
   */
  public function calculateAndUpdateTotalTrials($view) {
    // Clone the original query.
    $cloned_query = clone $view->build_info['query'];

    // Remove the LIMIT from the cloned query.
    $cloned_query->range();

    // Remove the node_ucb1_score expression from the query.
    $expressions = &$cloned_query->getExpressions();
    unset($expressions['node_ucb1_score']);

    // Remove the ORDER BY clause that uses node_ucb1_score.
    $orderby = &$cloned_query->getOrderBy();
    foreach ($orderby as $key => $order) {
      if (strpos($key, 'node_ucb1_score') !== false) {
        unset($orderby[$key]);
      }
    }

    // Add SUM of ai_sorting_trials to the query.
    $cloned_query->addExpression('SUM(COALESCE(node_counter.ai_sorting_trials, 0))', 'total_ai_sorting_trials');

    // Remove all fields to ensure we're only getting the sum.
    $fields = &$cloned_query->getFields();
    $fields = [];

    // Execute the cloned query and fetch the result.
    try {
      $cloned_result = $cloned_query->execute();
      $total_trials = (int) $cloned_result->fetchField();

      // Update the total trials in the database.
      $view_id = $view->id();
      $display_id = $view->current_display;

      $this->database->merge('ai_sorting_total_trials')
        ->key(['view_id' => $view_id, 'display_id' => $display_id])
        ->fields([
          'total_trials' => $total_trials,
          'changed' => \Drupal::time()->getRequestTime(),
        ])
        ->execute();

      return $total_trials;
    }
    catch (\Exception $e) {
      \Drupal::logger('ai_sorting')->error('Error executing Cloned Query: @error', ['@error' => $e->getMessage()]);
      return 0;
    }
  }
}