<?php

declare(strict_types=1);

namespace Drupal\Tests\policy_evidence_interface\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests MCP route access and preflight through Drupal's HTTP kernel.
 *
 * @group policy_evidence_interface
 * @runTestsInSeparateProcesses
 */
#[RunTestsInSeparateProcesses]
final class McpEndpointRoutingTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'policy_evidence_interface'];

  /**
   * Tests the routed preflight response for an anonymous request.
   */
  public function testPreflight(): void {
    $route = $this->container->get('router.route_provider')
      ->getRouteByName('policy_evidence_interface.endpoint');
    $this->assertSame('/_mcp', $route->getPath());

    $request = Request::create('https://example.test/_mcp', 'OPTIONS');
    $request->headers->set('Origin', 'https://client.example.test');
    $request->headers->set('Access-Control-Request-Method', 'POST');
    $request->headers->set('Access-Control-Request-Headers', 'authorization,content-type');

    $response = $this->container->get('http_kernel')->handle($request);

    $this->assertSame(204, $response->getStatusCode());
    $this->assertSame('', $response->getContent());
    $this->assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
    $this->assertSame('GET, POST, OPTIONS', $response->headers->get('Access-Control-Allow-Methods'));
    $this->assertSame('Content-Type, Authorization, Mcp-Session-Id', $response->headers->get('Access-Control-Allow-Headers'));
  }

  /**
   * Tests the route denies an anonymous POST without its permission.
   */
  public function testAnonymousPost(): void {
    $request = Request::create(
      'https://example.test/_mcp',
      'POST',
      [],
      [],
      [],
      [],
      '{"jsonrpc":"2.0","id":7,"method":"initialize"}',
    );
    $request->headers->set('Content-Type', 'application/json');

    $response = $this->container->get('http_kernel')->handle($request);

    $this->assertSame(403, $response->getStatusCode());
  }

  /**
   * Tests MCP CORS headers are not added to unrelated preflight requests.
   */
  public function testUnrelatedPreflight(): void {
    $request = Request::create('https://example.test/other', 'OPTIONS');
    $response = $this->container->get('http_kernel')->handle($request);

    $this->assertNull($response->headers->get('Access-Control-Allow-Origin'));
  }

}
