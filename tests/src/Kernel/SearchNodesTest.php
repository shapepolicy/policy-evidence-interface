<?php

declare(strict_types=1);

namespace Drupal\Tests\policy_evidence_interface\Kernel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the search_nodes tool against real nodes.
 *
 * @group policy_evidence_interface
 */
#[Group('policy_evidence_interface')]
#[RunTestsInSeparateProcesses]
final class SearchNodesTest extends PolicyEvidenceKernelTestBase {

  /**
   * Returns the titles from a search_nodes result, in result order.
   */
  private function searchTitles(array $arguments): array {
    $result = $this->runTool('search_nodes', $arguments);
    return array_column($result['results'], 'title');
  }

  /**
   * Tests that the keyword matches part of a title; other titles are left out.
   */
  public function testKeywordMatchesPartOfTitle(): void {
    $this->createArticle('Social housing review');
    $this->createArticle('Transport plan');

    $this->assertSame(['Social housing review'], $this->searchTitles(['keyword' => 'housing']));
  }

  /**
   * Tests the fields returned for each result and that total matches the count.
   */
  public function testResultShape(): void {
    $node = $this->createArticle('Housing review');

    $result = $this->runTool('search_nodes', ['keyword' => 'Housing']);

    $this->assertSame(1, $result['total']);
    $this->assertCount(1, $result['results']);
    $this->assertSame((int) $node->id(), $result['results'][0]['nid']);
    $this->assertSame('article', $result['results'][0]['type']);
    $this->assertStringEndsWith('/node/' . $node->id(), $result['results'][0]['url']);
    $this->assertSame($node->getCreatedTime(), strtotime($result['results'][0]['created']));
  }

  /**
   * Tests that newer nodes come first.
   */
  public function testNewestFirst(): void {
    $this->createArticle('Housing old', ['created' => 1000]);
    $this->createArticle('Housing new', ['created' => 2000]);

    $this->assertSame(['Housing new', 'Housing old'], $this->searchTitles(['keyword' => 'Housing']));
  }

  /**
   * Tests a keyword that matches nothing.
   */
  public function testNoMatches(): void {
    $this->createArticle('Housing review');

    $result = $this->runTool('search_nodes', ['keyword' => 'climate']);

    $this->assertSame(['total' => 0, 'results' => []], $result);
  }

  /**
   * Tests that SQL in the keyword is searched for as plain text.
   */
  public function testSqlInKeywordIsTreatedAsText(): void {
    $this->createArticle('Housing review');

    $this->assertSame([], $this->searchTitles(['keyword' => "' OR 1=1 --"]));
  }

  /**
   * Tests that unpublished nodes are not returned.
   */
  public function testUnpublishedNodesAreExcluded(): void {
    $this->createArticle('Housing draft', ['status' => 0]);
    $this->createArticle('Housing final');

    $this->assertSame(['Housing final'], $this->searchTitles(['keyword' => 'Housing']));
  }

  /**
   * Tests that nodes with a disallowed tag are left out, even with other tags.
   */
  public function testDisallowedTagsAreExcluded(): void {
    $this->createArticle('Housing restricted', ['field_tags' => [$this->restrictedTerm->id()]]);
    $this->createArticle('Housing mixed', [
      'field_tags' => [$this->publicTerm->id(), $this->restrictedTerm->id()],
    ]);
    $this->createArticle('Housing public', ['field_tags' => [$this->publicTerm->id()]]);
    $this->createArticle('Housing untagged');

    $titles = $this->searchTitles(['keyword' => 'Housing']);
    sort($titles);

    $this->assertSame(['Housing public', 'Housing untagged'], $titles);
  }

  /**
   * Tests the content_type filter.
   */
  public function testContentTypeFilter(): void {
    $this->createArticle('Housing article');
    $this->createArticle('Housing page', ['type' => 'page']);

    $this->assertSame(['Housing page'], $this->searchTitles(['keyword' => 'Housing', 'content_type' => 'page']));
  }

  /**
   * Tests that an unknown content type gives no results.
   */
  public function testUnknownContentType(): void {
    $this->createArticle('Housing article');

    $this->assertSame([], $this->searchTitles(['keyword' => 'Housing', 'content_type' => 'missing']));
  }

  /**
   * Tests that 10 results are returned when no limit is given.
   */
  public function testDefaultLimitIsTen(): void {
    $this->createHousingArticles(11);

    $this->assertCount(10, $this->searchTitles(['keyword' => 'Housing']));
  }

  /**
   * Tests the smallest useful limit, 1.
   */
  public function testLimitOne(): void {
    $this->createHousingArticles(3);

    $this->assertCount(1, $this->searchTitles(['keyword' => 'Housing', 'limit' => 1]));
  }

  /**
   * Tests that a limit above 50 is capped at 50.
   */
  public function testLimitIsCappedAtFifty(): void {
    $this->createHousingArticles(51);

    $this->assertCount(50, $this->searchTitles(['keyword' => 'Housing', 'limit' => 51]));
  }

  /**
   * Tests that a limit below 1 is raised to 1 rather than passed to the query.
   */
  public function testLimitBelowOneIsRaisedToOne(): void {
    $this->createHousingArticles(3);

    $this->assertCount(1, $this->searchTitles(['keyword' => 'Housing', 'limit' => -1]));
  }

}
