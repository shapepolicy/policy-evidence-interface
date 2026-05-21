<?php

namespace Drupal\Tests\policy_evidence_interface\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests tool schema definitions.
 *
 * Verifies that the policy_evidence_interface plugin correctly defines:
 * - search_policies: required 'query', optional 'limit' (default 10, max 50)
 * - get_policy: required 'id' (integer type)
 *
 * @group policy_evidence_interface
 */
class PolicyEvidenceSchemaTest extends KernelTestBase {

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
   * Test search_policies tool schema.
   */
  public function testSearchPoliciesToolSchema(): void {
    $plugin = $this->mcpPluginManager->createInstance('policy_evidence_interface');
    $tools = $plugin->getTools();

    // Find search_policies tool
    $search_tool = NULL;
    foreach ($tools as $tool) {
      if (($tool['name'] ?? $tool['id'] ?? NULL) === 'search_policies') {
        $search_tool = $tool;
        break;
      }
    }

    $this->assertNotNull(
      $search_tool,
      'search_policies tool should exist.'
    );

    $this->assertArrayHasKey(
      'inputSchema',
      $search_tool,
      'Tool should have inputSchema key.'
    );

    $schema = $search_tool['inputSchema'];

    // Check schema structure
    $this->assertArrayHasKey(
      'properties',
      $schema,
      'inputSchema should have properties.'
    );

    $properties = $schema['properties'];

    // Check input parameters exist
    $this->assertArrayHasKey(
      'query',
      $properties,
      'search_policies should have "query" input parameter.'
    );

    $this->assertArrayHasKey(
      'limit',
      $properties,
      'search_policies should have "limit" input parameter.'
    );
  }

  /**
   * Test search_policies query parameter is required.
   */
  public function testSearchPoliciesQueryIsRequired(): void {
    $plugin = $this->mcpPluginManager->createInstance('policy_evidence_interface');
    $tools = $plugin->getTools();

    $search_tool = NULL;
    foreach ($tools as $tool) {
      if (($tool['name'] ?? $tool['id'] ?? NULL) === 'search_policies') {
        $search_tool = $tool;
        break;
      }
    }

    $schema = $search_tool['inputSchema'];
    $required = $schema['required'] ?? [];

    $this->assertContains(
      'query',
      $required,
      'query should be a required field.'
    );
  }

  /**
   * Test search_policies limit parameter is optional.
   */
  public function testSearchPoliciesLimitIsOptional(): void {
    $plugin = $this->mcpPluginManager->createInstance('policy_evidence_interface');
    $tools = $plugin->getTools();

    $search_tool = NULL;
    foreach ($tools as $tool) {
      if (($tool['name'] ?? $tool['id'] ?? NULL) === 'search_policies') {
        $search_tool = $tool;
        break;
      }
    }

    $schema = $search_tool['inputSchema'];
    $required = $schema['required'] ?? [];

    $this->assertNotContains(
      'limit',
      $required,
      'limit should be optional (not in required array).'
    );
  }

  /**
   * Test search_policies limit has default value of 10.
   */
  public function testSearchPoliciesLimitDefaultValue(): void {
    $plugin = $this->mcpPluginManager->createInstance('policy_evidence_interface');
    $tools = $plugin->getTools();

    $search_tool = NULL;
    foreach ($tools as $tool) {
      if (($tool['name'] ?? $tool['id'] ?? NULL) === 'search_policies') {
        $search_tool = $tool;
        break;
      }
    }

    $schema = $search_tool['inputSchema'];
    $limit_schema = $schema['properties']['limit'] ?? [];

    $this->assertArrayHasKey(
      'default',
      $limit_schema,
      'limit should have a default value.'
    );

    $this->assertEquals(
      10,
      $limit_schema['default'],
      'limit default should be 10.'
    );
  }

  /**
   * Test search_policies limit has maximum constraint of 50.
   */
  public function testSearchPoliciesLimitMaximumValue(): void {
    $plugin = $this->mcpPluginManager->createInstance('policy_evidence_interface');
    $tools = $plugin->getTools();

    $search_tool = NULL;
    foreach ($tools as $tool) {
      if (($tool['name'] ?? $tool['id'] ?? NULL) === 'search_policies') {
        $search_tool = $tool;
        break;
      }
    }

    $schema = $search_tool['inputSchema'];
    $limit_schema = $schema['properties']['limit'] ?? [];

    $this->assertArrayHasKey(
      'maximum',
      $limit_schema,
      'limit should have a maximum constraint.'
    );

    $this->assertEquals(
      50,
      $limit_schema['maximum'],
      'limit maximum should be 50.'
    );
  }

  /**
   * Test search_policies limit is typed as integer.
   */
  public function testSearchPoliciesLimitType(): void {
    $plugin = $this->mcpPluginManager->createInstance('policy_evidence_interface');
    $tools = $plugin->getTools();

    $search_tool = NULL;
    foreach ($tools as $tool) {
      if (($tool['name'] ?? $tool['id'] ?? NULL) === 'search_policies') {
        $search_tool = $tool;
        break;
      }
    }

    $schema = $search_tool['inputSchema'];
    $limit_schema = $schema['properties']['limit'] ?? [];

    $this->assertEquals(
      'integer',
      $limit_schema['type'] ?? NULL,
      'limit should be typed as integer.'
    );
  }

  /**
   * Test get_policy tool schema.
   */
  public function testGetPolicyToolSchema(): void {
    $plugin = $this->mcpPluginManager->createInstance('policy_evidence_interface');
    $tools = $plugin->getTools();

    // Find get_policy tool
    $get_tool = NULL;
    foreach ($tools as $tool) {
      if (($tool['name'] ?? $tool['id'] ?? NULL) === 'get_policy') {
        $get_tool = $tool;
        break;
      }
    }

    $this->assertNotNull(
      $get_tool,
      'get_policy tool should exist.'
    );

    $this->assertArrayHasKey(
      'inputSchema',
      $get_tool,
      'Tool should have inputSchema key.'
    );

    $schema = $get_tool['inputSchema'];

    // Check schema structure
    $this->assertArrayHasKey(
      'properties',
      $schema,
      'inputSchema should have properties.'
    );

    $properties = $schema['properties'];

    // Check required field
    $this->assertArrayHasKey(
      'id',
      $properties,
      'get_policy should have "id" input parameter.'
    );
  }

  /**
   * Test get_policy id parameter is required.
   */
  public function testGetPolicyIdIsRequired(): void {
    $plugin = $this->mcpPluginManager->createInstance('policy_evidence_interface');
    $tools = $plugin->getTools();

    $get_tool = NULL;
    foreach ($tools as $tool) {
      if (($tool['name'] ?? $tool['id'] ?? NULL) === 'get_policy') {
        $get_tool = $tool;
        break;
      }
    }

    $schema = $get_tool['inputSchema'];
    $required = $schema['required'] ?? [];

    $this->assertContains(
      'id',
      $required,
      'id should be a required field.'
    );
  }

  /**
   * Test get_policy id is typed as integer.
   */
  public function testGetPolicyIdType(): void {
    $plugin = $this->mcpPluginManager->createInstance('policy_evidence_interface');
    $tools = $plugin->getTools();

    $get_tool = NULL;
    foreach ($tools as $tool) {
      if (($tool['name'] ?? $tool['id'] ?? NULL) === 'get_policy') {
        $get_tool = $tool;
        break;
      }
    }

    $schema = $get_tool['inputSchema'];
    $id_schema = $schema['properties']['id'];

    $this->assertEquals(
      'integer',
      $id_schema['type'] ?? NULL,
      'id should be typed as integer.'
    );
  }

}
