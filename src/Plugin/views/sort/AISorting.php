<?php

namespace Drupal\ai_sorting\Plugin\views\sort;

use Drupal\views\Plugin\views\sort\SortPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\views\Views;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\ai_sorting\Service\TotalTrialsService;
use Drupal\views\ViewsHandler;
use Drupal\Core\Link;

/**
 * AI-based sorting plugin for Views.
 *
 * @ViewsSort("ai_sorting")
 */
class AISorting extends SortPluginBase {

  /**
   * The TotalTrialsService.
   *
   * @var \Drupal\ai_sorting\Service\TotalTrialsService
   */
  protected $totalTrialsService;

  /**
   * Constructs a new AISorting object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\ai_sorting\Service\TotalTrialsService $total_trials_service
   *   The TotalTrialsService.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, TotalTrialsService $total_trials_service) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->totalTrialsService = $total_trials_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai_sorting.total_trials_service')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['alpha'] = ['default' => 2];
    // Remove the 'order' option
    unset($options['order']);
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();

    \Drupal::logger('ai_sorting')->notice('query() called');
    // Log the view and display ID.
    \Drupal::logger('ai_sorting')->notice('AI Sorting query() called for view: @view_id, display: @display_id', [
      '@view_id' => $this->view->id(),
      '@display_id' => $this->view->current_display,
    ]);

    // Retrieve the total_trials from the service.
    $view_id = $this->view->id();
    $display_id = $this->view->current_display;
    $totalTrials = $this->totalTrialsService->getTotalTrials($view_id, $display_id) ?? 1;

    // Retrieve the alpha parameter from options.
    $alpha = $this->options['alpha'];

    // Construct the UCB1 formula within the ORDER BY clause.
    $ucb1Formula = "(COALESCE(node_counter.totalcount, 0) / GREATEST(COALESCE(node_counter.ai_sorting_trials, 1), 1)) + " .
                  "SQRT(($alpha * LN($totalTrials)) / GREATEST(COALESCE(node_counter.ai_sorting_trials, 1), 1)) + " .
                  "(RAND() * 0.000001)";

    // Log the UCB1 formula.
    \Drupal::logger('ai_sorting')->notice('UCB1 Formula: @formula', ['@formula' => $ucb1Formula]);

    // Always use DESC order for UCB1 scores.
    $this->query->addOrderBy(
      NULL,
      $ucb1Formula,
      'DESC',
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

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    
    // Remove the order selector
    unset($form['order']);

    $form['ucb1_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('AI Sorting Settings'),
    ];

    $url = Url::fromUri('https://medium.com/analytics-vidhya/multi-armed-bandit-analysis-of-upper-confidence-bound-algorithm-4b84be516047', [
      'attributes' => [
        'target' => '_blank',
        'rel' => 'noopener noreferrer',
      ],
    ]);
    $link = Link::fromTextAndUrl($this->t('Learn more about the UCB algorithm'), $url);

    $form['ucb1_settings']['alpha'] = [
      '#type' => 'number',
      '#title' => $this->t('Exploration-Exploitation Balance'),
      '#default_value' => $this->options['alpha'],
      '#min' => 0,
      '#max' => 10,
      '#step' => 0.1,
      '#description' => $this->t('Controls the balance between exploring new options and exploiting known successful options. Higher values encourage more exploration. Typical values range from 1 to 3. A lower value (closer to 0) will favor showing content that has performed well in the past. A higher value will encourage trying out more varied content. @link', [
        '@link' => $link->toString(),
      ]),
      '#field_prefix' => $this->t('Alpha:'),
      '#field_suffix' => $this->t('(0.0 to 10.0)'),
      '#required' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    parent::submitOptionsForm($form, $form_state);

    $options = &$form_state->getValue('options');

    // Save the alpha value
    if (isset($options['ucb1_settings']['alpha'])) {
      $this->options['alpha'] = $options['ucb1_settings']['alpha'];
    }

    // If you have other options, save them here
    // For example:
    // $this->options['some_other_option'] = $options['some_other_option'];

    // Clear any caches if necessary
    \Drupal::service('plugin.manager.views.sort')->clearCachedDefinitions();
  }

}