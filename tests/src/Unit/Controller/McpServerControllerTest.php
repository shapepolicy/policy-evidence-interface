<?php

declare(strict_types=1);

namespace Drupal\Tests\policy_evidence_interface\Unit\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Session\UserSession;
use Drupal\policy_evidence_interface\Controller\McpServerController;
use Drupal\policy_evidence_interface\Plugin\McpResourcePluginManager;
use Drupal\policy_evidence_interface\Plugin\McpToolPluginManager;
use Drupal\policy_evidence_interface\Service\McpRateLimiter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests basic HTTP responses from the MCP server controller.
 */
final class McpServerControllerTest extends TestCase {

  /**
   * Tests the initialize JSON-RPC response for an authorized user.
   */
  public function testInitialize(): void {
    $response = $this->handleRequest('{"jsonrpc":"2.0","id":7,"method":"initialize","params":{}}');
    $body = json_decode($response->getContent(), TRUE, 512, JSON_THROW_ON_ERROR);

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('2.0', $body['jsonrpc']);
    $this->assertSame(7, $body['id']);
    $this->assertSame('2024-11-05', $body['result']['protocolVersion']);
    $this->assertSame('Drupal MCP Server', $body['result']['serverInfo']['name']);
    $this->assertArrayHasKey('tools', $body['result']['capabilities']);
    $this->assertArrayHasKey('resources', $body['result']['capabilities']);
  }

  /**
   * Tests malformed JSON produces a parse error.
   */
  public function testMalformedJson(): void {
    $response = $this->handleRequest('{');

    $this->assertSame(400, $response->getStatusCode());
    $this->assertSame([
      'jsonrpc' => '2.0',
      'id' => NULL,
      'error' => [
        'code' => -32700,
        'message' => 'Parse error: invalid JSON',
      ],
    ], json_decode($response->getContent(), TRUE, 512, JSON_THROW_ON_ERROR));
  }

  /**
   * Tests initialized notifications produce no response body.
   */
  public function testInitializedNotification(): void {
    $response = $this->handleRequest('{"jsonrpc":"2.0","method":"notifications/initialized"}');

    $this->assertSame(204, $response->getStatusCode());
    $this->assertSame('', $response->getContent());
  }

  /**
   * Tests anonymous requests are rejected with a bearer challenge.
   */
  public function testAnonymousPostIsUnauthorized(): void {
    $this->assertUnauthorizedResponse($this->handleRequest(
      '{"jsonrpc":"2.0","id":7,"method":"initialize"}',
      'POST',
      new UserSession(),
    ));
  }

  /**
   * Tests authenticated users without the connector role are rejected.
   */
  public function testPostWithoutConnectorRoleIsUnauthorized(): void {
    $this->assertUnauthorizedResponse($this->handleRequest(
      '{"jsonrpc":"2.0","id":7,"method":"initialize"}',
      'POST',
      new UserSession(['uid' => 1, 'roles' => ['authenticated']]),
    ));
  }

  /**
   * Tests preflight requests do not require an authenticated account.
   */
  public function testAnonymousOptionsPreflight(): void {
    $response = $this->handleRequest('', 'OPTIONS', new UserSession());

    $this->assertSame(204, $response->getStatusCode());
    $this->assertSame('', $response->getContent());
    $this->assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
    $this->assertSame('GET, POST, OPTIONS', $response->headers->get('Access-Control-Allow-Methods'));
    $this->assertSame('Content-Type, Authorization, Mcp-Session-Id', $response->headers->get('Access-Control-Allow-Headers'));
  }

  /**
   * Checks the access-denied response and resource-metadata challenge.
   */
  private function assertUnauthorizedResponse(Response $response): void {
    $this->assertSame(401, $response->getStatusCode());
    $this->assertSame('Bearer resource_metadata="https://example.test/.well-known/oauth-protected-resource"', $response->headers->get('WWW-Authenticate'));
    $this->assertSame('unauthorized', json_decode($response->getContent(), TRUE, 512, JSON_THROW_ON_ERROR)['error']);
  }

  /**
   * Sends an HTTP request as the supplied user or an MCP connector.
   */
  private function handleRequest(string $body, string $method = 'POST', ?UserSession $account = NULL): Response {
    $request = Request::create('https://example.test/_mcp', $method, [], [], [], [], $body);
    $request_stack = new RequestStack();
    $request_stack->push($request);

    $container = new ContainerBuilder();
    $container->set('current_user', $account ?? new UserSession([
      'uid' => 1,
      'roles' => ['authenticated', 'mcp_connector'],
    ]));
    $container->set('request_stack', $request_stack);
    $original_container = \Drupal::hasContainer() ? \Drupal::getContainer() : NULL;
    \Drupal::setContainer($container);

    try {
      $controller = new McpServerController(
        $this->createMock(McpToolPluginManager::class),
        $this->createMock(McpResourcePluginManager::class),
        new McpRateLimiter(
          $this->createMock(CacheBackendInterface::class),
          $this->createMock(TimeInterface::class),
        ),
      );
      return $controller->handle($request);
    }
    finally {
      if ($original_container) {
        \Drupal::setContainer($original_container);
      }
      else {
        \Drupal::unsetContainer();
      }
    }
  }

}
