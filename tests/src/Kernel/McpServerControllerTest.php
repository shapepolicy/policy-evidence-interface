<?php

declare(strict_types=1);

namespace Drupal\Tests\policy_evidence_interface\Kernel;

use Drupal\Core\Session\AnonymousUserSession;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the HTTP MCP endpoint (/_mcp) by calling its controller directly.
 *
 * Routing and Simple OAuth are not involved: each test sets the current user
 * itself, as Simple OAuth would after checking a bearer token.
 *
 * @group policy_evidence_interface
 */
#[Group('policy_evidence_interface')]
#[RunTestsInSeparateProcesses]
final class McpServerControllerTest extends PolicyEvidenceKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->allowToolCalls();
    $this->grantMcpRole();
  }

  /**
   * Tests that a CORS preflight is answered before any login check.
   */
  public function testPreflightIsAnsweredWithoutLogin(): void {
    $this->setCurrentUser(new AnonymousUserSession());

    $response = $this->sendHttp('OPTIONS');

    $this->assertSame(204, $response->getStatusCode());
    $this->assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
  }

  /**
   * Tests that an anonymous request gets 401 with the OAuth metadata link.
   *
   * The link must use the host the request came in on.
   */
  public function testAnonymousRequestIsUnauthorized(): void {
    $this->setCurrentUser(new AnonymousUserSession());

    $response = $this->sendHttp('POST', '{}', 'https://policy.example.org/_mcp');

    $this->assertSame(401, $response->getStatusCode());
    $this->assertSame(
      'Bearer resource_metadata="https://policy.example.org/.well-known/oauth-protected-resource"',
      $response->headers->get('WWW-Authenticate'),
    );
  }

  /**
   * Tests that a logged-in user without the mcp_connector role gets 401.
   */
  public function testUserWithoutRoleIsUnauthorized(): void {
    $this->setUpCurrentUser([], ['access content']);

    $response = $this->sendHttp('POST', '{}');

    $this->assertSame(401, $response->getStatusCode());
  }

  /**
   * Tests that a body that is not JSON gets a parse error.
   */
  public function testInvalidJsonIsParseError(): void {
    $response = $this->sendHttp('POST', '{not json');

    $this->assertSame(400, $response->getStatusCode());
    $this->assertSame(-32700, json_decode($response->getContent(), TRUE)['error']['code']);
  }

  /**
   * Tests that valid JSON which is not an object also gets a parse error.
   */
  public function testJsonScalarIsParseError(): void {
    $response = $this->sendHttp('POST', '42');

    $this->assertSame(-32700, json_decode($response->getContent(), TRUE)['error']['code']);
  }

  /**
   * Tests that a message without "method" is an invalid request.
   */
  public function testMissingMethodIsInvalidRequest(): void {
    $response = $this->sendHttp('POST', '{"jsonrpc":"2.0","id":1}');

    $this->assertSame(-32600, json_decode($response->getContent(), TRUE)['error']['code']);
  }

  /**
   * Tests that an unknown method returns "method not found".
   */
  public function testUnknownMethod(): void {
    $this->assertSame(-32601, $this->httpRpc('unknown/method')['error']['code']);
  }

  /**
   * Tests the initialize handshake.
   */
  public function testInitialize(): void {
    $result = $this->httpRpc('initialize')['result'];

    $this->assertSame('2024-11-05', $result['protocolVersion']);
    $this->assertSame(
      ['name' => 'Drupal MCP Server', 'version' => '1.0.0'],
      $result['serverInfo'],
    );
    $this->assertSame(['listChanged' => FALSE], $result['capabilities']['tools']);
  }

  /**
   * Tests that the "initialized" notification gets an empty 204 response.
   */
  public function testNotificationHasNoBody(): void {
    $response = $this->sendHttp('POST', '{"jsonrpc":"2.0","method":"notifications/initialized"}');

    $this->assertSame(204, $response->getStatusCode());
    $this->assertSame('', $response->getContent());
  }

  /**
   * Tests a batch: one response per request, in order, none for notifications.
   */
  public function testBatchRequest(): void {
    $batch = json_encode([
      ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize'],
      ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'],
      ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'prompts/list'],
    ]);

    $responses = json_decode($this->sendHttp('POST', $batch)->getContent(), TRUE);

    $this->assertSame([1, 2], array_column($responses, 'id'));
  }

  /**
   * Tests that tools/list returns the five core tools.
   */
  public function testToolsList(): void {
    $names = array_column($this->httpRpc('tools/list')['result']['tools'], 'name');
    sort($names);

    $this->assertSame(self::CORE_TOOLS, $names);
  }

  /**
   * Tests a successful tools/call: the tool result is returned as JSON text.
   */
  public function testToolsCallReturnsText(): void {
    $this->config('system.site')->set('name', 'Policy Library')->save();

    $result = $this->httpRpc('tools/call', ['name' => 'get_site_info', 'arguments' => []])['result'];

    $this->assertFalse($result['isError']);
    $this->assertSame('text', $result['content'][0]['type']);
    $this->assertSame('Policy Library', json_decode($result['content'][0]['text'], TRUE)['site_name']);
  }

  /**
   * Tests tools/call without a tool name.
   */
  public function testToolsCallWithoutName(): void {
    $this->assertSame(-32602, $this->httpRpc('tools/call', [])['error']['code']);
  }

  /**
   * Tests tools/call with a tool that does not exist, such as a write tool.
   */
  public function testToolsCallUnknownTool(): void {
    $error = $this->httpRpc('tools/call', ['name' => 'delete_node'])['error'];

    $this->assertSame(-32601, $error['code']);
    $this->assertSame('Unknown tool: delete_node', $error['message']);
  }

  /**
   * Tests that a call over the tool's limit is refused with a retry time.
   */
  public function testToolsCallOverRateLimit(): void {
    $this->config('policy_evidence_interface.settings')
      ->set('rate_limit.tools.get_site_info.limit', 1)
      ->save();
    $this->httpRpc('tools/call', ['name' => 'get_site_info']);

    $result = $this->httpRpc('tools/call', ['name' => 'get_site_info'])['result'];

    $this->assertTrue($result['isError']);
    $this->assertStringStartsWith(
      'Rate limit exceeded for tool "get_site_info". Try again in ',
      $result['content'][0]['text'],
    );
  }

  /**
   * Tests that resources/list returns the three resources.
   */
  public function testResourcesList(): void {
    $uris = array_column($this->httpRpc('resources/list')['result']['resources'], 'uri');
    sort($uris);

    $this->assertSame(['drupal://content-types', 'drupal://recent-nodes', 'drupal://site-info'], $uris);
  }

  /**
   * Tests that resources/read returns the requested resource.
   */
  public function testResourcesRead(): void {
    $contents = $this->httpRpc('resources/read', ['uri' => 'drupal://site-info'])['result']['contents'];

    $this->assertSame('drupal://site-info', $contents[0]['uri']);
  }

  /**
   * Tests resources/read without a uri.
   */
  public function testResourcesReadWithoutUri(): void {
    $this->assertSame(-32602, $this->httpRpc('resources/read', [])['error']['code']);
  }

  /**
   * Tests resources/read with a uri that does not exist.
   */
  public function testResourcesReadUnknownUri(): void {
    $this->assertSame(-32601, $this->httpRpc('resources/read', ['uri' => 'drupal://missing'])['error']['code']);
  }

  /**
   * Tests that a tool error is returned with isError set.
   */
  public function testToolsCallErrorIsFlagged(): void {
    $result = $this->httpRpc('tools/call', ['name' => 'get_node', 'arguments' => ['nid' => 999]])['result'];

    $this->assertTrue($result['isError']);
    $this->assertSame('Node 999 not found.', json_decode($result['content'][0]['text'], TRUE)['error']);
  }

}
