<?php

namespace Drupal\ai_sorting\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a configuration form for AI Sorting settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['ai_sorting.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ai_sorting_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('ai_sorting.settings');
    $form['alpha'] = [
      '#type' => 'number',
      '#title' => $this->t('Alpha Value'),
      '#description' => $this->t('Controls exploration in the UCB2 algorithm. Smaller values (e.g., 0.01) favor exploitation, larger values favor exploration.'),
      '#default_value' => $config->get('alpha') ?? 0.01,
      '#step' => 0.01,
      '#min' => 0.001,
      '#max' => 1,
      '#required' => TRUE,
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('ai_sorting.settings')
      ->set('alpha', $form_state->getValue('alpha'))
      ->save();
    parent::submitForm($form, $form_state);
  }
}