<?php

declare(strict_types=1);

namespace Drupal\Tests\policy_evidence_interface\Unit\Plugin;

use Drupal\policy_evidence_interface\Plugin\McpToolBase;
use PHPUnit\Framework\TestCase;

/**
 * Tests the base MCP tool definition.
 */
final class McpToolBaseTest extends TestCase {

  /**
   * Tests that plugin metadata is exposed in the tool definition.
   */
  public function testToolDefinition(): void {
    $plugin = new class([], 'fallback_tool', [
      'tool_name' => 'example_tool',
      'description' => 'An example MCP tool.',
    ]) extends McpToolBase {

      /**
       * {@inheritdoc}
       */
      public function execute(array $arguments): mixed {
        return $arguments;
      }

    };

    $definition = $plugin->getToolDefinition();

    $this->assertSame('example_tool', $definition['name']);
    $this->assertSame('An example MCP tool.', $definition['description']);
    $this->assertSame('object', $definition['inputSchema']['type']);
    $this->assertEquals(new \stdClass(), $definition['inputSchema']['properties']);
    $this->assertSame([], $definition['inputSchema']['required']);
  }

}
