<?php

declare(strict_types=1);

namespace Drupal\Tests\policy_evidence_interface\Kernel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the message handling of the stdio server (drush mcp:server).
 *
 * The command reads stdin in a loop, so the tests pass one message at a time
 * to its dispatch() method (see stdioRpc() in the base class).
 *
 * @group policy_evidence_interface
 */
#[Group('policy_evidence_interface')]
#[RunTestsInSeparateProcesses]
final class McpServerCommandsTest extends PolicyEvidenceKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->allowToolCalls();
  }

  /**
   * Tests the initialize handshake over stdio.
   */
  public function testInitialize(): void {
    $response = $this->stdioRpc('initialize');

    $this->assertSame(1, $response['id']);
    $this->assertSame('2024-11-05', $response['result']['protocolVersion']);
  }

  /**
   * Tests that a notification gets no response.
   */
  public function testNotificationHasNoResponse(): void {
    $this->assertNull($this->stdioRpc('notifications/initialized', [], NULL));
  }

  /**
   * Tests that a message without "method" is an invalid request.
   */
  public function testMissingMethod(): void {
    $this->assertSame(-32600, $this->stdioRpc(NULL)['error']['code']);
  }

  /**
   * Tests that an unknown method returns "method not found".
   */
  public function testUnknownMethod(): void {
    $this->assertSame(-32601, $this->stdioRpc('unknown/method')['error']['code']);
  }

  /**
   * Tests that stdio lists the same five tools as HTTP.
   */
  public function testToolsList(): void {
    $names = array_column($this->stdioRpc('tools/list')['result']['tools'], 'name');
    sort($names);

    $this->assertSame(self::CORE_TOOLS, $names);
  }

  /**
   * Tests a successful tools/call over stdio.
   */
  public function testToolsCall(): void {
    $result = $this->stdioRpc('tools/call', ['name' => 'get_site_info'])['result'];

    $this->assertFalse($result['isError']);
    $this->assertSame(\Drupal::VERSION, json_decode($result['content'][0]['text'], TRUE)['drupal_version']);
  }

  /**
   * Tests that stdio applies the rate limit too.
   */
  public function testToolsCallOverRateLimit(): void {
    $this->config('policy_evidence_interface.settings')
      ->set('rate_limit.tools.get_site_info.limit', 0)
      ->save();

    $result = $this->stdioRpc('tools/call', ['name' => 'get_site_info'])['result'];

    $this->assertTrue($result['isError']);
  }

  /**
   * Tests that resources/read works over stdio.
   */
  public function testResourcesRead(): void {
    $response = $this->stdioRpc('resources/read', ['uri' => 'drupal://content-types']);

    $this->assertSame('drupal://content-types', $response['result']['contents'][0]['uri']);
  }

}
