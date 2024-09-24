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
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

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
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

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
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, TotalTrialsService $total_trials_service, RequestStack $request_stack) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->totalTrialsService = $total_trials_service;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai_sorting.total_trials_service'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['alpha'] = ['default' => 2];
    $options['order'] = ['default' => '']; // We dont use, but unsetting results in adminSummary warning.

    // Add cache_max_age option with a default value.
    $options['cache_max_age'] = ['default' => 60]; // Default max-age set to 60 seconds.
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();

    // Retrieve the total_trials from the service.
    $view_id = $this->view->id();
    $display_id = $this->view->current_display;
    $totalTrials = $this->totalTrialsService->getTotalTrials($view_id, $display_id) ?? 1;

    // Retrieve the alpha parameter from options.
    $alpha = $this->options['alpha'];

    // Construct the UCB1 formula within the ORDER BY clause.
    $ucb1Formula = "(COALESCE(node_counter.totalcount, 0) / GREATEST(COALESCE(node_counter.ai_sorting_trials, 1), 1)) + " .
                  "SQRT((" . $alpha . " * LN(" . $totalTrials . ")) / GREATEST(COALESCE(node_counter.ai_sorting_trials, 1), 1)) + " .
                  "(RAND() * 0.000001)";

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

    // Set the cache-control header.
    $this->setCacheControlHeader();
  }

  /**
   * Sets the cache-control header with configurable max-age and s-maxage.
   */
  protected function setCacheControlHeader() {
    $request = $this->requestStack->getCurrentRequest();
    if ($request && $request->headers->has('X-Drupal-Cache')) {
      // This is an internal subrequest, so we shouldn't modify the headers.
      return;
    }

    // Retrieve the configured max-age.
    $max_age = isset($this->options['cache_max_age']) ? (int) $this->options['cache_max_age'] : 60;

    // Retrieve existing Cache-Control header if any.
    $existing_cache_control = $request->headers->get('Cache-Control', '');

    // Parse existing directives.
    $directives = [];
    if (!empty($existing_cache_control)) {
      $parts = explode(',', $existing_cache_control);
      foreach ($parts as $part) {
        $part = trim($part);
        if (strpos($part, '=') !== FALSE) {
          list($key, $value) = explode('=', $part, 2);
          $directives[strtolower($key)] = $value;
        }
        else {
          $directives[strtolower($part)] = TRUE;
        }
      }
    }

    // Update or add max-age and s-maxage.
    $directives['max-age'] = $max_age;
    $directives['s-maxage'] = $max_age;

    // Reconstruct the Cache-Control header.
    $new_cache_control = [];
    foreach ($directives as $key => $value) {
      if (is_bool($value)) {
        $new_cache_control[] = $key;
      }
      else {
        $new_cache_control[] = $key . '=' . $value;
      }
    }
    $new_cache_control_header = implode(', ', $new_cache_control);

    // Set the updated Cache-Control header.
    $response = new Response();
    $response->headers->set('Cache-Control', $new_cache_control_header);
    $response->prepare($request);
    $response->send();
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    
    // Remove the order selector
    unset($form['order']);

    $form['ucb1_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('AI Sorting Settings'),
      '#open' => TRUE,
    ];

    $url = Url::fromUri('https://medium.com/analytics-vidhya/multi-armed-bandit-analysis-of-upper-confidence-bound-algorithm-4b84be516047', [
      'attributes' => [
        'target' => '_blank',
        'rel' => 'noopener noreferrer',
      ],
    ]);
    $link = Link::fromTextAndUrl($this->t('Learn more about the UCB algorithm'), $url);

    $form['ucb1_settings']['tracking_method'] = [
      '#type' => 'select',
      '#title' => $this->t('Tracking Method'),
      '#options' => [
        'views_display_specific' => $this->t('Views-Display-Specific Click Tracking'),
        'statistics' => $this->t('Statistics Module'),
      ],
      '#default_value' => $this->options['tracking_method'] ?? 'views_display_specific',
      '#description' => $this->t('Select the method to track user interactions. The "Statistics Module" uses the built-in Drupal statistics module, while "Views-Display-Specific Click Tracking" captures precise user interactions within the specific Views display.'),
    ];

    // Add an advanced details element for alpha and cache settings.
    $form['ucb1_settings']['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced Settings'),
      '#open' => FALSE, // Ensure the details element is collapsed by default.
    ];

    $form['ucb1_settings']['advanced']['alpha'] = [
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

    $form['ucb1_settings']['advanced']['cache_max_age'] = [
      '#type' => 'select',
      '#title' => $this->t('Browser and proxy cache maximum age'),
      '#default_value' => $this->options['cache_max_age'],
      '#options' => [
        0 => $this->t('Never cache'),
        30 => $this->t('30 seconds'),
        60 => $this->t('1 minute'),
        120 => $this->t('2 minutes'),
        300 => $this->t('5 minutes'),
        600 => $this->t('10 minutes'),
      ],
      '#description' => $this->t('This is used as the value for max-age in Cache-Control headers. Note: This setting overrides the page cache time and is specific to the AI sorting algorithm. For views sorting fewer than 10,000 nodes, a 1-minute cache lifetime is optimal. For views sorting more than 10,000 nodes, a 5-minute cache lifetime is recommended. Be aware that a longer cache time may affect the exploration aspect of the algorithm, which benefits from up-to-date data.'),
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
    if (isset($options['ucb1_settings']['advanced']['alpha'])) {
      $this->options['alpha'] = $options['ucb1_settings']['advanced']['alpha'];
    }

    // Save the tracking method
    if (isset($options['ucb1_settings']['tracking_method'])) {
      $this->options['tracking_method'] = $options['ucb1_settings']['tracking_method'];
    }

    // Save the cache_max_age value
    if (isset($options['ucb1_settings']['advanced']['cache_max_age'])) {
      $this->options['cache_max_age'] = $options['ucb1_settings']['advanced']['cache_max_age'];
    }

    // Clear any caches if necessary
    \Drupal::service('plugin.manager.views.sort')->clearCachedDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    $summary = [];

    // Add tracking method to the summary.
    if (isset($this->options['tracking_method'])) {
      $summary[] = $this->t('Tracking method: @method', ['@method' => $this->options['tracking_method']]);
    }

    // Add alpha to the summary.
    $summary[] = $this->t('Alpha: @alpha', ['@alpha' => $this->options['alpha']]);

    // Add cache_max_age to the summary.
    if (isset($this->options['cache_max_age'])) {
      $summary[] = $this->t('Cache Max Age: @max_age seconds', ['@max_age' => $this->options['cache_max_age']]);
    }

    // Handle the 'order' key gracefully.
    if (isset($this->options['order']) && $this->options['order'] !== '') {
      $summary[] = $this->t('Order: @order', ['@order' => $this->options['order']]);
    }

    return implode(', ', $summary);
  }

}
