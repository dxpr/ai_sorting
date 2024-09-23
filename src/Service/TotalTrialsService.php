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
}