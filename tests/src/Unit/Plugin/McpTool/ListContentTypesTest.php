<?php

declare(strict_types=1);

namespace Drupal\Tests\policy_evidence_interface\Unit\Plugin\McpTool;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\policy_evidence_interface\Plugin\McpTool\ListContentTypes;
use PHPUnit\Framework\TestCase;

/**
 * Tests the ListContentTypes MCP tool.
 */
final class ListContentTypesTest extends TestCase {

  /**
   * Tests the empty input schema.
   */
  public function testInputSchema(): void {
    $tool = new ListContentTypes([], 'list_content_types', []);
    $schema = $tool->getToolDefinition()['inputSchema'];

    $this->assertSame('object', $schema['type']);
    $this->assertInstanceOf(\stdClass::class, $schema['properties']);
    $this->assertSame([], (array) $schema['properties']);
    $this->assertSame([], $schema['required']);
  }

  /**
   * Tests configured content types are returned.
   */
  public function testExecuteReturnsContentTypes(): void {
    $node_type = new class() {

      /**
       * Returns the content type ID.
       */
      public function id(): string {
        return 'policy_evidence';
      }

      /**
       * Returns the content type label.
       */
      public function label(): string {
        return 'Policy evidence';
      }

      /**
       * Returns the content type description.
       */
      public function getDescription(): string {
        return 'Evidence used to support policy decisions.';
      }

    };

    $this->assertSame([
      'content_types' => [
        [
          'machine_name' => 'policy_evidence',
          'label' => 'Policy evidence',
          'description' => 'Evidence used to support policy decisions.',
        ],
      ],
    ], $this->executeTool(['policy_evidence' => $node_type]));
  }

  /**
   * Tests an empty content type list is returned.
   */
  public function testExecuteReturnsEmptyList(): void {
    $this->assertSame(['content_types' => []], $this->executeTool([]));
  }

  /**
   * Executes the tool with mocked Drupal services.
   */
  private function executeTool(array $node_types): array {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadMultiple')->willReturn($node_types);

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')
      ->with('node_type')
      ->willReturn($storage);

    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $entity_type_manager);
    \Drupal::setContainer($container);

    try {
      $tool = new ListContentTypes([], 'list_content_types', []);
      return $tool->execute([]);
    }
    finally {
      \Drupal::unsetContainer();
    }
  }

}
