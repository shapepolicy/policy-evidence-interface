<?php

declare(strict_types=1);

namespace Drupal\policy_evidence_interface\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\policy_evidence_interface\Plugin\McpToolPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
/**
 * Configure Policy evidence interface settings for this site.
 */
final class PolicyEvidenceInterfaceSettingsForm extends ConfigFormBase {
  public function __construct(
    private readonly McpToolPluginManager $toolManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.policy_evidence_interface.tool')
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

    // Retrieve the nested array from configuration
    $config_tags = $this->config('policy_evidence_interface.settings')->get('access_control_tags');
    $disallow_tag_ids = isset($config_tags['disallow_tag_ids']) ? $config_tags['disallow_tag_ids'] : [];

    $form['disallow_tag_ids'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Disallowed Taxonomy Tag IDs'),
      '#description' => $this->t('Enter Taxonomy IDs separated by commas (e.g., 1, 2, 3).'),
      // Convert the array into a comma-separated string for the textfield
      '#default_value' => is_array($disallow_tag_ids) ? implode(', ', $disallow_tag_ids) : '',
    ];

    $config = $this->config('policy_evidence_interface.settings');
    // Rate Limiting Details Fieldset.
    $form['rate_limit'] = [
      '#type' => 'details',
      '#title' => $this->t('MCP Rate Limiting Settings'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];

    $form['rate_limit']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable rate limiting'),
      '#default_value' => $config->get('rate_limit.enabled') ?? TRUE,
    ];

    $form['rate_limit']['global'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Global Limits'),
      '#states' => [
        'visible' => [
          ':input[name="rate_limit[enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $globalLimit = $config->get('rate_limit.global.limit') ?? 5;

    $form['rate_limit']['global']['limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Global limit'),
      '#default_value' => $globalLimit,
      '#min' => 0,
      '#required' => TRUE,
    ];

    $form['rate_limit']['global']['window_seconds'] = [
      '#type' => 'number',
      '#title' => $this->t('Window duration (seconds)'),
      '#default_value' => $config->get('rate_limit.global.window_seconds') ?? 60,
      '#min' => 0,
      '#required' => TRUE,
    ];

    // Tool-specific settings populated from Plugin Manager definitions.
    $form['rate_limit']['tools'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Tool-Specific Limits'),
      '#description' => $this->t('Override rate limits for discovered plugin tools.'),
      '#states' => [
        'visible' => [
          ':input[name="rate_limit[enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    foreach ($this->toolManager->getDefinitions() as $id => $definition) {
      /** @var \Drupal\policy_evidence_interface\Plugin\McpToolInterface $plugin */
      $plugin = $this->toolManager->createInstance($id);
      $toolDef = $plugin->getToolDefinition();

      $label = $toolDef['label'] ?? $definition['label'] ?? $id;
      // if it can't find the limit then it will set to 0 so that the tool is effectivly
      // disabled until you go in and do the doing in the form
      $defaultLimit = 0; 

      $form['rate_limit']['tools'][$id]['limit'] = [
        '#type' => 'number',
        '#title' => $this->t('@label limit', ['@label' => $label]),
        '#default_value' => $config->get("rate_limit.tools.{$id}.limit") ?? $defaultLimit,
        '#min' => 0,
        '#required' => TRUE,
      ];
    }


    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {

    $input = $form_state->getValue('disallow_tag_ids');
    if (!empty($input)) {
      // Strip spaces and split by commas
      $values = array_filter(explode(',', str_replace(' ', '', $input)));
      
      foreach ($values as $value) {
        if (!is_numeric($value)) {
          $form_state->setErrorByName('disallow_tag_ids', $this->t('The field must contain numeric Tag IDs only.'));
          break;
        }
      }
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $input = $form_state->getValue('disallow_tag_ids');
  
    $tag_ids_array = [];
    if (!empty($input)) {
      // Clean spaces, split by comma, remove empties, and force to integers
      $tag_ids_array = array_map('intval', array_filter(explode(',', str_replace(' ', '', $input))));
      $tag_ids_array = array_values(array_unique($tag_ids_array)); // Remove duplicates and reset keys
    }
    // Load the current config object
    $config = $this->configFactory()->getEditable('policy_evidence_interface.settings');
    // Get the existing parent array so we don't overwrite other keys under access_control_tags
    $access_control_tags = $config->get('access_control_tags') ?: [];
    // Update just the nested array
    $access_control_tags['disallow_tag_ids'] = $tag_ids_array;

    $this->config('policy_evidence_interface.settings')
      ->set('rate_limit', $form_state->getValue('rate_limit'))
      ->set('access_control_tags', $access_control_tags)
      ->set('pdf_root_path', $form_state->getValue('pdf_root_path'))
      ->set('pdf_file', $form_state->getValue('pdf_file'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
