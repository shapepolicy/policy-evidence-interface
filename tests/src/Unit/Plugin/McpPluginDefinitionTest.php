<?php

declare(strict_types=1);

namespace Drupal\Tests\policy_evidence_interface\Unit\Plugin;

use Drupal\policy_evidence_interface\Plugin\McpResourceBase;
use Drupal\policy_evidence_interface\Plugin\McpTool\GetNode;
use Drupal\policy_evidence_interface\Plugin\McpTool\GetSiteInfo;
use Drupal\policy_evidence_interface\Plugin\McpTool\ListContentTypes;
use Drupal\policy_evidence_interface\Plugin\McpTool\ReadNodePdf;
use Drupal\policy_evidence_interface\Plugin\McpTool\SearchNodes;
use Drupal\policy_evidence_interface\Plugin\McpToolBase;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the definitions that tool and resource plugins send to MCP clients.
 *
 * These are what a client receives from tools/list and resources/list.
 *
 * @group policy_evidence_interface
 */
#[Group('policy_evidence_interface')]
final class McpPluginDefinitionTest extends UnitTestCase {

  /**
   * Tests that a tool uses tool_name and description from its annotation.
   */
  public function testToolDefinitionUsesAnnotationValues(): void {
    $tool = new GetSiteInfo([], 'get_site_info', [
      'tool_name' => 'site_info_tool',
      'description' => 'Site details.',
    ]);

    $definition = $tool->getToolDefinition();

    $this->assertSame('site_info_tool', $definition['name']);
    $this->assertSame('Site details.', $definition['description']);
  }

  /**
   * Tests the fallbacks when the annotation has no tool_name or description.
   */
  public function testToolDefinitionFallsBackToPluginId(): void {
    $tool = new GetSiteInfo([], 'get_site_info', []);

    $definition = $tool->getToolDefinition();

    $this->assertSame('get_site_info', $definition['name']);
    $this->assertSame('', $definition['description']);
  }

  /**
   * Tests that a tool without parameters sends "properties" as a JSON object.
   *
   * MCP clients expect "properties": {} here, not an empty list [].
   */
  public function testDefaultInputSchemaEncodesAsEmptyObject(): void {
    $tool = new class([], 'no_params', []) extends McpToolBase {

      /**
       * {@inheritdoc}
       */
      public function execute(array $arguments): mixed {
        return [];
      }

    };

    $schema = $tool->getToolDefinition()['inputSchema'];

    $this->assertSame('{}', json_encode($schema['properties']));
  }

  /**
   * Tests the parameters each tool declares in its input schema.
   */
  public function testToolInputSchemas(): void {
    $expected = [
      GetNode::class => [['nid'], ['nid']],
      ReadNodePdf::class => [['nid', 'page_start'], ['nid']],
      SearchNodes::class => [['keyword', 'content_type', 'limit'], ['keyword']],
      GetSiteInfo::class => [[], []],
      ListContentTypes::class => [[], []],
    ];

    foreach ($expected as $class => [$properties, $required]) {
      $schema = (new $class([], 'test', []))->getToolDefinition()['inputSchema'];

      $this->assertSame('object', $schema['type'], $class);
      $this->assertEqualsCanonicalizing($properties, array_keys((array) $schema['properties']), $class);
      $this->assertSame($required, $schema['required'], $class);
    }
  }

  /**
   * Tests that a resource uses the uri, name, description and mimeType given.
   */
  public function testResourceDefinitionUsesAnnotationValues(): void {
    $definition = [
      'uri' => 'drupal://example',
      'name' => 'Example',
      'description' => 'An example resource.',
      'mimeType' => 'text/plain',
    ];

    $resource = $this->createResource('example', $definition);

    $this->assertSame($definition, $resource->getResourceDefinition());
  }

  /**
   * Tests the resource fallbacks when the annotation leaves values out.
   */
  public function testResourceDefinitionFallbacks(): void {
    $resource = $this->createResource('example', []);

    $this->assertSame([
      'uri' => '',
      'name' => 'example',
      'description' => '',
      'mimeType' => 'application/json',
    ], $resource->getResourceDefinition());
  }

  /**
   * Creates a minimal resource plugin with the given definition.
   */
  private function createResource(string $plugin_id, array $definition): McpResourceBase {
    return new class([], $plugin_id, $definition) extends McpResourceBase {

      /**
       * {@inheritdoc}
       */
      public function read(): array {
        return [];
      }

    };
  }

}
