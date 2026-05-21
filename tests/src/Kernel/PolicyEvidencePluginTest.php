<?php

namespace Drupal\Tests\policy_evidence_interface\Kernel;

use Drupal\KernelTests\KernelTestBase;
use InvalidArgumentException;

/**
 * Tests MCP plugin discovery and tool execution.
 *
 * Verifies that the policy_evidence_interface plugin:
 * - Is discoverable by the MCP plugin manager
 * - Can be instantiated
 * - Exposes exactly two tools: search_policies and get_policy
 * - Returns placeholder responses (Phase 1 scaffold verification)
 * - Properly rejects unknown tool IDs
 *
 * @group policy_evidence_interface
 */
class PolicyEvidencePluginTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'mcp',
    'policy_evidence_interface',
  ];

  /**
   * The MCP plugin manager.
   *
   * @var \Drupal\mcp\Plugin\McpPluginManager
   */
  protected $mcpPluginManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['policy_evidence_interface']);
    $this->mcpPluginManager = \Drupal::service('plugin.manager.mcp');
  }

  /**
   * Test that the MCP plugin manager can discover the policy_evidence plugin.
   */
  public function testPluginDiscovery(): void {
    $definitions = $this->mcpPluginManager->getDefinitions();

    $this->assertArrayHasKey(
      'policy_evidence_interface',
      $definitions,
      'Plugin ID "policy_evidence_interface" should be discoverable by MCP plugin manager.'
    );
  }

  /**
   * Test that the plugin can be instantiated.
   */
  public function testPluginInstantiation(): void {
    $plugin = $this->mcpPluginManager->createInstance('policy_evidence_interface');

    $this->assertNotNull(
      $plugin,
      'Plugin "policy_evidence_interface" should be instantiable.'
    );

    $this->assertInstanceOf(
      'Drupal\policy_evidence_interface\Plugin\Mcp\PolicyEvidence',
      $plugin,
      'Plugin instance should be of correct class.'
    );
  }

  /**
   * Test that getTools() returns exactly two tools.
   */
  public function testGetToolsReturnsExactlyTwoTools(): void {
    $plugin = $this->mcpPluginManager->createInstance('policy_evidence_interface');
    $tools = $plugin->getTools();

    $this->assertCount(
      2,
      $tools,
      'Plugin should expose exactly two tools.'
    );
  }

  /**
   * Test that tool names are "search_policies" and "get_policy".
   */
  public function testToolNames(): void {
    $plugin = $this->mcpPluginManager->createInstance('policy_evidence_interface');
    $tools = $plugin->getTools();

    $tool_names = array_map(function ($tool) {
      return $tool['name'] ?? $tool['id'] ?? NULL;
    }, $tools);

    $this->assertContains(
      'search_policies',
      $tool_names,
      'Tools should include "search_policies".'
    );

    $this->assertContains(
      'get_policy',
      $tool_names,
      'Tools should include "get_policy".'
    );
  }

  /**
   * Test that executeTool for search_policies returns placeholder response.
   */
  public function testSearchPoliciesPlaceholderExecution(): void {
    $plugin = $this->mcpPluginManager->createInstance('policy_evidence_interface');

    $result = $plugin->executeTool('search_policies', [
      'query' => 'test',
    ]);

    $this->assertIsString(
      $result,
      'executeTool result should be a string.'
    );

    $this->assertStringContainsString(
      'not yet implemented',
      $result,
      'search_policies should return placeholder "not yet implemented" message.'
    );
  }

  /**
   * Test that executeTool for get_policy returns placeholder response.
   */
  public function testGetPolicyPlaceholderExecution(): void {
    $plugin = $this->mcpPluginManager->createInstance('policy_evidence_interface');

    $result = $plugin->executeTool('get_policy', [
      'id' => 123,
    ]);

    $this->assertIsString(
      $result,
      'executeTool result should be a string.'
    );

    $this->assertStringContainsString(
      'not yet implemented',
      $result,
      'get_policy should return placeholder "not yet implemented" message.'
    );
  }

  /**
   * Test that executeTool throws InvalidArgumentException for unknown tools.
   */
  public function testUnknownToolThrowsException(): void {
    $plugin = $this->mcpPluginManager->createInstance('policy_evidence_interface');

    $this->expectException(InvalidArgumentException::class);

    $plugin->executeTool('nonexistent_tool', []);
  }

  /**
   * Test executeTool with search_policies and explicit limit parameter.
   *
   * TODO (Phase 2): After real search logic is implemented, verify:
   * - Limit parameter is respected (e.g., limit=5 returns 5 or fewer results).
   * - Default limit is 10 if not provided.
   * - Max limit is 50 (requests > 50 should be capped or rejected).
   */
  public function testSearchPoliciesWithLimitParameter(): void {
    $plugin = $this->mcpPluginManager->createInstance('policy_evidence_interface');

    $result = $plugin->executeTool('search_policies', [
      'query' => 'test',
      'limit' => 5,
    ]);

    // Currently just checks placeholder. Future: verify limit is respected.
    $this->assertStringContainsString(
      'not yet implemented',
      $result
    );
  }

  /**
   * Test that get_policy accepts the 'id' parameter.
   *
   * TODO (Phase 2): After real retrieval logic is implemented, verify:
   * - Missing 'id' throws or returns error.
   * - Non-integer 'id' is rejected or coerced appropriately.
   * - Valid 'id' returns node data.
   */
  public function testGetPolicyWithIdParameter(): void {
    $plugin = $this->mcpPluginManager->createInstance('policy_evidence_interface');

    $result = $plugin->executeTool('get_policy', [
      'id' => 42,
    ]);

    $this->assertIsString($result);
    $this->assertStringContainsString('not yet implemented', $result);
  }

}
