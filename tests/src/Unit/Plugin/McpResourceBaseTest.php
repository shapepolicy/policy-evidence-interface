<?php

declare(strict_types=1);

namespace Drupal\Tests\policy_evidence_interface\Unit\Plugin;

use Drupal\policy_evidence_interface\Plugin\McpResourceBase;
use PHPUnit\Framework\TestCase;

/**
 * Tests the base MCP resource definition.
 */
final class McpResourceBaseTest extends TestCase {

  /**
   * Tests that plugin metadata is exposed in the resource definition.
   */
  public function testResourceDefinition(): void {
    $definition = [
      'uri' => 'drupal://example',
      'name' => 'Example Resource',
      'description' => 'An example MCP resource.',
      'mimeType' => 'text/plain',
    ];
    $plugin = $this->createPlugin('example_resource', $definition);

    $this->assertSame($definition, $plugin->getResourceDefinition());
  }

  /**
   * Tests fallback values when optional plugin metadata is omitted.
   */
  public function testResourceDefinitionDefaults(): void {
    $plugin = $this->createPlugin('fallback_resource', []);

    $this->assertSame([
      'uri' => '',
      'name' => 'fallback_resource',
      'description' => '',
      'mimeType' => 'application/json',
    ], $plugin->getResourceDefinition());
  }

  /**
   * Creates a concrete resource plugin for testing the abstract base class.
   */
  private function createPlugin(string $plugin_id, array $definition): McpResourceBase {
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
