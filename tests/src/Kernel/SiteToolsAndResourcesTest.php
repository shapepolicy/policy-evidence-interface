<?php

declare(strict_types=1);

namespace Drupal\Tests\policy_evidence_interface\Kernel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests get_site_info, list_content_types and the three MCP resources.
 *
 * @group policy_evidence_interface
 */
#[Group('policy_evidence_interface')]
#[RunTestsInSeparateProcesses]
final class SiteToolsAndResourcesTest extends PolicyEvidenceKernelTestBase {

  /**
   * Reads a resource plugin and returns its content: uri, mimeType and text.
   */
  private function readResource(string $plugin_id): array {
    return \Drupal::service('plugin.manager.policy_evidence_interface.resource')
      ->createInstance($plugin_id)
      ->read();
  }

  /**
   * Reads a resource plugin and returns its decoded JSON text.
   */
  private function readResourceData(string $plugin_id): array {
    return json_decode($this->readResource($plugin_id)['text'], TRUE);
  }

  /**
   * Tests that get_site_info returns the site settings.
   */
  public function testGetSiteInfo(): void {
    $this->config('system.site')
      ->set('name', 'Policy Library')
      ->set('slogan', 'Evidence for policy')
      ->set('mail', 'admin@example.com')
      ->save();

    $result = $this->runTool('get_site_info', []);

    $this->assertSame([
      'site_name' => 'Policy Library',
      'slogan' => 'Evidence for policy',
      'mail' => 'admin@example.com',
      'base_url' => 'http://localhost',
      'drupal_version' => \Drupal::VERSION,
      'default_langcode' => 'en',
    ], $result);
  }

  /**
   * Tests that list_content_types lists each content type once, with its label.
   */
  public function testListContentTypes(): void {
    $types = $this->runTool('list_content_types', [])['content_types'];

    $this->assertCount(2, $types);
    $this->assertEquals(['article' => 'Article', 'page' => 'Page'], array_column($types, 'label', 'machine_name'));
  }

  /**
   * Tests the site-info resource, including its uri and mime type.
   */
  public function testSiteInfoResource(): void {
    $this->config('system.site')->set('name', 'Policy Library')->save();

    $content = $this->readResource('site_info');
    $data = json_decode($content['text'], TRUE);

    $this->assertSame('drupal://site-info', $content['uri']);
    $this->assertSame('application/json', $content['mimeType']);
    $this->assertSame('Policy Library', $data['site_name']);
    $this->assertArrayHasKey('front_page', $data);
  }

  /**
   * Tests the content-types resource.
   */
  public function testContentTypesResource(): void {
    $data = $this->readResourceData('content_types');

    $this->assertEqualsCanonicalizing(['article', 'page'], array_column($data['content_types'], 'machine_name'));
  }

  /**
   * Tests the recent-nodes resource on a site with no content.
   */
  public function testRecentNodesWhenEmpty(): void {
    $this->assertSame(['nodes' => [], 'count' => 0], $this->readResourceData('recent_nodes'));
  }

  /**
   * Tests that recent-nodes is sorted by last change and skips unpublished.
   */
  public function testRecentNodesOrderAndStatus(): void {
    $this->createArticle('Older', ['changed' => 1000]);
    $this->createArticle('Newer', ['changed' => 2000]);
    $this->createArticle('Draft', ['changed' => 3000, 'status' => 0]);

    $data = $this->readResourceData('recent_nodes');

    $this->assertSame(['Newer', 'Older'], array_column($data['nodes'], 'title'));
  }

  /**
   * Tests that recent-nodes returns at most 20 nodes.
   */
  public function testRecentNodesReturnsAtMostTwenty(): void {
    $this->createHousingArticles(21);

    $data = $this->readResourceData('recent_nodes');

    $this->assertSame(20, $data['count']);
    $this->assertCount(20, $data['nodes']);
  }

}
