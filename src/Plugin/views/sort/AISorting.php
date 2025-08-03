<?php

namespace Drupal\ai_sorting\Plugin\views\sort;

use Drupal\views\Plugin\views\sort\SortPluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\rl\Service\ExperimentManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * AI-based sorting plugin for Views using Reinforcement Learning.
 *
 * @ViewsSort("ai_sorting")
 */
class AISorting extends SortPluginBase {

  /**
   * The RL experiment manager.
   *
   * @var \Drupal\rl\Service\ExperimentManagerInterface
   */
  protected $experimentManager;

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
   * @param \Drupal\rl\Service\ExperimentManagerInterface $experiment_manager
   *   The RL experiment manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ExperimentManagerInterface $experiment_manager, RequestStack $request_stack) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->experimentManager = $experiment_manager;
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
      $container->get('rl.experiment_manager'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['alpha'] = ['default' => 2.0];
    $options['order'] = ['default' => '']; // We don't use this, but prevents warnings.
    $options['cache_max_age'] = ['default' => 60]; // Default max-age set to 60 seconds.
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();

    // Generate experiment UUID from view and display
    $experiment_uuid = sha1($this->view->id() . ':' . $this->view->current_display);

    // Get UCB1 scores from RL module
    $alpha = (float) $this->options['alpha'];
    $scores = $this->experimentManager->getUCB1Scores($experiment_uuid, $alpha);

    if (empty($scores)) {
      // No data yet, fall back to random order
      $this->query->addOrderBy(NULL, 'RAND()', 'DESC', 'ai_sorting_fallback');
      return;
    }

    // Build a CASE statement to order by UCB1 scores
    $case_statement = 'CASE ' . $this->tableAlias . '.nid ';
    foreach ($scores as $nid => $score) {
      $case_statement .= "WHEN " . (int) $nid . " THEN " . (float) $score . " ";
    }
    $case_statement .= 'ELSE 0 END';

    // Add small random noise to break ties
    $order_formula = $case_statement . ' + (RAND() * 0.000001)';

    $this->query->addOrderBy(
      NULL,
      $order_formula,
      'DESC',
      'ai_sorting_score'
    );

    // Set the cache-control header
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

    // Regex to find max-age and s-maxage values.
    $max_age_regex = '/max-age=(\d+)/';
    $s_maxage_regex = '/s-maxage=(\d+)/';

    // Function to replace the age value if it's higher than the new max_age.
    $replace_age = function ($matches) use ($max_age) {
      $current_age = (int) $matches[1];
      return $current_age > $max_age ? str_replace($matches[1], $max_age, $matches[0]) : $matches[0];
    };

    // Check and replace max-age.
    if (preg_match($max_age_regex, $existing_cache_control)) {
      $existing_cache_control = preg_replace_callback($max_age_regex, $replace_age, $existing_cache_control);
    }

    // Check and replace s-maxage.
    if (preg_match($s_maxage_regex, $existing_cache_control)) {
      $existing_cache_control = preg_replace_callback($s_maxage_regex, $replace_age, $existing_cache_control);
    }

    // If neither max-age nor s-maxage is present, do nothing.
    if (!preg_match($max_age_regex, $existing_cache_control) && !preg_match($s_maxage_regex, $existing_cache_control)) {
      return;
    }

    // Set the updated Cache-Control header.
    $response = new Response();
    $response->headers->set('Cache-Control', $existing_cache_control);
    $response->prepare($request);
    $response->send();
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    
    // Remove the order selector since we always use DESC for UCB1 scores
    unset($form['order']);

    $form['ai_sorting_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('AI Sorting Settings'),
      '#open' => TRUE,
      '#description' => $this->t('<strong>What does AI Sorting do?</strong><br>
        AI Sorting uses machine learning to automatically order content based on user engagement. It learns which content gets clicked more often and gradually shows the most engaging content first, while still giving new content a chance to be discovered.<br><br>
        <strong>How it works:</strong><br>
        • <em>Turns</em>: When content appears in this view<br>
        • <em>Rewards</em>: When users click on that content<br>
        • The algorithm balances showing popular content with exploring new options<br><br>
        <strong>Best for:</strong> News feeds, product listings, blog posts, or any content where user engagement matters.'),
    ];

    $url = Url::fromUri('https://medium.com/analytics-vidhya/multi-armed-bandit-analysis-of-upper-confidence-bound-algorithm-4b84be516047', [
      'attributes' => [
        'target' => '_blank',
        'rel' => 'noopener noreferrer',
      ],
    ]);
    $link = Link::fromTextAndUrl($this->t('Learn more about the UCB1 algorithm'), $url);

    // Add an advanced details element for alpha and cache settings.
    $form['ai_sorting_settings']['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced Settings'),
      '#open' => FALSE,
    ];

    $form['ai_sorting_settings']['advanced']['alpha'] = [
      '#type' => 'number',
      '#title' => $this->t('Exploration-Exploitation Balance (Alpha)'),
      '#default_value' => $this->options['alpha'],
      '#min' => 0,
      '#max' => 10,
      '#step' => 0.1,
      '#description' => $this->t('Controls the balance between exploring new options and exploiting known successful options. Higher values encourage more exploration. Typical values range from 1 to 3. @link', [
        '@link' => $link->toString(),
      ]),
      '#field_prefix' => $this->t('Alpha:'),
      '#field_suffix' => $this->t('(0.0 to 10.0)'),
      '#required' => TRUE,
    ];

    $form['ai_sorting_settings']['advanced']['cache_max_age'] = [
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
      '#description' => $this->t('This is used as the value for max-age in Cache-Control headers. Note: This setting overrides the page cache time and is specific to the AI sorting algorithm. For views sorting fewer than 10,000 nodes, a 1-minute cache lifetime is recommended. For views sorting more than 10,000 nodes, a 5-minute cache lifetime is recommended. Be aware that a longer cache time may affect the exploration aspect of the algorithm, which benefits from up-to-date data.'),
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
    if (isset($options['ai_sorting_settings']['advanced']['alpha'])) {
      $this->options['alpha'] = $options['ai_sorting_settings']['advanced']['alpha'];
    }

    // Save the cache_max_age value
    if (isset($options['ai_sorting_settings']['advanced']['cache_max_age'])) {
      $this->options['cache_max_age'] = $options['ai_sorting_settings']['advanced']['cache_max_age'];
    }

    // Clear any caches if necessary
    \Drupal::service('plugin.manager.views.sort')->clearCachedDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    $summary = [];
    $summary[] = $this->t('Alpha: @alpha', ['@alpha' => $this->options['alpha']]);
    
    $cache_max_age = $this->options['cache_max_age'];
    if ($cache_max_age == 0) {
      $summary[] = $this->t('Cache: Never cache');
    } elseif ($cache_max_age < 60) {
      $summary[] = $this->t('Cache: @seconds seconds', ['@seconds' => $cache_max_age]);
    } elseif ($cache_max_age < 3600) {
      $minutes = $cache_max_age / 60;
      $summary[] = $this->t('Cache: @minutes minute(s)', ['@minutes' => $minutes]);
    } else {
      $hours = $cache_max_age / 3600;
      $summary[] = $this->t('Cache: @hours hour(s)', ['@hours' => $hours]);
    }
    
    return implode(', ', $summary);
  }

}