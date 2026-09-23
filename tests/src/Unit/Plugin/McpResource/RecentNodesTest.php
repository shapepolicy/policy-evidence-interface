<?php

declare(strict_types=1);

namespace Drupal\Tests\policy_evidence_interface\Unit\Plugin\McpResource;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Url;
use Drupal\policy_evidence_interface\Plugin\McpResource\RecentNodes;
use PHPUnit\Framework\TestCase;

/**
 * Tests the RecentNodes MCP resource.
 */
final class RecentNodesTest extends TestCase {

  /**
   * Tests the query and mapped node data.
   */
  public function testReadReturnsRecentNodes(): void {
    $url = $this->createMock(Url::class);
    $url->method('toString')->willReturn('https://example.test/node/42');

    $node = $this->createMock(RecentNodeTestInterface::class);
    $node->method('id')->willReturn('42');
    $node->method('label')->willReturn('Policy evidence');
    $node->method('bundle')->willReturn('article');
    $node->method('getCreatedTime')->willReturn(1700000000);
    $node->method('getChangedTime')->willReturn(1700003600);
    $node->expects($this->once())->method('toUrl')
      ->with('canonical', ['absolute' => TRUE])
      ->willReturn($url);

    $result = $this->readResource([42], [42 => $node]);

    $this->assertSame('drupal://recent-nodes', $result['uri']);
    $this->assertSame('application/json', $result['mimeType']);
    $this->assertSame([
      'nodes' => [
        [
          'nid' => 42,
          'title' => 'Policy evidence',
          'type' => 'article',
          'url' => 'https://example.test/node/42',
          'created' => date('c', 1700000000),
          'changed' => date('c', 1700003600),
        ],
      ],
      'count' => 1,
    ], json_decode($result['text'], TRUE, 512, JSON_THROW_ON_ERROR));
  }

  /**
   * Tests the response when there are no recent nodes.
   */
  public function testReadReturnsEmptyList(): void {
    $this->assertSame([
      'nodes' => [],
      'count' => 0,
    ], json_decode($this->readResource([], [])['text'], TRUE, 512, JSON_THROW_ON_ERROR));
  }

  /**
   * Reads the resource with mocked Drupal services.
   */
  private function readResource(array $nids, array $nodes): array {
    $query = $this->createMock(QueryInterface::class);
    $query->expects($this->once())->method('accessCheck')
      ->with(TRUE)->willReturnSelf();
    $query->expects($this->once())->method('condition')
      ->with('status', 1)->willReturnSelf();
    $query->expects($this->once())->method('sort')
      ->with('changed', 'DESC')->willReturnSelf();
    $query->expects($this->once())->method('range')
      ->with(0, 20)->willReturnSelf();
    $query->expects($this->once())->method('execute')
      ->willReturn($nids);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $storage->expects($this->once())->method('loadMultiple')
      ->with($nids)->willReturn($nodes);

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')
      ->with('node')->willReturn($storage);

    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $entity_type_manager);
    $original_container = \Drupal::hasContainer() ? \Drupal::getContainer() : NULL;
    \Drupal::setContainer($container);

    try {
      $resource = new RecentNodes([], 'recent_nodes', []);
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

/**
 * Adds node timestamp methods to the core entity interface for this test.
 */
interface RecentNodeTestInterface extends EntityInterface {

  /**
   * Returns the creation timestamp.
   */
  public function getCreatedTime();

  /**
   * Returns the last-changed timestamp.
   */
  public function getChangedTime();

}
