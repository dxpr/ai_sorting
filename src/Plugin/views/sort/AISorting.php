<?php

namespace Drupal\ai_sorting\Plugin\views\sort;

use Drupal\views\Plugin\views\sort\SortPluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\rl\Service\ExperimentManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ExperimentManagerInterface $experiment_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->experimentManager = $experiment_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('rl.experiment_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['alpha'] = ['default' => 2.0];
    $options['order'] = ['default' => '']; // We don't use this, but prevents warnings.
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
    ];

    $url = Url::fromUri('https://medium.com/analytics-vidhya/multi-armed-bandit-analysis-of-upper-confidence-bound-algorithm-4b84be516047', [
      'attributes' => [
        'target' => '_blank',
        'rel' => 'noopener noreferrer',
      ],
    ]);
    $link = Link::fromTextAndUrl($this->t('Learn more about the UCB1 algorithm'), $url);

    $form['ai_sorting_settings']['alpha'] = [
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
  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    parent::submitOptionsForm($form, $form_state);

    $options = &$form_state->getValue('options');

    // Save the alpha value
    if (isset($options['ai_sorting_settings']['alpha'])) {
      $this->options['alpha'] = $options['ai_sorting_settings']['alpha'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    $summary = [];
    $summary[] = $this->t('Alpha: @alpha', ['@alpha' => $this->options['alpha']]);
    return implode(', ', $summary);
  }

}