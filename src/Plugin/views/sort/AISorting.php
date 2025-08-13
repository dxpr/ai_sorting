<?php

namespace Drupal\ai_sorting\Plugin\views\sort;

use Drupal\views\Plugin\views\sort\SortPluginBase;
use Drupal\Core\Form\FormStateInterface;
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
    $options['order'] = ['default' => ''];
    $options['cache_max_age'] = ['default' => 60];
    $options['favor_recent'] = ['default' => FALSE];
    // 3 months default
    $options['time_window_seconds'] = ['default' => 7776000];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    try {
      $this->ensureMyTable();

      // Generate deterministic experiment ID from view and display.
      $experiment_id = 'ai_sorting-' . $this->view->id() . '-' . $this->view->current_display;
      // Sanitize to ensure database-safe characters.
      $experiment_id = preg_replace('/[^a-zA-Z0-9_-]/', '_', $experiment_id);

      $time_window_seconds = $this->options['favor_recent'] ? $this->options['time_window_seconds'] : NULL;

      // Get the base field from the view configuration.
      $base_field = $this->view->storage->get('base_field');

      // If no base field is defined, we can't sort.
      if (empty($base_field)) {
        throw new \RuntimeException('AI Sorting requires a base_field to be defined in the view.');
      }

      // Get all possible arm IDs that will be in the result set.
      // We need to execute a query to get these IDs.
      $arm_ids = [];

      // Clone the current query to get IDs without affecting the main query.
      // The view's query at this point has all filters/conditions applied.
      $id_query = clone $this->query;

      // We only need the base field (ID field).
      // Clear fields and add only the base field.
      $id_query->clearFields();
      $id_alias = $id_query->addField($this->tableAlias, $base_field);

      // Remove any existing grouping and ordering.
      $id_query->groupby = [];
      $id_query->orderby = [];
      $id_query->addGroupBy($id_alias);

      // Build and execute the query to get all IDs.
      // The query() method returns a SelectQuery object.
      $query_obj = $id_query->query();

      // Remove limit and offset from the query object.
      $query_obj->range();

      $result = $query_obj->execute();

      foreach ($result as $row) {
        if (isset($row->$base_field)) {
          $arm_ids[] = (string) $row->$base_field;
        }
      }

      // Pass all arm IDs to the RL module to get scores.
      // The RL module will handle new arms by initializing them.
      $scores = $this->experimentManager->getThompsonScores(
        $experiment_id,
        $time_window_seconds,
        $arm_ids
      );

      // Fail hard if RL module doesn't return scores - no silent fallbacks!
      if (empty($scores)) {
        throw new \RuntimeException(sprintf(
          'AI Sorting FAILED: No scores returned for experiment "%s". RL module must always return scores for requested arms. Check RL module configuration and database connectivity.',
          $experiment_id
        ));
      }

      // Build the CASE statement for sorting.
      $case_statement = 'CASE ' . $this->tableAlias . '.' . $base_field . ' ';

      foreach ($scores as $arm_id => $score) {
        if (is_numeric($arm_id)) {
          $case_statement .= "WHEN " . (int) $arm_id . " THEN " . (float) $score . " ";
        }
        else {
          $escaped_id = addslashes($arm_id);
          $case_statement .= "WHEN '" . $escaped_id . "' THEN " . (float) $score . " ";
        }
      }

      // This should never be reached since we passed all IDs to RL module.
      $case_statement .= 'ELSE 0 END';

      $this->query->addOrderBy(
        NULL,
        $case_statement,
        'DESC',
        'ai_sorting_score'
      );

      $this->setConditionalPageCache();

    }
    catch (\Exception $e) {
      $logger = $this->loggerFactory->get('ai_sorting');
      $logger->error('AI Sorting: @message', ['@message' => $e->getMessage()]);
      throw $e;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

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
        • The algorithm balances showing popular content with exploring new options.'),
    ];

    $form['ai_sorting_settings']['favor_recent'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Favor recent content'),
      '#default_value' => $this->options['favor_recent'] ?? FALSE,
      '#description' => $this->t('Enable this for content that becomes outdated (news, campaigns, seasonal products).'),
    ];

    $form['ai_sorting_settings']['time_window_seconds'] = [
      '#type' => 'select',
      '#title' => $this->t('Count interactions from'),
      '#default_value' => $this->options['time_window_seconds'] ?? 7776000,
      '#options' => [
    // 30 * 24 * 60 * 60
        2592000 => $this->t('Last month'),
    // 90 * 24 * 60 * 60
        7776000 => $this->t('Last 3 months'),
    // 180 * 24 * 60 * 60
        15552000 => $this->t('Last 6 months'),
    // 365 * 24 * 60 * 60
        31536000 => $this->t('Last year'),
      ],
      '#states' => [
        'visible' => [
          ':input[name="options[ai_sorting_settings][favor_recent]"]' => ['checked' => TRUE],
        ],
      ],
      '#description' => $this->t('Only recently active content influences recommendations.'),
    ];

    $form['ai_sorting_settings']['help'] = [
      '#type' => 'details',
      '#title' => $this->t('Which timeframe should I choose?'),
      '#open' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="options[ai_sorting_settings][favor_recent]"]' => ['checked' => TRUE],
        ],
      ],
      '#description' => $this->t('
        <strong>News & announcements:</strong> Last month<br>
        <strong>Blog posts:</strong> Last 3 months<br>
        <strong>Products:</strong> Last 6 months<br>
        <strong>Leave unchecked for:</strong> Documentation, tutorials, evergreen content
      '),
    ];

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

    if (isset($options['ai_sorting_settings']['favor_recent'])) {
      $this->options['favor_recent'] = $options['ai_sorting_settings']['favor_recent'];
    }

    if (isset($options['ai_sorting_settings']['time_window_seconds'])) {
      $this->options['time_window_seconds'] = $options['ai_sorting_settings']['time_window_seconds'];
    }

    if (isset($options['ai_sorting_settings']['advanced']['cache_max_age'])) {
      $this->options['cache_max_age'] = $options['ai_sorting_settings']['advanced']['cache_max_age'];
    }

    $cache_max_age = $this->options['cache_max_age'] ?? 60;
    $current_cache = $this->view->display_handler->getOption('cache');

    if ($cache_max_age > 0) {
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
      if ($current_cache['type'] !== 'none') {
        $this->view->display_handler->setOption('cache', ['type' => 'none']);

        \Drupal::messenger()->addWarning($this->t('Views cache has been automatically disabled because AI sorting cache is set to "Never cache".'));
      }
    }

    \Drupal::service('plugin.manager.views.sort')->clearCachedDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    $summary = [];

    // Time window summary.
    if (!empty($this->options['favor_recent'])) {
      $time_window_seconds = $this->options['time_window_seconds'];
      if ($time_window_seconds == 2592000) {
        $summary[] = $this->t('Time window: Last month');
      }
      elseif ($time_window_seconds == 7776000) {
        $summary[] = $this->t('Time window: Last 3 months');
      }
      elseif ($time_window_seconds == 15552000) {
        $summary[] = $this->t('Time window: Last 6 months');
      }
      elseif ($time_window_seconds == 31536000) {
        $summary[] = $this->t('Time window: Last year');
      }
      else {
        $days = round($time_window_seconds / 86400);
        $summary[] = $this->t('Time window: Last @days days', ['@days' => $days]);
      }
    }

    // Cache summary.
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

  /**
   * Sets conditional page cache based on AI Sorting and site-wide settings.
   */
  protected function setConditionalPageCache() {
    // Get AI Sorting cache setting from the view configuration directly.
    $view_config = $this->view->storage->get('display');
    $display_id = $this->view->current_display;

    // Check current display first, then fall back to default display.
    $ai_sorting_cache = NULL;
    if (isset($view_config[$display_id]['display_options']['sorts']['ai_sorting']['cache_max_age'])) {
      $ai_sorting_cache = (int) $view_config[$display_id]['display_options']['sorts']['ai_sorting']['cache_max_age'];
    }
    elseif (isset($view_config['default']['display_options']['sorts']['ai_sorting']['cache_max_age'])) {
      $ai_sorting_cache = (int) $view_config['default']['display_options']['sorts']['ai_sorting']['cache_max_age'];
    }

    // Fallback to options or default.
    if ($ai_sorting_cache === NULL) {
      $ai_sorting_cache = $this->options['cache_max_age'] ?? 60;
    }

    // Get site-wide page cache configuration.
    $site_config = \Drupal::config('system.performance');
    $site_page_cache = $site_config->get('cache.page.max_age');

    // Debug logging.
    $logger = $this->loggerFactory->get('ai_sorting');
    $logger->info('AI Sorting Cache Debug: AI cache=@ai, Site cache=@site, Display=@display', [
      '@ai' => $ai_sorting_cache,
      '@site' => $site_page_cache,
      '@display' => $display_id,
    ]);

    // If site cache is disabled (0) or AI Sorting cache is longer/equal,
    // leave page cache unchanged.
    if ($site_page_cache == 0 || $ai_sorting_cache >= $site_page_cache) {
      $logger->info('AI Sorting: Not overriding cache (site=@site, ai=@ai)', [
        '@site' => $site_page_cache,
        '@ai' => $ai_sorting_cache,
      ]);
      return;
    }

    $logger->info('AI Sorting: Overriding cache from @site to @ai seconds', [
      '@site' => $site_page_cache,
      '@ai' => $ai_sorting_cache,
    ]);

    // AI Sorting cache is shorter than site cache - store for subscriber.
    // Store the desired cache time in a static variable for subscriber.
    $cache_override = &drupal_static('ai_sorting_cache_override');
    $cache_override = $ai_sorting_cache;
  }

}
