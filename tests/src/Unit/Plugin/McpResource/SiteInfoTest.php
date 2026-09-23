<?php

declare(strict_types=1);

namespace Drupal\Tests\policy_evidence_interface\Unit\Plugin\McpResource;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\policy_evidence_interface\Plugin\McpResource\SiteInfo;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests the SiteInfo MCP resource.
 */
final class SiteInfoTest extends TestCase {

  /**
   * Tests configured site information is returned as a resource.
   */
  public function testReadReturnsSiteInformation(): void {
    $result = $this->readResource([
      'name' => 'Policy Evidence',
      'slogan' => 'Evidence for better policy',
      'mail' => 'admin@example.test',
      'default_langcode' => 'en-au',
      'page.front' => '/research',
    ], 'https://example.test/path');

    $this->assertSame('drupal://site-info', $result['uri']);
    $this->assertSame('application/json', $result['mimeType']);
    $this->assertSame([
      'site_name' => 'Policy Evidence',
      'slogan' => 'Evidence for better policy',
      'mail' => 'admin@example.test',
      'base_url' => 'https://example.test',
      'drupal_version' => \Drupal::VERSION,
      'default_langcode' => 'en-au',
      'front_page' => '/research',
    ], json_decode($result['text'], TRUE, 512, JSON_THROW_ON_ERROR));
  }

  /**
   * Tests fallback values for missing site configuration.
   */
  public function testReadUsesDefaults(): void {
    $result = $this->readResource([], 'http://localhost');

    $this->assertSame([
      'site_name' => '',
      'slogan' => '',
      'mail' => '',
      'base_url' => 'http://localhost',
      'drupal_version' => \Drupal::VERSION,
      'default_langcode' => 'en',
      'front_page' => '/node',
    ], json_decode($result['text'], TRUE, 512, JSON_THROW_ON_ERROR));
  }

  /**
   * Reads the resource with mocked Drupal services.
   */
  private function readResource(array $values, string $url): array {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnCallback(
      static fn (string $key): mixed => $values[$key] ?? NULL,
    );

    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')
      ->with('system.site')
      ->willReturn($config);

    $request_stack = new RequestStack();
    $request_stack->push(Request::create($url));

    $container = new ContainerBuilder();
    $container->set('config.factory', $config_factory);
    $container->set('request_stack', $request_stack);
    $original_container = \Drupal::hasContainer() ? \Drupal::getContainer() : NULL;
    \Drupal::setContainer($container);

    try {
      $resource = new SiteInfo([], 'site_info', []);
      return $resource->read();
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
