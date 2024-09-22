<?php

namespace Drupal\ai_sorting\Plugin\views\sort;

use Drupal\views\Plugin\views\sort\SortPluginBase;
use Drupal\views\ResultRow;
use Drupal\ai_sorting\Service\UCB2Calculator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\views\Views;

/**
 * AI-based sorting plugin for Views.
 *
 * @ViewsSort("ai_sorting")
 */
class AISorting extends SortPluginBase {

  /**
   * The UCB2 calculator service.
   *
   * @var \Drupal\ai_sorting\Service\UCB2Calculator
   */
  protected $ucb2Calculator;

  /**
   * Constructs a new AISorting object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\ai_sorting\Service\UCB2Calculator $ucb2_calculator
   *   The UCB2 calculator service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, UCB2Calculator $ucb2_calculator) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->ucb2Calculator = $ucb2_calculator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai_sorting.ucb2_calculator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();
  
    // Calculate the total number of trials across all nodes.
    // This is essential for the UCB1 exploration term.
    // Using a subquery to sum ai_sorting_trials from node_counter.
    // Note: Depending on the database size, this may impact performance.
    $totalTrialsSubquery = "(SELECT SUM(COALESCE(ai_sorting_trials, 1)) FROM node_counter)";
  
    // Define constants for the UCB1 algorithm.
    // alpha: exploration parameter. Typically set to 2 for UCB1.
    $alpha = 2;
  
    // Construct the UCB1 formula within the ORDER BY clause.
    // UCB1 = (totalcount / ai_sorting_trials) + sqrt((alpha * ln(totalTrials)) / ai_sorting_trials)
    $ucb1Formula = "(COALESCE(node_counter.totalcount, 0) / GREATEST(COALESCE(node_counter.ai_sorting_trials, 1), 1)) + " .
                  "SQRT(($alpha * LN($totalTrialsSubquery)) / GREATEST(COALESCE(node_counter.ai_sorting_trials, 1), 1)) + " .
                  "(RAND() * 0.000001)";
  
    // Add the ORDER BY clause with the UCB1 formula.
    $this->query->addOrderBy(
      NULL,
      $ucb1Formula,
      $this->options['order'],
      'node_ucb1_score'
    );
  
    // Add the necessary JOIN to the node_counter table.
    $join = Views::pluginManager('join')->createInstance('standard', [
      'table' => 'node_counter',
      'field' => 'nid',
      'left_table' => 'node_field_data',
      'left_field' => 'nid',
      'type' => 'LEFT',
    ]);
    $this->query->addRelationship('node_counter', $join, 'node_field_data');
  }
}