<?php

namespace Drupal\ai_sorting\Plugin\views\sort;

use Drupal\views\Plugin\views\sort\SortPluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\rl\Service\ExperimentManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

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
   * Logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

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
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ExperimentManagerInterface $experiment_manager, RequestStack $request_stack, LoggerChannelFactoryInterface $logger_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->experimentManager = $experiment_manager;
    $this->requestStack = $request_stack;
    $this->loggerFactory = $logger_factory;
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
      $container->get('request_stack'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    // We don't use this, but prevents warnings.
    $options['order'] = ['default' => ''];
    // Default max-age set to 60 seconds.
    $options['cache_max_age'] = ['default' => 60];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    try {
      $this->ensureMyTable();

      // Generate experiment UUID from view and display.
      $experiment_uuid = sha1($this->view->id() . ':' . $this->view->current_display);

      // Get Thompson Sampling scores from RL module.
      $scores = $this->experimentManager->getUCB1Scores($experiment_uuid);

      if (empty($scores)) {
        // No data yet, fall back to random order.
        $this->query->addOrderBy(NULL, 'RAND()', 'DESC', 'ai_sorting_fallback');
        return;
      }

      // Build a CASE statement to order by UCB1 scores.
      $case_statement = 'CASE ' . $this->tableAlias . '.nid ';
      foreach ($scores as $nid => $score) {
        $case_statement .= "WHEN " . (int) $nid . " THEN " . (float) $score . " ";
      }
      $case_statement .= 'ELSE 0 END';

      // Add small random noise to break ties.
      $order_formula = $case_statement . ' + (RAND() * 0.000001)';

      $this->query->addOrderBy(
        NULL,
        $order_formula,
        'DESC',
        'ai_sorting_score'
      );

      // Disable dynamic page cache for AI sorting.
      \Drupal::service('page_cache_kill_switch')->trigger();

    }
    catch (\Exception $e) {
      $logger = $this->loggerFactory->get('ai_sorting');
      $logger->error('Error in AI Sorting query(): @message', ['@message' => $e->getMessage()]);
      $logger->error('Stack trace: @trace', ['@trace' => $e->getTraceAsString()]);
      throw $e;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    // Remove the order selector since we always use DESC for UCB1 scores.
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

    $url = Url::fromUri('https://en.wikipedia.org/wiki/Thompson_sampling', [
      'attributes' => [
        'target' => '_blank',
        'rel' => 'noopener noreferrer',
      ],
    ]);
    $link = Link::fromTextAndUrl($this->t('Learn more about Thompson Sampling'), $url);

    // Add an advanced details element for cache settings.
    $form['ai_sorting_settings']['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced Settings'),
      '#open' => FALSE,
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
      '#description' => $this->t('This is used as the value for max-age in Cache-Control headers. Note: This setting overrides the page cache time and is specific to the AI sorting algorithm. For views sorting fewer than 10,000 nodes, a 1-minute cache lifetime is recommended. For views sorting more than 10,000 nodes, a 5-minute cache lifetime is recommended. Be aware that a longer cache time may affect Thompson Sampling randomization, which benefits from fresh data.'),
      '#required' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    parent::submitOptionsForm($form, $form_state);

    $options = &$form_state->getValue('options');

    // Save the cache_max_age value.
    if (isset($options['ai_sorting_settings']['advanced']['cache_max_age'])) {
      $this->options['cache_max_age'] = $options['ai_sorting_settings']['advanced']['cache_max_age'];
    }

    // Auto-configure views cache to match AI sorting settings.
    $cache_max_age = $this->options['cache_max_age'] ?? 60;
    $current_cache = $this->view->display_handler->getOption('cache');

    if ($cache_max_age > 0) {
      // Set time-based cache matching our AI sorting refresh rate.
      if ($current_cache['type'] !== 'time' || $current_cache['options']['output_lifespan'] != $cache_max_age) {
        $this->view->display_handler->setOption('cache', [
          'type' => 'time',
          'options' => [
            'output_lifespan' => $cache_max_age,
            'results_lifespan' => $cache_max_age,
          ],
        ]);

        \Drupal::messenger()->addStatus($this->t('Views cache has been automatically set to @seconds seconds to match your AI sorting refresh rate.', ['@seconds' => $cache_max_age]));
      }
    }
    else {
      // Disable cache when AI sorting cache is set to 0.
      if ($current_cache['type'] !== 'none') {
        $this->view->display_handler->setOption('cache', ['type' => 'none']);

        \Drupal::messenger()->addWarning($this->t('Views cache has been automatically disabled because AI sorting cache is set to "Never cache".'));
      }
    }

    // Clear any caches if necessary.
    \Drupal::service('plugin.manager.views.sort')->clearCachedDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    $summary = [];

    $cache_max_age = $this->options['cache_max_age'];
    if ($cache_max_age == 0) {
      $summary[] = $this->t('Cache: Never cache');
    }
    elseif ($cache_max_age < 60) {
      $summary[] = $this->t('Cache: @seconds seconds', ['@seconds' => $cache_max_age]);
    }
    elseif ($cache_max_age < 3600) {
      $minutes = $cache_max_age / 60;
      $summary[] = $this->t('Cache: @minutes minute(s)', ['@minutes' => $minutes]);
    }
    else {
      $hours = $cache_max_age / 3600;
      $summary[] = $this->t('Cache: @hours hour(s)', ['@hours' => $hours]);
    }

    return implode(', ', $summary);
  }

}
