<?php

declare(strict_types=1);

namespace Drupal\policy_evidence_interface\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Policy evidence interface settings for this site.
 */
final class PolicyEvidenceInterfaceSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'policy_evidence_interface_policy_evidence_interface_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['policy_evidence_interface.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    
    $form['pdf_root_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('root path to pdfs'),
      '#default_value' => $this->config('policy_evidence_interface.settings')->get('pdf_root_path'),
    ];

    $form['pdf_file'] = [
      '#type' => 'textfield',
      '#title' => $this->t('target pdf name'),
      '#default_value' => $this->config('policy_evidence_interface.settings')->get('pdf_file'),
    ];

    $form['max_results'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum results'),
      '#default_value' => $this->config('policy_evidence_interface.settings')->get('max_results') ?? 10,
      '#min' => 1,
      '#max' => 50,
    ];
  
    $form['enabled_content_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Enabled content types'),
      '#options' => [
        'article' => $this->t('Article'),
        'page' => $this->t('Basic page'),
      ],
      '#default_value' => $this->config('policy_evidence_interface.settings')->get('enabled_content_types') ?? [],
    ];
  
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // @todo Validate the form here.
    // Example:
    // @code
    //   if ($form_state->getValue('example') === 'wrong') {
    //     $form_state->setErrorByName(
    //       'message',
    //       $this->t('The value is not correct.'),
    //     );
    //   }
    // @endcode
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('policy_evidence_interface.settings')
      ->set('pdf_root_path', $form_state->getValue('pdf_root_path'))
      ->set('pdf_file', $form_state->getValue('pdf_file'))
      ->set('max_results', $form_state->getValue('max_results'))
      ->set('enabled_content_types', array_filter($form_state->getValue('enabled_content_types')))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
