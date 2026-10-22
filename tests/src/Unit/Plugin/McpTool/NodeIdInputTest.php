<?php

declare(strict_types=1);

namespace Drupal\Tests\policy_evidence_interface\Unit\Plugin\McpTool;

use Drupal\policy_evidence_interface\Plugin\McpTool\GetNode;
use Drupal\policy_evidence_interface\Plugin\McpTool\ReadNodePdf;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that get_node and read_node_pdf reject a node ID that is not positive.
 *
 * No Drupal container exists in a unit test, so these tests also prove the
 * tools return the error before loading anything from the site.
 *
 * @group policy_evidence_interface
 */
#[Group('policy_evidence_interface')]
final class NodeIdInputTest extends UnitTestCase {

  /**
   * Arguments whose nid becomes 0 after the tools' (int) cast.
   */
  public static function providerNidNotPositive(): array {
    return [
      'missing' => [[]],
      'zero' => [['nid' => 0]],
      'zero string' => [['nid' => '0']],
      'empty string' => [['nid' => '']],
      'null' => [['nid' => NULL]],
      'false' => [['nid' => FALSE]],
      'text' => [['nid' => 'abc']],
      'empty array' => [['nid' => []]],
    ];
  }

  /**
   * Tests that get_node returns the "valid nid" error for each input.
   *
   * @dataProvider providerNidNotPositive
   */
  #[DataProvider('providerNidNotPositive')]
  public function testGetNodeRejectsNid(array $arguments): void {
    $tool = new GetNode([], 'get_node', []);

    $this->assertSame(['error' => 'A valid nid is required.'], $tool->execute($arguments));
  }

  /**
   * Tests that read_node_pdf returns the "valid nid" error for each input.
   *
   * @dataProvider providerNidNotPositive
   */
  #[DataProvider('providerNidNotPositive')]
  public function testReadNodePdfRejectsNid(array $arguments): void {
    $tool = new ReadNodePdf([], 'read_node_pdf', []);

    $this->assertSame(['error' => 'A valid nid is required.'], $tool->execute($arguments));
  }

}
