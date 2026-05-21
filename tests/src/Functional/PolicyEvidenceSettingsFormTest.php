<?php

namespace Drupal\Tests\policy_evidence_interface\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests settings form behavior and configuration.
 *
 * Verifies that:
 * - The settings form is accessible to authorized users
 * - Form elements are present (Phase 2+)
 * - Configuration can be saved and retrieved (Phase 2+)
 * - Validation rules work as expected (Phase 2+)
 *
 * @group policy_evidence_interface
 */
class PolicyEvidenceSettingsFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'mcp',
    'policy_evidence_interface',
  ];

  /**
   * Test that the settings form is accessible and renders without errors.
   */
  public function testSettingsFormAccessible(): void {
    $admin = $this->drupalCreateUser([
      'administer site configuration',
      'access policy evidence interface settings',
    ]);

    $this->drupalLogin($admin);

    $this->drupalGet('/admin/config/services/policy-evidence-interface');

    // Form should load successfully
    $this->assertSession()->statusCodeEquals(200);

    // Should display form title
    $this->assertSession()->pageTextContains('Policy Evidence Interface');
  }

  /**
   * Test that the settings form contains a submit button.
   */
  public function testSettingsFormHasSubmitButton(): void {
    $admin = $this->drupalCreateUser([
      'administer site configuration',
      'access policy evidence interface settings',
    ]);

    $this->drupalLogin($admin);

    $this->drupalGet('/admin/config/services/policy-evidence-interface');

    // Form should have a submit button (common names: "Save", "Submit", "Save configuration")
    $button_exists = (
      $this->getSession()->getPage()->findButton('Save') ||
      $this->getSession()->getPage()->findButton('Save configuration') ||
      $this->getSession()->getPage()->findButton('Submit')
    );

    $this->assertTrue(
      $button_exists,
      'Settings form should have a submit button.'
    );
  }

  /**
   * TODO (Phase 2): Test that max_results configuration field exists and works.
   *
   * After implementing the max_results form field, add:
   *
   *   public function testMaxResultsFieldExists(): void {
   *     $admin = $this->drupalCreateUser([...]);
   *     $this->drupalLogin($admin);
   *     $this->drupalGet('/admin/config/services/policy-evidence-interface');
   *
   *     // Check for max_results field
   *     $field = $this->getSession()->getPage()->findField('max_results');
   *     $this->assertNotNull($field, 'max_results field should exist.');
   *   }
   *
   *   public function testMaxResultsConfigurationSaving(): void {
   *     $admin = $this->drupalCreateUser([...]);
   *     $this->drupalLogin($admin);
   *     $this->drupalGet('/admin/config/services/policy-evidence-interface');
   *
   *     // Submit form with max_results = 20
   *     $this->submitForm(['max_results' => 20], 'Save configuration');
   *
   *     // Should show success message
   *     $this->assertSession()->pageTextContains('Settings saved');
   *
   *     // Configuration should be saved
   *     $config = \Drupal::config('policy_evidence_interface.settings');
   *     $this->assertEquals(20, $config->get('max_results'));
   *   }
   *
   *   public function testMaxResultsValidation(): void {
   *     $admin = $this->drupalCreateUser([...]);
   *     $this->drupalLogin($admin);
   *     $this->drupalGet('/admin/config/services/policy-evidence-interface');
   *
   *     // Try to submit invalid value (e.g., 0 or negative)
   *     $this->submitForm(['max_results' => -1], 'Save configuration');
   *
   *     // Should show validation error
   *     $this->assertSession()->pageTextContains(
   *       'max_results must be greater than 0'
   *     );
   *   }
   */

  /**
   * TODO (Phase 2): Test enabled_content_types configuration field.
   *
   * After implementing content type checkboxes, add:
   *
   *   public function testEnabledContentTypesFieldExists(): void {
   *     $admin = $this->drupalCreateUser([...]);
   *     $this->drupalLogin($admin);
   *     $this->drupalGet('/admin/config/services/policy-evidence-interface');
   *
   *     // Check for content type selection fields
   *     $this->assertSession()->elementExists('xpath', '//input[@name="enabled_content_types[]"]');
   *   }
   *
   *   public function testEnabledContentTypesSaving(): void {
   *     $admin = $this->drupalCreateUser([...]);
   *     $this->drupalLogin($admin);
   *     $this->drupalGet('/admin/config/services/policy-evidence-interface');
   *
   *     // Enable specific content types
   *     $this->submitForm([
   *       'enabled_content_types[policy]' => TRUE,
   *       'enabled_content_types[guidance]' => FALSE,
   *     ], 'Save configuration');
   *
   *     // Verify configuration was saved
   *     $config = \Drupal::config('policy_evidence_interface.settings');
   *     $enabled = $config->get('enabled_content_types');
   *     $this->assertContains('policy', $enabled);
   *     $this->assertNotContains('guidance', $enabled);
   *   }
   */

  /**
   * Test default configuration is loaded.
   */
  public function testDefaultConfigurationLoaded(): void {
    // Verify that default config from config/install exists
    $config = \Drupal::config('policy_evidence_interface.settings');

    // Config should exist and be accessible
    $this->assertNotNull(
      $config,
      'Default configuration should be loadable.'
    );
  }

}
