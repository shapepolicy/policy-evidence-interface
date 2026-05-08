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
      ->save();
    parent::submitForm($form, $form_state);
  }

}
