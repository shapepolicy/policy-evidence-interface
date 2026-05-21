<?php

namespace Drupal\Tests\policy_evidence_interface\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests routing and settings form accessibility.
 *
 * Verifies that:
 * - The admin settings route exists at /admin/config/services/policy-evidence-interface
 * - The route returns a 200 OK response for authorized users
 * - The settings form renders without errors
 * - Unauthorized access is properly denied
 *
 * @group policy_evidence_interface
 */
class PolicyEvidenceRoutingTest extends BrowserTestBase {

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
   * Test that the settings route exists and returns 200 for authorized users.
   */
  public function testSettingsRouteExists(): void {
    // Create an admin user with necessary permissions
    $admin = $this->drupalCreateUser([
      'administer site configuration',
      'access policy evidence interface settings',
    ]);

    $this->drupalLogin($admin);

    // Navigate to the settings path
    $this->drupalGet('/admin/config/services/policy-evidence-interface');

    // Should not get a 404
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Test that the settings form renders and contains expected content.
   */
  public function testSettingsFormRenders(): void {
    $admin = $this->drupalCreateUser([
      'administer site configuration',
      'access policy evidence interface settings',
    ]);

    $this->drupalLogin($admin);

    $this->drupalGet('/admin/config/services/policy-evidence-interface');

    // Form should render without errors
    $this->assertSession()->statusCodeEquals(200);

    // Page should contain a title indicating it's the policy evidence settings
    $this->assertSession()->pageTextContains('Policy Evidence Interface');
  }

  /**
   * Test that unauthenticated users cannot access the settings page.
   */
  public function testSettingsRouteRequiresAuthentication(): void {
    // Try to access without logging in
    $this->drupalGet('/admin/config/services/policy-evidence-interface');

    // Should be denied (403 Forbidden)
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Test that users without permission cannot access the settings page.
   */
  public function testSettingsRouteRequiresPermission(): void {
    // Create a user with admin permissions but not policy_evidence permissions
    $user = $this->drupalCreateUser([
      'administer site configuration',
    ]);

    $this->drupalLogin($user);

    $this->drupalGet('/admin/config/services/policy-evidence-interface');

    // Should be denied (403 Forbidden)
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Test that the correct settings form controller is invoked.
   *
   * This is verified indirectly: if the form renders without error,
   * the controller exists and is properly mapped.
   */
  public function testSettingsFormControllerIsCorrect(): void {
    $admin = $this->drupalCreateUser([
      'administer site configuration',
      'access policy evidence interface settings',
    ]);

    $this->drupalLogin($admin);

    $this->drupalGet('/admin/config/services/policy-evidence-interface');

    // If the form renders, the route controller is correct
    $this->assertSession()->statusCodeEquals(200);

    // Settings form should be visible (not an error page)
    $this->assertSession()->elementNotExists(
      'xpath',
      '//html/body[contains(., "The website encountered an unexpected error")]'
    );
  }

  /**
   * Test that the route path is exactly as specified.
   */
  public function testSettingsRoutePathIsCorrect(): void {
    $admin = $this->drupalCreateUser([
      'administer site configuration',
      'access policy evidence interface settings',
    ]);

    $this->drupalLogin($admin);

    // Navigate using the exact path specified in requirements
    $this->drupalGet('/admin/config/services/policy-evidence-interface');

    // Should be accessible at this exact path
    $this->assertSession()->statusCodeEquals(200);

    // Current URL should match the route path
    $this->assertEquals(
      '/admin/config/services/policy-evidence-interface',
      parse_url($this->getSession()->getCurrentUrl(), PHP_URL_PATH)
    );
  }

  /**
   * TODO (Phase 2): Test settings form field rendering and submission.
   *
   * After implementing form fields, add tests for:
   * - Form field "max_results" exists and is functional
   * - Form field "enabled_content_types" exists and is functional
   * - Form submission saves configuration
   * - Validation rules work (e.g., max_results must be positive integer)
   * - Submitted values can be retrieved from config
   *
   * Example test method (for Phase 2):
   *   public function testSettingsFormFieldsAndSubmission(): void {
   *     $admin = $this->drupalCreateUser([...]);
   *     $this->drupalLogin($admin);
   *     $this->drupalGet('/admin/config/services/policy-evidence-interface');
   *
   *     // Fill in form fields
   *     $this->submitForm([
   *       'max_results' => 20,
   *       'enabled_content_types[policy]' => TRUE,
   *     ], 'Save configuration');
   *
   *     // Verify success message
   *     $this->assertSession()->pageTextContains('Settings saved');
   *
   *     // Verify configuration was saved
   *     $config = \Drupal::config('policy_evidence_interface.settings');
   *     $this->assertEquals(20, $config->get('max_results'));
   *   }
   */

}
