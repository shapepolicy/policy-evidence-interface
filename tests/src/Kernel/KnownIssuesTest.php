<?php

declare(strict_types=1);

namespace Drupal\Tests\policy_evidence_interface\Kernel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Bugs found while writing the tests. These tests fail until the bug is fixed.
 *
 * Each test checks the correct behaviour, so it fails today. They are kept out
 * of the normal "policy_evidence_interface" group so that group passes. Run
 * them with "--group policy_evidence_interface_known_issue".
 *
 * @group policy_evidence_interface_known_issue
 */
#[Group('policy_evidence_interface_known_issue')]
#[RunTestsInSeparateProcesses]
final class KnownIssuesTest extends PolicyEvidenceKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['pei_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->allowToolCalls();
    $this->grantMcpRole();
  }

  /**
   * Tests that a tool error is flagged with isError over HTTP.
   *
   * MCP says a failed tool call should set "isError": true. Today the
   * controller always sends false, so clients treat "Node 999 not found." as
   * a successful result.
   */
  public function testToolErrorIsFlaggedOverHttp(): void {
    $result = $this->httpRpc('tools/call', ['name' => 'get_node', 'arguments' => ['nid' => 999]])['result'];

    $this->assertTrue($result['isError'], 'isError should be true when the tool returns an error.');
  }

  /**
   * Tests that a tool error is flagged with isError over stdio.
   *
   * Same as above for drush mcp:server.
   */
  public function testToolErrorIsFlaggedOverStdio(): void {
    $result = $this->stdioRpc('tools/call', ['name' => 'get_node', 'arguments' => ['nid' => 999]])['result'];

    $this->assertTrue($result['isError'], 'isError should be true when the tool returns an error.');
  }

  /**
   * Tests that get_node respects Drupal node access.
   *
   * The pei_test module denies viewing nodes titled "Private ...". get_node
   * only checks the published flag and tags, so it returns the node anyway.
   */
  public function testGetNodeRespectsNodeAccess(): void {
    $node = $this->createArticle('Private briefing');
    $this->assertFalse($node->access('view', $this->reader), 'Test setup: the reader cannot view this node.');

    $result = $this->runTool('get_node', ['nid' => $node->id()]);

    $this->assertArrayHasKey('error', $result, 'get_node returned a node the reader cannot view.');
  }

  /**
   * Tests that read_node_pdf respects Drupal node access.
   *
   * Same as above: the PDF of a node the reader cannot view is returned.
   */
  public function testReadNodePdfRespectsNodeAccess(): void {
    $node = $this->createArticleWithPdf('three-pages.pdf', ['title' => 'Private briefing']);
    $this->assertFalse($node->access('view', $this->reader), 'Test setup: the reader cannot view this node.');

    $result = $this->runTool('read_node_pdf', ['nid' => $node->id()]);

    $this->assertArrayHasKey('error', $result, 'read_node_pdf returned the PDF of a node the reader cannot view.');
  }

  /**
   * Tests that a negative search limit does not bypass the maximum of 50.
   *
   * The limit is only capped from above. With -1, SQLite returns every match
   * and MariaDB/MySQL fail with an SQL syntax error.
   */
  public function testNegativeSearchLimitIsHandled(): void {
    $this->createHousingArticles(51);

    $result = $this->runTool('search_nodes', ['keyword' => 'Housing', 'limit' => -1]);

    // A fix may refuse the limit with an error instead of returning results.
    $this->assertTrue(
      isset($result['error']) || count($result['results']) <= 50,
      'A negative limit returned more than 50 results.',
    );
  }

}
