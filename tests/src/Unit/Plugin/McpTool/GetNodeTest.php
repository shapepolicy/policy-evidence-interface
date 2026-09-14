<?php

declare(strict_types=1);

namespace Drupal\Tests\policy_evidence_interface\Unit\Plugin\McpTool;

use Drupal\policy_evidence_interface\Plugin\McpTool\GetNode;
use PHPUnit\Framework\TestCase;

/**
 * Tests the GetNode input contract.
 */
final class GetNodeTest extends TestCase {

  /**
   * Tests that the input schema requires an integer node ID.
   */
  public function testInputSchema(): void {
    $tool = new GetNode([], 'get_node', []);
    $schema = $tool->getToolDefinition()['inputSchema'];

    $this->assertSame('object', $schema['type']);
    $this->assertSame('integer', $schema['properties']['nid']['type']);
    $this->assertSame(['nid'], $schema['required']);
  }

  /**
   * Tests that invalid node IDs are rejected before accessing Drupal services.
   */
  public function testInvalidNodeId(): void {
    $tool = new GetNode([], 'get_node', []);
    $error = ['error' => 'A valid nid is required.'];

    $this->assertSame($error, $tool->execute([]));
    $this->assertSame($error, $tool->execute(['nid' => 0]));
    $this->assertSame($error, $tool->execute(['nid' => -1]));
    $this->assertSame($error, $tool->execute(['nid' => 'invalid']));
  }

}
