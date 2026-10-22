<?php

declare(strict_types=1);

namespace Drupal\Tests\policy_evidence_interface\Kernel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the read_node_pdf tool with the PDFs in tests/fixtures.
 *
 * The fixture three-pages.pdf has one sentence per page, numbers-only.pdf has
 * one page of digits, and broken.pdf is not a complete PDF.
 *
 * @group policy_evidence_interface
 */
#[Group('policy_evidence_interface')]
#[RunTestsInSeparateProcesses]
final class ReadNodePdfTest extends PolicyEvidenceKernelTestBase {

  /**
   * Tests that page 1 is returned when page_start is not given.
   */
  public function testDefaultsToFirstPage(): void {
    $node = $this->createArticleWithPdf('three-pages.pdf');

    $result = $this->runTool('read_node_pdf', ['nid' => $node->id()]);

    $this->assertSame(1, $result['page']);
    $this->assertSame(3, $result['total_pages']);
    $this->assertSame('Page one discusses housing affordability policy.', trim($result['text']));
  }

  /**
   * Tests the metadata returned with the page text.
   */
  public function testReturnsFileDetails(): void {
    $node = $this->createArticleWithPdf('three-pages.pdf');

    $result = $this->runTool('read_node_pdf', ['nid' => $node->id()]);

    $this->assertSame((int) $node->id(), $result['nid']);
    $this->assertSame('Report with PDF', $result['title']);
    $this->assertSame('three-pages.pdf', $result['file_name']);
    $this->assertSame('public://three-pages.pdf', $result['file_uri']);
  }

  /**
   * Tests the last page, which is the upper boundary.
   */
  public function testLastPage(): void {
    $node = $this->createArticleWithPdf('three-pages.pdf');

    $result = $this->runTool('read_node_pdf', ['nid' => $node->id(), 'page_start' => 3]);

    $this->assertSame('Page three summarises the recommendations.', trim($result['text']));
  }

  /**
   * Tests that the page after the last one is out of range.
   */
  public function testPageAfterLastIsOutOfRange(): void {
    $node = $this->createArticleWithPdf('three-pages.pdf');

    $result = $this->runTool('read_node_pdf', ['nid' => $node->id(), 'page_start' => 4]);

    $this->assertSame(['error' => 'Page 4 is out of range. This PDF has 3 page(s).'], $result);
  }

  /**
   * Tests that page 0 is out of range, because pages start at 1.
   */
  public function testPageZeroIsOutOfRange(): void {
    $node = $this->createArticleWithPdf('three-pages.pdf');

    $result = $this->runTool('read_node_pdf', ['nid' => $node->id(), 'page_start' => 0]);

    $this->assertSame(['error' => 'Page 0 is out of range. This PDF has 3 page(s).'], $result);
  }

  /**
   * Tests that a page with almost no letters is reported as non-extractable.
   */
  public function testPageWithoutLettersIsReported(): void {
    $node = $this->createArticleWithPdf('numbers-only.pdf');

    $result = $this->runTool('read_node_pdf', ['nid' => $node->id()]);

    $this->assertSame(
      ['error' => 'This PDF page contains non-extractable text (likely font-encoded or scanned).'],
      $result,
    );
  }

  /**
   * Tests that a broken PDF returns a parse error instead of failing.
   */
  public function testBrokenPdfReturnsError(): void {
    $node = $this->createArticleWithPdf('broken.pdf');

    $result = $this->runTool('read_node_pdf', ['nid' => $node->id()]);

    $this->assertStringStartsWith('Failed to parse PDF: ', $result['error']);
  }

  /**
   * Tests a node whose PDF field is empty.
   */
  public function testNodeWithoutPdf(): void {
    $node = $this->createArticle('No attachment');

    $result = $this->runTool('read_node_pdf', ['nid' => $node->id()]);

    $this->assertSame(['error' => "Node {$node->id()} has no PDF file attached."], $result);
  }

  /**
   * Tests a file entity whose file has been removed from disk.
   */
  public function testFileMissingOnDisk(): void {
    $node = $this->createArticleWithPdf('three-pages.pdf');
    unlink($this->publicDirectory . '/three-pages.pdf');

    $result = $this->runTool('read_node_pdf', ['nid' => $node->id()]);

    $this->assertStringStartsWith("PDF file not found on disk for node {$node->id()}.", $result['error']);
  }

  /**
   * Tests that a nid with no node returns "not found".
   */
  public function testMissingNode(): void {
    $result = $this->runTool('read_node_pdf', ['nid' => 999]);

    $this->assertSame(['error' => 'Node 999 not found.'], $result);
  }

  /**
   * Tests that the PDF of an unpublished node is refused.
   *
   * Only "an error and nothing else" is checked, not the wording, because a
   * future node access check may refuse it first with another message.
   */
  public function testUnpublishedNodeIsRefused(): void {
    $node = $this->createArticleWithPdf('three-pages.pdf', ['status' => 0]);

    $result = $this->runTool('read_node_pdf', ['nid' => $node->id()]);

    $this->assertSame(['error'], array_keys($result));
  }

  /**
   * Tests that the PDF of a node with a disallowed tag is refused.
   */
  public function testDisallowedTagIsRefused(): void {
    $node = $this->createArticleWithPdf('three-pages.pdf', [
      'field_tags' => [$this->restrictedTerm->id()],
    ]);

    $result = $this->runTool('read_node_pdf', ['nid' => $node->id()]);

    $this->assertSame(
      ['error' => "Node {$node->id()} is not allowed to be read due to containing tag: restricted."],
      $result,
    );
  }

}
