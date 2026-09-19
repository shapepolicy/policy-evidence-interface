<?php

declare(strict_types=1);

namespace Drupal\Tests\policy_evidence_interface\Unit\Plugin\McpTool;

use Drupal\policy_evidence_interface\Plugin\McpTool\ReadNodePdf;
use PHPUnit\Framework\TestCase;

/**
 * Tests the ReadNodePdf input contract.
 */
final class ReadNodePdfTest extends TestCase {

  /**
   * Tests the node ID and starting-page input schema.
   */
  public function testInputSchema(): void {
    $tool = new ReadNodePdf([], 'read_node_pdf', []);
    $schema = $tool->getToolDefinition()['inputSchema'];

    $this->assertSame('object', $schema['type']);
    $this->assertSame('integer', $schema['properties']['nid']['type']);
    $this->assertSame('integer', $schema['properties']['page_start']['type']);
    $this->assertSame(1, $schema['properties']['page_start']['default']);
    $this->assertSame(['nid'], $schema['required']);
  }

  /**
   * Tests that invalid node IDs are rejected before accessing Drupal services.
   */
  public function testInvalidNodeId(): void {
    $tool = new ReadNodePdf([], 'read_node_pdf', []);
    $error = ['error' => 'A valid nid is required.'];

    $this->assertSame($error, $tool->execute([]));

    foreach ([0, -1, '12', '12abc', 1.5, TRUE, [1]] as $nid) {
      $this->assertSame($error, $tool->execute(['nid' => $nid]));
    }
  }

  /**
   * Tests that invalid starting pages are rejected before service access.
   */
  public function testInvalidPageStart(): void {
    $tool = new ReadNodePdf([], 'read_node_pdf', []);
    $error = ['error' => 'A valid page_start is required.'];

    foreach ([0, -1, NULL, '1', 1.5, TRUE, [1]] as $page_start) {
      $arguments = ['nid' => 1, 'page_start' => $page_start];
      $this->assertSame($error, $tool->execute($arguments));
    }
  }

}
