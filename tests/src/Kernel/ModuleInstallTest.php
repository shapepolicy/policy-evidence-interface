<?php

declare(strict_types=1);

namespace Drupal\Tests\policy_evidence_interface\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests what the module sets up when it is installed.
 *
 * @group policy_evidence_interface
 */
#[Group('policy_evidence_interface')]
#[RunTestsInSeparateProcesses]
final class ModuleInstallTest extends KernelTestBase {

  /**
   * Drupal finds no schema for the module's config; see the tests README.
   *
   * @var string[]
   */
  protected static $configSchemaCheckerExclusions = ['policy_evidence_interface.settings'];

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'policy_evidence_interface'];

  /**
   * Tests the default settings after install: every tool starts disabled.
   */
  public function testDefaultSettings(): void {
    $this->installConfig(['policy_evidence_interface']);

    $settings = $this->config('policy_evidence_interface.settings');

    $this->assertTrue($settings->get('rate_limit.enabled'));
    $this->assertSame(0, $settings->get('rate_limit.global.limit'));
    $this->assertSame(60, $settings->get('rate_limit.global.window_seconds'));
    $this->assertSame([], $settings->get('access_control_tags.disallow_tag_ids'));
  }

  /**
   * Tests that the five tools and three resources are discovered.
   */
  public function testPluginDiscovery(): void {
    $tools = array_keys(\Drupal::service('plugin.manager.policy_evidence_interface.tool')->getDefinitions());
    $resources = array_keys(\Drupal::service('plugin.manager.policy_evidence_interface.resource')->getDefinitions());
    sort($tools);
    sort($resources);

    $this->assertSame(PolicyEvidenceKernelTestBase::CORE_TOOLS, $tools);
    $this->assertSame(['content_types', 'recent_nodes', 'site_info'], $resources);
  }

  /**
   * Tests that enabling the sub-module adds its tool.
   */
  public function testSubModuleAddsTool(): void {
    $this->enableModules(['policy_evidence_interface_basic_tools']);

    $tools = \Drupal::service('plugin.manager.policy_evidence_interface.tool')->getDefinitions();

    $this->assertArrayHasKey('list_content_types_sub', $tools);
  }

  /**
   * Tests the path, methods and permission of the MCP endpoint route.
   */
  public function testEndpointRoute(): void {
    $route = \Drupal::service('router.route_provider')->getRouteByName('policy_evidence_interface.endpoint');

    $this->assertSame('/_mcp', $route->getPath());
    $this->assertEqualsCanonicalizing(['GET', 'POST', 'OPTIONS'], $route->getMethods());
    $this->assertSame('access mcp server', $route->getRequirement('_permission'));
    $this->assertSame(['oauth2'], $route->getOption('_auth'));
  }

}
