<?php

namespace Drupal\policy_evidence_interface\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Admin form for Policy Evidence Interface settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['policy_evidence_interface.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'policy_evidence_interface_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('policy_evidence_interface.settings');

    $form['max_results'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum results per query'),
      '#description' => $this->t('Maximum number of policy results to return per search query.'),
      '#default_value' => $config->get('max_results') ?? 20,
      '#min' => 1,
      '#max' => 100,
      '#required' => TRUE,
    ];

    $form['enabled_content_types'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Enabled content types'),
      '#description' => $this->t('Drupal content type machine names to expose via MCP (one per line). Leave blank to expose all.'),
      '#default_value' => implode("\n", $config->get('enabled_content_types') ?? []),
      '#rows' => 5,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $enabled_types = array_filter(
      explode("\n", $form_state->getValue('enabled_content_types'))
    );
    $enabled_types = array_map('trim', $enabled_types);

    $this->config('policy_evidence_interface.settings')
      ->set('max_results', $form_state->getValue('max_results'))
      ->set('enabled_content_types', $enabled_types)
      ->save();

    parent::submitForm($form, $form_state);
  }

}
