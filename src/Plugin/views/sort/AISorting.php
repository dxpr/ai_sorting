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
    $this->query->addOrderBy(
      NULL,
      "COALESCE(node_counter.totalcount, 0) / GREATEST(COALESCE(node_counter.ai_sorting_trials, 1), 1)",
      $this->options['order'],
      'node_ucb2_score'
    );
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