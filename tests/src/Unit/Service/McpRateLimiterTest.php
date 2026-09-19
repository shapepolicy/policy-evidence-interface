<?php

declare(strict_types=1);

namespace Drupal\Tests\policy_evidence_interface\Unit\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\policy_evidence_interface\Service\McpRateLimiter;
use PHPUnit\Framework\TestCase;

/**
 * Tests MCP rate-limit windows.
 */
final class McpRateLimiterTest extends TestCase {

  /**
   * Tests that a window expires during a long-running request.
   */
  public function testWindowUsesCurrentTime(): void {
    $current_time = 1000;
    $cache_items = [];

    $cache = $this->createMock(CacheBackendInterface::class);
    $cache->method('get')->willReturnCallback(
      static function (string $key, bool $allow_invalid = FALSE) use (&$cache_items): object|false {
        if (!array_key_exists($key, $cache_items)) {
          return FALSE;
        }

        return (object) ['data' => $cache_items[$key]];
      },
    );
    $cache->method('set')->willReturnCallback(
      static function (
        string $key,
        mixed $data,
        int $expire = Cache::PERMANENT,
        array $tags = [],
      ) use (&$cache_items): void {
        $cache_items[$key] = $data;
      },
    );

    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1000);
    $time->method('getCurrentTime')->willReturnCallback(
      static function () use (&$current_time): int {
        return $current_time;
      },
    );

    $limiter = new McpRateLimiter($cache, $time);

    for ($call = 0; $call < 5; $call++) {
      $this->assertTrue($limiter->check('stdio', 'get_node')['allowed']);
    }

    $current_time = 1020;

    $limited = $limiter->check('stdio', 'get_node');
    $this->assertFalse($limited['allowed']);
    $this->assertSame(40, $limited['retry_after']);

    $current_time = 1060;

    $this->assertTrue($limiter->check('stdio', 'get_node')['allowed']);

    $current_time = 2000;

    $this->assertTrue($limiter->check('search-client', 'search_nodes')['allowed']);
    $this->assertTrue($limiter->check('search-client', 'search_nodes')['allowed']);

    $current_time = 2020;

    $limited = $limiter->check('search-client', 'search_nodes');
    $this->assertFalse($limited['allowed']);
    $this->assertSame('Rate limit exceeded for tool "search_nodes".', $limited['message']);
    $this->assertSame(40, $limited['retry_after']);

    $current_time = 2060;

    $this->assertTrue($limiter->check('search-client', 'search_nodes')['allowed']);
  }

}
