<?php

declare(strict_types=1);

namespace Drupal\Tests\policy_evidence_interface\Unit\Plugin\McpTool;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\policy_evidence_interface\Plugin\McpTool\SearchNodes;
use PHPUnit\Framework\TestCase;

/**
 * Tests the SearchNodes input contract.
 */
final class SearchNodesTest extends TestCase {

  /**
   * Tests the search input schema.
   */
  public function testInputSchema(): void {
    $tool = new SearchNodes([], 'search_nodes', []);
    $schema = $tool->getToolDefinition()['inputSchema'];
    $properties = $schema['properties'];

    $this->assertSame('object', $schema['type']);
    $this->assertSame('string', $properties['keyword']['type']);
    $this->assertSame(1, $properties['keyword']['minLength']);
    $this->assertSame('string', $properties['content_type']['type']);
    $this->assertSame('integer', $properties['limit']['type']);
    $this->assertSame(1, $properties['limit']['minimum']);
    $this->assertSame(50, $properties['limit']['maximum']);
    $this->assertSame(10, $properties['limit']['default']);
    $this->assertSame(['keyword'], $schema['required']);
  }

  /**
   * Tests that invalid keywords are rejected before accessing Drupal services.
   */
  public function testInvalidKeyword(): void {
    $tool = new SearchNodes([], 'search_nodes', []);
    $error = ['error' => 'A valid keyword is required.'];

    $this->assertSame($error, $tool->execute([]));

    foreach (['', NULL, 1, 1.5, TRUE, [1]] as $keyword) {
      $this->assertSame($error, $tool->execute(['keyword' => $keyword]));
    }
  }

  /**
   * Tests invalid content types are rejected before Drupal service access.
   */
  public function testInvalidContentType(): void {
    $tool = new SearchNodes([], 'search_nodes', []);
    $error = ['error' => 'content_type must be a string.'];

    foreach ([NULL, 1, 1.5, TRUE, [1]] as $content_type) {
      $arguments = ['keyword' => 'policy', 'content_type' => $content_type];
      $this->assertSame($error, $tool->execute($arguments));
    }
  }

  /**
   * Tests that invalid limits are rejected before accessing Drupal services.
   */
  public function testInvalidLimit(): void {
    $tool = new SearchNodes([], 'search_nodes', []);
    $error = ['error' => 'limit must be an integer between 1 and 50.'];

    foreach ([0, -1, 51, NULL, '10', 1.5, INF, NAN, TRUE, [1]] as $limit) {
      $arguments = ['keyword' => 'policy', 'limit' => $limit];
      $this->assertSame($error, $tool->execute($arguments));
    }
  }

  /**
   * Tests that a schema-valid integral float limit is accepted.
   */
  public function testIntegralFloatLimit(): void {
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('sort')->willReturnSelf();
    $query->method('range')->with(0, 10)->willReturnSelf();
    $query->method('execute')->willReturn([]);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $storage->method('loadMultiple')->with([])->willReturn([]);

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')->with('node')->willReturn($storage);

    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $entity_type_manager);
    \Drupal::setContainer($container);

    try {
      $tool = new SearchNodes([], 'search_nodes', []);
      $result = $tool->execute(['keyword' => 'policy', 'limit' => 10.0]);
    }
    finally {
      \Drupal::unsetContainer();
    }

    $this->assertSame(['total' => 0, 'results' => []], $result);
  }

}
