<?php

declare(strict_types=1);

namespace Drupal\Tests\policy_evidence_interface\Unit\Controller;

use Drupal\Component\DependencyInjection\Container;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\policy_evidence_interface\Controller\McpServerController;
use Drupal\policy_evidence_interface\Plugin\McpResourceInterface;
use Drupal\policy_evidence_interface\Plugin\McpResourcePluginManager;
use Drupal\policy_evidence_interface\Plugin\McpToolInterface;
use Drupal\policy_evidence_interface\Plugin\McpToolPluginManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests the MCP JSON-RPC dispatcher and public metadata endpoints.
 */
final class McpServerControllerTest extends TestCase {

  private McpToolPluginManager&MockObject $toolManager;

  private McpResourcePluginManager&MockObject $resourceManager;

  private TestableMcpServerController $controller;

  protected function setUp(): void {
    parent::setUp();

    $this->toolManager = $this->createMock(McpToolPluginManager::class);
    $this->resourceManager = $this->createMock(McpResourcePluginManager::class);
    $this->controller = new TestableMcpServerController(
      $this->toolManager,
      $this->resourceManager,
    );
  }

  protected function tearDown(): void {
    \Drupal::unsetContainer();
    parent::tearDown();
  }

  public function testInitializeReturnsNegotiatedServerCapabilities(): void {
    $response = $this->controller->dispatchMessage([
      'jsonrpc' => '2.0',
      'id' => 1,
      'method' => 'initialize',
      'params' => ['protocolVersion' => McpServerController::MCP_VERSION],
    ]);

    self::assertSame('2.0', $response['jsonrpc']);
    self::assertSame(1, $response['id']);
    self::assertSame(McpServerController::MCP_VERSION, $response['result']['protocolVersion']);
    self::assertSame(McpServerController::SERVER_NAME, $response['result']['serverInfo']['name']);
    self::assertArrayHasKey('tools', $response['result']['capabilities']);
    self::assertArrayHasKey('resources', $response['result']['capabilities']);
  }

  public function testPingAndNotificationHandling(): void {
    self::assertSame([
      'jsonrpc' => '2.0',
      'id' => 'ping-1',
      'result' => [],
    ], $this->controller->dispatchMessage([
      'jsonrpc' => '2.0',
      'id' => 'ping-1',
      'method' => 'ping',
    ]));

    self::assertNull($this->controller->dispatchMessage([
      'jsonrpc' => '2.0',
      'method' => 'notifications/initialized',
    ]));
  }

  public function testInvalidMessagesReturnJsonRpcErrors(): void {
    self::assertSame(-32600, $this->controller->dispatchMessage([
      'jsonrpc' => '2.0',
      'id' => 2,
    ])['error']['code']);

    $unknown = $this->controller->dispatchMessage([
      'jsonrpc' => '2.0',
      'id' => 3,
      'method' => 'unknown/method',
    ]);
    self::assertSame(-32601, $unknown['error']['code']);
    self::assertSame('Method not found: unknown/method', $unknown['error']['message']);
  }

  public function testListsAndCallsTools(): void {
    $plugin = $this->createMock(McpToolInterface::class);
    $definition = [
      'name' => 'example_tool',
      'description' => 'An example tool.',
      'inputSchema' => ['type' => 'object'],
    ];
    $plugin->method('getToolDefinition')->willReturn($definition);
    $plugin->expects(self::once())
      ->method('execute')
      ->with(['query' => 'housing'])
      ->willReturn(['matches' => 2]);

    $this->toolManager->method('getDefinitions')->willReturn(['example' => []]);
    $this->toolManager->method('createInstance')
      ->with('example')
      ->willReturn($plugin);

    $list = $this->controller->dispatchMessage([
      'jsonrpc' => '2.0',
      'id' => 4,
      'method' => 'tools/list',
    ]);
    self::assertSame([$definition], $list['result']['tools']);

    $call = $this->controller->dispatchMessage([
      'jsonrpc' => '2.0',
      'id' => 5,
      'method' => 'tools/call',
      'params' => [
        'name' => 'example_tool',
        'arguments' => ['query' => 'housing'],
      ],
    ]);
    self::assertFalse($call['result']['isError']);
    self::assertSame(
      ['matches' => 2],
      json_decode($call['result']['content'][0]['text'], TRUE, flags: JSON_THROW_ON_ERROR),
    );
  }

  public function testToolCallValidatesNameAndKnownPlugin(): void {
    $missing = $this->controller->dispatchMessage([
      'jsonrpc' => '2.0',
      'id' => 6,
      'method' => 'tools/call',
      'params' => [],
    ]);
    self::assertSame(-32602, $missing['error']['code']);

    $this->toolManager->method('getDefinitions')->willReturn([]);
    $unknown = $this->controller->dispatchMessage([
      'jsonrpc' => '2.0',
      'id' => 7,
      'method' => 'tools/call',
      'params' => ['name' => 'missing_tool'],
    ]);
    self::assertSame(-32601, $unknown['error']['code']);
  }

