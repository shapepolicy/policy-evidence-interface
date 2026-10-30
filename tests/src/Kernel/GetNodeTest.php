<?php

declare(strict_types=1);

namespace Drupal\Tests\policy_evidence_interface\Kernel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the get_node tool against real nodes.
 *
 * @group policy_evidence_interface
 */
#[Group('policy_evidence_interface')]
#[RunTestsInSeparateProcesses]
final class GetNodeTest extends PolicyEvidenceKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['pei_test'];

  /**
   * Tests the fields returned for a published, untagged article.
   */
  public function testReturnsPublishedNode(): void {
    $node = $this->createArticle('Housing report');

    $result = $this->runTool('get_node', ['nid' => $node->id()]);

    $this->assertSame((int) $node->id(), $result['nid']);
    $this->assertSame('Housing report', $result['title']);
    $this->assertSame('article', $result['type']);
    $this->assertTrue($result['status']);
    $this->assertSame($this->reader->getAccountName(), $result['author']);
    $this->assertStringEndsWith('/node/' . $node->id(), $result['url']);
    $this->assertSame($node->getCreatedTime(), strtotime($result['created']));
  }

  /**
   * Tests that HTML tags are removed from the body text.
   */
  public function testBodyHtmlIsStripped(): void {
    $node = $this->createArticle('Housing report', [
      'body' => ['value' => '<p>Rents <strong>rose</strong>.</p><script>alert(1)</script>'],
    ]);

    $result = $this->runTool('get_node', ['nid' => $node->id()]);

    $this->assertStringNotContainsString('<', $result['body']);
    $this->assertStringContainsString('Rents rose.', $result['body']);
  }

  /**
   * Tests that base fields are left out of "fields" and custom fields are kept.
   */
  public function testFieldsListsOnlyCustomFields(): void {
    $node = $this->createArticle('Housing report', [
      'body' => ['value' => 'Text'],
      'field_tags' => [$this->publicTerm->id()],
    ]);

    $fields = $this->runTool('get_node', ['nid' => $node->id()])['fields'];

    $this->assertArrayHasKey('body', $fields);
    $this->assertSame((string) $this->publicTerm->id(), $fields['field_tags']);
    foreach (['nid', 'title', 'uid', 'status', 'created'] as $base_field) {
      $this->assertArrayNotHasKey($base_field, $fields);
    }
  }

  /**
   * Tests that a nid with no node returns "not found".
   */
  public function testMissingNode(): void {
    $result = $this->runTool('get_node', ['nid' => 999]);

    $this->assertSame(['error' => 'Node 999 not found.'], $result);
  }

  /**
   * Tests that an unpublished node is refused.
   *
   * Only "an error and nothing else" is checked, not the wording, because a
   * future node access check may refuse it first with another message.
   */
  public function testUnpublishedNodeIsRefused(): void {
    $node = $this->createArticle('Draft', ['status' => 0]);

    $result = $this->runTool('get_node', ['nid' => $node->id()]);

    $this->assertSame(['error'], array_keys($result));
  }

  /**
   * Tests that a node with a disallowed tag is refused.
   */
  public function testDisallowedTagIsRefused(): void {
    $node = $this->createArticle('Restricted report', [
      'field_tags' => [$this->restrictedTerm->id()],
    ]);

    $result = $this->runTool('get_node', ['nid' => $node->id()]);

    $this->assertSame(
      ['error' => "Node {$node->id()} is not allowed to be read due to containing tag: restricted."],
      $result,
    );
  }

  /**
   * Tests that one disallowed tag is enough, even next to an allowed tag.
   */
  public function testDisallowedTagAmongOthersIsRefused(): void {
    $node = $this->createArticle('Mixed report', [
      'field_tags' => [$this->publicTerm->id(), $this->restrictedTerm->id()],
    ]);

    $result = $this->runTool('get_node', ['nid' => $node->id()]);

    $this->assertArrayHasKey('error', $result);
  }

  /**
   * Tests that a node with only allowed tags is returned.
   */
  public function testAllowedTagIsReturned(): void {
    $node = $this->createArticle('Public report', [
      'field_tags' => [$this->publicTerm->id()],
    ]);

    $result = $this->runTool('get_node', ['nid' => $node->id()]);

    $this->assertSame('Public report', $result['title']);
  }

  /**
   * Tests that clearing the disallowed list makes a tagged node readable again.
   */
  public function testEmptyDisallowedListAllowsTaggedNode(): void {
    $node = $this->createArticle('Restricted report', [
      'field_tags' => [$this->restrictedTerm->id()],
    ]);
    $this->config('policy_evidence_interface.settings')
      ->set('access_control_tags.disallow_tag_ids', [])
      ->save();

    $result = $this->runTool('get_node', ['nid' => $node->id()]);

    $this->assertSame('Restricted report', $result['title']);
  }

  /**
   * Tests that a node the reader may not view is refused.
   *
   * The pei_test module denies viewing nodes titled "Private ...".
   */
  public function testNodeAccessIsRespected(): void {
    $node = $this->createArticle('Private briefing');
    $this->assertFalse($node->access('view', $this->reader), 'Test setup: the reader cannot view this node.');

    $result = $this->runTool('get_node', ['nid' => $node->id()]);

    $this->assertSame(['error' => "Node {$node->id()} is not accessible."], $result);
  }

}
