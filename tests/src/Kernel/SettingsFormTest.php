<?php

declare(strict_types=1);

namespace Drupal\Tests\policy_evidence_interface\Kernel;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\policy_evidence_interface\Form\PolicyEvidenceInterfaceSettingsForm;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the settings form by building and submitting it in code.
 *
 * @group policy_evidence_interface
 */
#[Group('policy_evidence_interface')]
#[RunTestsInSeparateProcesses]
final class SettingsFormTest extends PolicyEvidenceKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['policy_evidence_interface_basic_tools'];

  /**
   * All six tools, including the sub-module's example tool.
   */
  private const TOOLS = [...self::CORE_TOOLS, 'list_content_types_sub'];

  /**
   * Submits the form with valid values, with $values merged over them.
   */
  private function submit(array $values = []): FormStateInterface {
    $defaults = [
      'disallow_tag_ids' => '',
      'rate_limit' => [
        'enabled' => 1,
        'global' => ['limit' => 20, 'window_seconds' => 60],
        'tools' => array_fill_keys(self::TOOLS, ['limit' => 5]),
      ],
    ];
    $form_state = (new FormState())->setValues(NestedArray::mergeDeep($defaults, $values));
    \Drupal::formBuilder()->submitForm(PolicyEvidenceInterfaceSettingsForm::class, $form_state);
    return $form_state;
  }

  /**
   * Returns one saved module setting.
   */
  private function settings(string $key): mixed {
    return $this->config('policy_evidence_interface.settings')->get($key);
  }

  /**
   * Tests that the form has a limit field for every discovered tool.
   */
  public function testHasLimitFieldPerTool(): void {
    $form = \Drupal::formBuilder()->getForm(PolicyEvidenceInterfaceSettingsForm::class);

    $fields = Element::children($form['rate_limit']['tools']);

    $this->assertEqualsCanonicalizing(self::TOOLS, $fields);
  }

  /**
   * Tests that saved tag IDs are shown as a comma-separated list.
   */
  public function testShowsSavedTagIds(): void {
    $this->config('policy_evidence_interface.settings')
      ->set('access_control_tags.disallow_tag_ids', [4, 9])
      ->save();

    $form = \Drupal::formBuilder()->getForm(PolicyEvidenceInterfaceSettingsForm::class);

    $this->assertSame('4, 9', $form['disallow_tag_ids']['#default_value']);
  }

  /**
   * Tests that a valid submission saves the rate limits.
   */
  public function testSavesRateLimits(): void {
    $form_state = $this->submit();

    $this->assertSame([], $form_state->getErrors());
    $this->assertEquals(20, $this->settings('rate_limit.global.limit'));
    $this->assertEquals(60, $this->settings('rate_limit.global.window_seconds'));
    $this->assertEquals(5, $this->settings('rate_limit.tools.list_content_types_sub.limit'));
  }

  /**
   * Tests that tag IDs are trimmed, turned into numbers and de-duplicated.
   */
  public function testParsesTagIds(): void {
    $this->submit(['disallow_tag_ids' => ' 3, 1,3 ,,7']);

    $this->assertEqualsCanonicalizing([1, 3, 7], $this->settings('access_control_tags.disallow_tag_ids'));
  }

  /**
   * Tests that an empty tag field clears the list.
   */
  public function testEmptyTagFieldClearsList(): void {
    $this->submit(['disallow_tag_ids' => '']);

    $this->assertSame([], $this->settings('access_control_tags.disallow_tag_ids'));
  }

  /**
   * Tests that a tag ID that is not a number is rejected and nothing is saved.
   */
  public function testRejectsNonNumericTagId(): void {
    $form_state = $this->submit(['disallow_tag_ids' => '1, abc']);

    $this->assertArrayHasKey('disallow_tag_ids', $form_state->getErrors());
    $this->assertSame(
      [(int) $this->restrictedTerm->id()],
      $this->settings('access_control_tags.disallow_tag_ids'),
    );
  }

  /**
   * Tests that a negative limit is rejected by the field's minimum of 0.
   */
  public function testRejectsNegativeLimit(): void {
    $form_state = $this->submit(['rate_limit' => ['global' => ['limit' => -1]]]);

    $this->assertArrayHasKey('rate_limit][global][limit', $form_state->getErrors());
  }

}
