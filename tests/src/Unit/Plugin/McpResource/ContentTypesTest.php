<?php

declare(strict_types=1);

namespace Drupal\Tests\policy_evidence_interface\Unit\Plugin\McpResource;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityDescriptionInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\policy_evidence_interface\Plugin\McpResource\ContentTypes;
use PHPUnit\Framework\TestCase;

/**
 * Tests the ContentTypes MCP resource.
 */
final class ContentTypesTest extends TestCase {

  /**
   * Tests content type data in the resource response.
   */
  public function testReadReturnsContentTypes(): void {
    $node_type = $this->createConfiguredMock(EntityDescriptionInterface::class, [
      'id' => 'policy_evidence',
      'label' => 'Policy evidence',
      'getDescription' => 'Evidence used to support policy decisions.',
    ]);

    $result = $this->readResource(['policy_evidence' => $node_type]);

    $this->assertSame('drupal://content-types', $result['uri']);
    $this->assertSame('application/json', $result['mimeType']);
    $this->assertSame([
      'content_types' => [
        [
          'machine_name' => 'policy_evidence',
          'label' => 'Policy evidence',
          'description' => 'Evidence used to support policy decisions.',
        ],
      ],
    ], json_decode($result['text'], TRUE, 512, JSON_THROW_ON_ERROR));
  }

  /**
   * Tests the resource when no content types exist.
   */
  public function testReadReturnsEmptyList(): void {
    $result = $this->readResource([]);

    $this->assertSame(
      ['content_types' => []],
      json_decode($result['text'], TRUE, 512, JSON_THROW_ON_ERROR),
    );
  }

  /**
   * Reads the resource with mocked Drupal services.
   */
  private function readResource(array $node_types): array {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadMultiple')->willReturn($node_types);

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')
      ->with('node_type')
      ->willReturn($storage);

    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $entity_type_manager);
    $original_container = \Drupal::hasContainer() ? \Drupal::getContainer() : NULL;
    \Drupal::setContainer($container);

    try {
      $resource = new ContentTypes([], 'content_types', []);
      return $resource->read();
    }
    finally {
      if ($original_container) {
        \Drupal::setContainer($original_container);
      }
      else {
        \Drupal::unsetContainer();
      }
    }
  }

}
