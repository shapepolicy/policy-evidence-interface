<?php

declare(strict_types=1);

namespace Drupal\Tests\policy_evidence_interface\Unit\EventSubscriber;

use Drupal\policy_evidence_interface\EventSubscriber\McpPreflightResponseSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Tests the CORS headers added to MCP preflight responses.
 */
final class McpPreflightResponseSubscriberTest extends TestCase {

  /**
   * Tests preflight headers without changing Drupal's response status or Allow.
   */
  public function testMcpPreflight(): void {
    $request = Request::create('https://example.test/_mcp', 'OPTIONS');
    $request->headers->set('Origin', 'https://client.example.test');
    $request->headers->set('Access-Control-Request-Method', 'POST');
    $response = new Response('', 200, ['Allow' => 'GET, POST, OPTIONS']);
    $event = new ResponseEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response);

    (new McpPreflightResponseSubscriber())->onResponse($event);

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('GET, POST, OPTIONS', $response->headers->get('Allow'));
    $this->assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
    $this->assertSame('GET, POST, OPTIONS', $response->headers->get('Access-Control-Allow-Methods'));
    $this->assertSame('Content-Type, Authorization, Mcp-Session-Id', $response->headers->get('Access-Control-Allow-Headers'));
  }

  /**
   * Tests that other OPTIONS responses do not receive MCP CORS headers.
   */
  public function testUnrelatedOptions(): void {
    $request = Request::create('https://example.test/other', 'OPTIONS');
    $request->headers->set('Origin', 'https://client.example.test');
    $request->headers->set('Access-Control-Request-Method', 'POST');
    $response = new Response('', 200);
    $event = new ResponseEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response);

    (new McpPreflightResponseSubscriber())->onResponse($event);

    $this->assertNull($response->headers->get('Access-Control-Allow-Origin'));
  }

}
