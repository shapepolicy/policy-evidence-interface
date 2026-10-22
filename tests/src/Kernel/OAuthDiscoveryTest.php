<?php

declare(strict_types=1);

namespace Drupal\Tests\policy_evidence_interface\Kernel;

use Drupal\consumers\Entity\Consumer;
use Drupal\policy_evidence_interface\Controller\McpServerController;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the OAuth discovery and client ID endpoints MCP clients call first.
 *
 * @group policy_evidence_interface
 */
#[Group('policy_evidence_interface')]
#[RunTestsInSeparateProcesses]
final class OAuthDiscoveryTest extends PolicyEvidenceKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['image', 'consumers'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('consumer');
  }

  /**
   * Returns the controller.
   */
  private function controller(): McpServerController {
    return McpServerController::create($this->container);
  }

  /**
   * Tests /.well-known/oauth-protected-resource.
   */
  public function testResourceMetadata(): void {
    $request = Request::create('https://policy.example.org/.well-known/oauth-protected-resource');

    $data = json_decode($this->controller()->getResourceMetadata($request)->getContent(), TRUE);

    $this->assertSame([
      'resource' => 'https://policy.example.org/_mcp',
      'authorization_servers' => ['https://policy.example.org'],
      'scopes_supported' => ['mcp_connector_scope'],
      'bearer_methods_supported' => ['header'],
    ], $data);
  }

  /**
   * Tests /.well-known/oauth-authorization-server, including PKCE support.
   */
  public function testAuthorizationServerMetadata(): void {
    $request = Request::create('https://policy.example.org/.well-known/oauth-authorization-server');

    $data = json_decode($this->controller()->getAuthMetadata($request)->getContent(), TRUE);

    $this->assertSame('https://policy.example.org', $data['issuer']);
    $this->assertSame('https://policy.example.org/oauth/authorize', $data['authorization_endpoint']);
    $this->assertSame('https://policy.example.org/oauth/token', $data['token_endpoint']);
    $this->assertSame('https://policy.example.org/oauth/getclientid', $data['registration_endpoint']);
    $this->assertSame(['S256'], $data['code_challenge_methods_supported']);
    $this->assertSame(['none'], $data['token_endpoint_auth_methods_supported']);
  }

  /**
   * Tests /oauth/getclientid when no "mcp connector" consumer exists.
   */
  public function testClientIdWithoutConsumer(): void {
    $response = $this->controller()->mockedRegister(Request::create('/oauth/getclientid', 'POST'));

    $this->assertSame(400, $response->getStatusCode());
    $this->assertSame('invalid_client_metadata', json_decode($response->getContent(), TRUE)['error']);
  }

  /**
   * Tests that /oauth/getclientid returns the "mcp connector" client ID.
   */
  public function testClientIdFromConsumer(): void {
    Consumer::create(['label' => 'mcp connector', 'client_id' => 'pei-client'])->save();

    $response = $this->controller()->mockedRegister(Request::create('/oauth/getclientid', 'POST'));

    $this->assertSame(201, $response->getStatusCode());
    $this->assertSame('pei-client', json_decode($response->getContent(), TRUE)['client_id']);
    $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
  }

}
