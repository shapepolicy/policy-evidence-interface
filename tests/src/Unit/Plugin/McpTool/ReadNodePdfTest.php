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

    $invalid_arguments = [
      [],
      ['nid' => 0],
      ['nid' => -1],
      ['nid' => '12'],
      ['nid' => '12abc'],
      ['nid' => 1.5],
      ['nid' => TRUE],
      ['nid' => [1]],
    ];

    foreach ($invalid_arguments as $arguments) {
      $this->assertSame($error, $tool->execute($arguments));
    }
  }

  /**
   * Tests that invalid starting pages are rejected before service access.
   */
  public function testInvalidPageStart(): void {
    $tool = new ReadNodePdf([], 'read_node_pdf', []);
    $error = ['error' => 'A valid page_start is required.'];

    $invalid_arguments = [
      ['nid' => 1, 'page_start' => 0],
      ['nid' => 1, 'page_start' => -1],
      ['nid' => 1, 'page_start' => '1'],
      ['nid' => 1, 'page_start' => 1.5],
      ['nid' => 1, 'page_start' => TRUE],
      ['nid' => 1, 'page_start' => [1]],
    ];

    foreach ($invalid_arguments as $arguments) {
      $this->assertSame($error, $tool->execute($arguments));
    }
  }

}
