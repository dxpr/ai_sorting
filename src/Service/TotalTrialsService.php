<?php

namespace Drupal\ai_sorting\Service;

use Drupal\rl\Service\ExperimentManagerInterface;
use Drupal\views\ViewExecutable;

/**
 * Service to calculate and update total trials for AI sorting experiments.
 */
class TotalTrialsService {

  /**
   * The RL experiment manager.
   *
   * @var \Drupal\rl\Service\ExperimentManagerInterface
   */
  protected $experimentManager;

  /**
   * Constructs a new TotalTrialsService.
   *
   * @param \Drupal\rl\Service\ExperimentManagerInterface $experiment_manager
   *   The RL experiment manager.
   */
  public function __construct(ExperimentManagerInterface $experiment_manager) {
    $this->experimentManager = $experiment_manager;
  }

  /**
   * Calculates and updates total trials for a view.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view executable.
   *
   * @return int
   *   The total trials count.
   */
  public function calculateAndUpdateTotalTrials(ViewExecutable $view) {
    $experiment_uuid = sha1($view->id() . ':' . $view->current_display);
    return $this->experimentManager->getTotalTurns($experiment_uuid);
  }

  /**
   * Gets total trials for a specific experiment.
   *
   * @param string $view_id
   *   The view ID.
   * @param string $display_id
   *   The display ID.
   *
   * @return int
   *   The total trials count.
   */
  public function getTotalTrials($view_id, $display_id) {
    $experiment_uuid = sha1($view_id . ':' . $display_id);
    return $this->experimentManager->getTotalTurns($experiment_uuid);
  }

}