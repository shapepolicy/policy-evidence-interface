<?php

declare(strict_types=1);

namespace Drupal\Tests\policy_evidence_interface\Unit\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Session\AccountInterface;
use Drupal\policy_evidence_interface\Controller\McpServerController;
use Drupal\policy_evidence_interface\Plugin\McpResourcePluginManager;
use Drupal\policy_evidence_interface\Plugin\McpToolPluginManager;
use Drupal\policy_evidence_interface\Service\McpRateLimiter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests basic HTTP responses from the MCP server controller.
 */
final class McpServerControllerTest extends TestCase {

  /**
   * Tests the initialize JSON-RPC response for an authorized user.
   */
  public function testInitialize(): void {
    $response = $this->handlePost('{"jsonrpc":"2.0","id":7,"method":"initialize","params":{}}');
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
    $response = $this->handlePost('{');

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
    $response = $this->handlePost('{"jsonrpc":"2.0","method":"notifications/initialized"}');

    $this->assertSame(204, $response->getStatusCode());
    $this->assertSame('', $response->getContent());
  }

  /**
   * Sends a POST as a user with the MCP connector role.
   */
  private function handlePost(string $body): Response {
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAnonymous')->willReturn(FALSE);
    $account->method('getRoles')->willReturn(['authenticated', 'mcp_connector']);

    $container = new ContainerBuilder();
    $container->set('current_user', $account);
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
      return $controller->handle(Request::create('https://example.test/_mcp', 'POST', [], [], [], [], $body));
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