  public function testListsAndReadsResources(): void {
    $plugin = $this->createMock(McpResourceInterface::class);
    $definition = [
      'uri' => 'drupal://example',
      'name' => 'Example resource',
      'description' => 'An example resource.',
      'mimeType' => 'application/json',
    ];
    $content = [
      'uri' => 'drupal://example',
      'mimeType' => 'application/json',
      'text' => '{"ok":true}',
    ];
    $plugin->method('getResourceDefinition')->willReturn($definition);
    $plugin->expects(self::once())->method('read')->willReturn($content);

    $this->resourceManager->method('getDefinitions')->willReturn(['example' => []]);
    $this->resourceManager->method('createInstance')
      ->with('example')
      ->willReturn($plugin);

    $list = $this->controller->dispatchMessage([
      'jsonrpc' => '2.0',
      'id' => 8,
      'method' => 'resources/list',
    ]);
    self::assertSame([$definition], $list['result']['resources']);

    $read = $this->controller->dispatchMessage([
      'jsonrpc' => '2.0',
      'id' => 9,
      'method' => 'resources/read',
      'params' => ['uri' => 'drupal://example'],
    ]);
    self::assertSame([$content], $read['result']['contents']);
  }

  public function testResourceReadValidatesUriAndKnownPlugin(): void {
    $missing = $this->controller->dispatchMessage([
      'jsonrpc' => '2.0',
      'id' => 10,
      'method' => 'resources/read',
      'params' => [],
    ]);
    self::assertSame(-32602, $missing['error']['code']);

    $this->resourceManager->method('getDefinitions')->willReturn([]);
    $unknown = $this->controller->dispatchMessage([
      'jsonrpc' => '2.0',
      'id' => 11,
      'method' => 'resources/read',
      'params' => ['uri' => 'drupal://missing'],
    ]);
    self::assertSame(-32601, $unknown['error']['code']);
  }

  public function testMetadataEndpointsUseTheRequestOrigin(): void {
    $request = Request::create('https://apo.example/_mcp');

    $resourceMetadata = $this->controller->getResourceMetadata($request);
    self::assertSame([
      'resource' => 'https://apo.example/_mcp',
      'authorization_servers' => ['https://apo.example'],
      'scopes_supported' => ['mcp_connector_scope'],
      'bearer_methods_supported' => ['header'],
    ], json_decode($resourceMetadata->getContent(), TRUE, flags: JSON_THROW_ON_ERROR));

    $authMetadata = $this->controller->getAuthMetadata($request);
    $auth = json_decode($authMetadata->getContent(), TRUE, flags: JSON_THROW_ON_ERROR);
    self::assertSame('https://apo.example', $auth['issuer']);
    self::assertSame('https://apo.example/oauth/authorize', $auth['authorization_endpoint']);
    self::assertSame('https://apo.example/oauth/token', $auth['token_endpoint']);
    self::assertSame('https://apo.example/oauth/getclientid', $auth['registration_endpoint']);
  }

  public function testCorsHeadersAreApplied(): void {
    $response = $this->controller->addCorsHeaders(new Response());

    self::assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
    self::assertSame('GET, POST, OPTIONS', $response->headers->get('Access-Control-Allow-Methods'));
    self::assertSame('application/json', $response->headers->get('Content-Type'));
  }

  public function testOptionsRequestReturnsCorsPreflightResponse(): void {
    $response = $this->controller->handle(Request::create(
      'https://apo.example/_mcp',
      'OPTIONS',
    ));

    self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    self::assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
  }

  public function testAuthorizedHttpGetAndPostRequests(): void {
    $this->setCurrentUser(FALSE, ['authenticated', 'mcp_connector']);

    $get = $this->controller->handle(Request::create(
      'https://apo.example/_mcp',
      'GET',
    ));
    self::assertSame(Response::HTTP_OK, $get->getStatusCode());
    self::assertSame('ok', json_decode(
      $get->getContent(),
      TRUE,
      flags: JSON_THROW_ON_ERROR,
    )['status']);

    $post = $this->controller->handle(Request::create(
      'https://apo.example/_mcp',
      'POST',
      server: ['CONTENT_TYPE' => 'application/json'],
      content: json_encode([
        'jsonrpc' => '2.0',
        'id' => 12,
        'method' => 'ping',
      ], JSON_THROW_ON_ERROR),
    ));
    self::assertSame(Response::HTTP_OK, $post->getStatusCode());
    self::assertSame([], json_decode(
      $post->getContent(),
      TRUE,
      flags: JSON_THROW_ON_ERROR,
    )['result']);
  }

  public function testUnauthorizedHttpRequestReturnsBearerChallenge(): void {
    $request = Request::create('https://apo.example/_mcp', 'GET');
    $this->setCurrentUser(TRUE, ['anonymous'], $request);

    $response = $this->controller->handle($request);

    self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    self::assertSame(
      'Bearer resource_metadata="https://apo.example/.well-known/oauth-protected-resource"',
      $response->headers->get('WWW-Authenticate'),
    );
  }

  /**
   * Installs the minimal Drupal container services used by the HTTP handler.
   */
  private function setCurrentUser(
    bool $anonymous,
    array $roles,
    ?Request $request = NULL,
  ): void {
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('isAnonymous')->willReturn($anonymous);
    $account->method('getRoles')->willReturn($roles);

    $requestStack = new RequestStack();
    if ($request !== NULL) {
      $requestStack->push($request);
    }

    $container = new Container();
    $container->set('current_user', $account);
    $container->set('request_stack', $requestStack);
    \Drupal::setContainer($container);
  }

}

/**
 * Exposes protected controller methods for focused unit testing.
 */
final class TestableMcpServerController extends McpServerController {

  public function dispatchMessage(array $message): ?array {
    return $this->dispatch($message);
  }

  public function addCorsHeaders(Response $response): Response {
    return $this->corsResponse($response);
  }

}
