<?php

namespace Drupal\ai_sorting\Decorator;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\rl\Decorator\ExperimentDecoratorInterface;
use Drupal\views\ViewEntityInterface;

/**
 * Decorator service for AI Sorting experiments.
 */
class AiSortingExperimentDecorator implements ExperimentDecoratorInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new AiSortingExperimentDecorator.
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
  public function decorateExperiment(string $uuid): ?array {
    // Check if this UUID follows the AI Sorting pattern (sha1 of view:display)
    $view_storage = $this->entityTypeManager->getStorage('view');
    $views = $view_storage->loadMultiple();

    foreach ($views as $view) {
      /** @var \Drupal\views\ViewEntityInterface $view */
      foreach ($view->get('display') as $display_id => $display) {
        $test_uuid = sha1($view->id() . ':' . $display_id);
        if ($test_uuid === $uuid) {
          // Found matching view and display
          $view_url = Url::fromRoute('entity.view.edit_form', ['view' => $view->id()]);
          $view_link = Link::fromTextAndUrl($view->label(), $view_url);
          
          return [
            '#markup' => $view_link->toString() . ' (' . $display_id . ')',
          ];
        }
      }
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function decorateArm(string $experiment_uuid, string $arm_id): ?array {
    // For AI Sorting, arm_id should be a node ID
    if (is_numeric($arm_id)) {
      try {
        $node = $this->entityTypeManager->getStorage('node')->load($arm_id);
        if ($node) {
          $node_url = Url::fromRoute('entity.node.canonical', ['node' => $node->id()]);
          $node_link = Link::fromTextAndUrl($node->label(), $node_url);
          
          return [
            '#markup' => $node_link->toString(),
          ];
        }
      }
      catch (\Exception $e) {
        // If node loading fails, fall back to raw arm_id
      }
    }

    return NULL;
  }

}