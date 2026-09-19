<?php

declare(strict_types=1);

namespace Drupal\Tests\policy_evidence_interface\Unit\Plugin\McpTool;

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

    $this->assertSame('object', $schema['type']);
    $this->assertSame('string', $schema['properties']['keyword']['type']);
    $this->assertSame(1, $schema['properties']['keyword']['minLength']);
    $this->assertSame('string', $schema['properties']['content_type']['type']);
    $this->assertSame('integer', $schema['properties']['limit']['type']);
    $this->assertSame(1, $schema['properties']['limit']['minimum']);
    $this->assertSame(50, $schema['properties']['limit']['maximum']);
    $this->assertSame(10, $schema['properties']['limit']['default']);
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

    foreach ([0, -1, 51, NULL, '10', 1.5, TRUE, [1]] as $limit) {
      $arguments = ['keyword' => 'policy', 'limit' => $limit];
      $this->assertSame($error, $tool->execute($arguments));
    }
  }

}
