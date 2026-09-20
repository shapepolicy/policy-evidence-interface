<?php

declare(strict_types=1);

namespace Drupal\Tests\policy_evidence_interface\Unit\Plugin\McpTool;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\policy_evidence_interface\Plugin\McpTool\GetSiteInfo;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests the GetSiteInfo MCP tool.
 */
final class GetSiteInfoTest extends TestCase {

  /**
   * Tests the empty input schema.
   */
  public function testInputSchema(): void {
    $tool = new GetSiteInfo([], 'get_site_info', []);
    $schema = $tool->getToolDefinition()['inputSchema'];

    $this->assertSame('object', $schema['type']);
    $this->assertInstanceOf(\stdClass::class, $schema['properties']);
    $this->assertSame([], (array) $schema['properties']);
    $this->assertSame([], $schema['required']);
  }

  /**
   * Tests configured site information is returned.
   */
  public function testExecuteReturnsSiteInformation(): void {
    $result = $this->executeTool([
      'name' => 'Policy Evidence',
      'slogan' => 'Evidence for better policy',
      'mail' => 'admin@example.test',
      'default_langcode' => 'en-au',
    ], 'https://example.test/path');

    $this->assertSame([
      'site_name' => 'Policy Evidence',
      'slogan' => 'Evidence for better policy',
      'mail' => 'admin@example.test',
      'base_url' => 'https://example.test',
      'drupal_version' => \Drupal::VERSION,
      'default_langcode' => 'en-au',
    ], $result);
  }

  /**
   * Tests fallback values for missing site configuration.
   */
  public function testExecuteUsesDefaults(): void {
    $result = $this->executeTool([], 'http://localhost');

    $this->assertSame('', $result['site_name']);
    $this->assertSame('', $result['slogan']);
    $this->assertSame('', $result['mail']);
    $this->assertSame('en', $result['default_langcode']);
  }

  /**
   * Executes the tool with mocked Drupal services.
   */
  private function executeTool(array $values, string $url): array {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnCallback(
      static fn (string $key): mixed => $values[$key] ?? NULL,
    );

    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')
      ->with('system.site')
      ->willReturn($config);

    $request_stack = new RequestStack([Request::create($url)]);

    $container = new ContainerBuilder();
    $container->set('config.factory', $config_factory);
    $container->set('request_stack', $request_stack);
    \Drupal::setContainer($container);

    try {
      $tool = new GetSiteInfo([], 'get_site_info', []);
      return $tool->execute([]);
    }
    finally {
      \Drupal::unsetContainer();
    }
  }

}
