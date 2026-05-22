<?php

declare(strict_types=1);

namespace Drupal\policy_evidence_interface\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Policy evidence interface settings for this site.
 */
final class PolicyEvidenceInterfaceSettingsForm extends ConfigFormBase {
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
    );
  }

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
    $config = $this->config('policy_evidence_interface.settings');
    $content_types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    $options = [];
    foreach ($content_types as $machine_name => $content_type) {
      $options[$machine_name] = $content_type->label();
    }

    $form['pdf_root_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('root path to pdfs'),
      '#default_value' => $config->get('pdf_root_path'),
      '#required' => TRUE,
      '#description' => $this->t('Relative path under Drupal root, must stay within sites/default/files.'),
    ];

    $form['pdf_file'] = [
      '#type' => 'textfield',
      '#title' => $this->t('target pdf name'),
      '#default_value' => $config->get('pdf_file'),
      '#required' => TRUE,
      '#description' => $this->t('Filename only (no directory separators).'),
    ];

    $form['max_results'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum results'),
      '#default_value' => $config->get('max_results') ?? 10,
      '#min' => 1,
      '#max' => 50,
      '#required' => TRUE,
    ];
  
    $form['enabled_content_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Enabled content types'),
      '#options' => $options,
      '#default_value' => $config->get('enabled_content_types') ?? [],
    ];
  
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $pdf_file = (string) $form_state->getValue('pdf_file');
    if (str_contains($pdf_file, '/') || str_contains($pdf_file, '\\')) {
      $form_state->setErrorByName('pdf_file', $this->t('PDF filename must not include directory separators.'));
    }

    $pdf_root_path = (string) $form_state->getValue('pdf_root_path');
    $full_root_path = realpath(DRUPAL_ROOT . '/' . ltrim($pdf_root_path, '/'));
    $public_files_dir = realpath(DRUPAL_ROOT . '/sites/default/files');
    if ($full_root_path === FALSE || $public_files_dir === FALSE || !str_starts_with($full_root_path, $public_files_dir)) {
      $form_state->setErrorByName('pdf_root_path', $this->t('PDF root path must exist and be inside sites/default/files.'));
    }
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
