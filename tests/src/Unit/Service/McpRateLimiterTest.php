<?php

declare(strict_types=1);

namespace Drupal\Tests\policy_evidence_interface\Unit\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\policy_evidence_interface\Service\McpRateLimiter;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the MCP rate limiter against its module configuration.
 *
 * The cache is an in-memory array and the clock is a variable, so each test
 * controls exactly how many calls were made and what time it is.
 *
 * @group policy_evidence_interface
 */
#[Group('policy_evidence_interface')]
final class McpRateLimiterTest extends UnitTestCase {

  /**
   * Cached counters with their expire time, keyed by cache ID.
   */
  private array $cacheItems = [];

  /**
   * The time returned by the mocked time service.
   */
  private int $now = 1000;

  /**
   * Builds a limiter that reads the given 'rate_limit' configuration.
   */
  private function createLimiter(array $rate_limit): McpRateLimiter {
    // Like Drupal's cache backends, an item is gone once its expire time has
    // passed, unless it is permanent. So the tests do not depend on how the
    // limiter checks expiry.
    $cache = $this->createMock(CacheBackendInterface::class);
    $cache->method('get')->willReturnCallback(
      function (string $cid): object|false {
        $item = $this->cacheItems[$cid] ?? NULL;
        $valid = $item && ($item['expire'] === CacheBackendInterface::CACHE_PERMANENT || $item['expire'] >= $this->now);
        return $valid ? (object) ['data' => $item['data']] : FALSE;
      },
    );
    $cache->method('set')->willReturnCallback(
      function (string $cid, mixed $data, int $expire): void {
        $this->cacheItems[$cid] = ['data' => $data, 'expire' => $expire];
      },
    );

    // Both clock methods return the same time, so these tests still pass if
    // the limiter switches to getCurrentTime() (see the unit KnownIssuesTest).
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturnCallback(fn (): int => $this->now);
    $time->method('getCurrentTime')->willReturnCallback(fn (): int => $this->now);

    $config_factory = $this->getConfigFactoryStub([
      'policy_evidence_interface.settings' => ['rate_limit' => $rate_limit],
    ]);

    return new McpRateLimiter($cache, $time, $config_factory);
  }

  /**
   * Returns an enabled configuration with a 60-second window.
   *
   * The given limits apply globally and to get_node; search_nodes allows 10.
   */
  private function enabledConfig(int $global_limit, int $tool_limit): array {
    return [
      'enabled' => TRUE,
      'global' => ['limit' => $global_limit, 'window_seconds' => 60],
      'tools' => [
        'get_node' => ['limit' => $tool_limit],
        'search_nodes' => ['limit' => 10],
      ],
    ];
  }

  /**
   * Tests that unticking "Enable rate limiting" blocks every tool call.
   */
  public function testDisabledSettingBlocksAllTools(): void {
    $limiter = $this->createLimiter(['enabled' => FALSE] + $this->enabledConfig(10, 10));

    $result = $limiter->check('user:1', 'get_node');

    $this->assertSame(
      ['allowed' => FALSE, 'message' => 'All tools are disabled', 'retry_after' => 'INFINITE'],
      $result,
    );
  }

  /**
   * Tests that missing rate-limit configuration also blocks every tool call.
   */
  public function testMissingConfigurationBlocksAllTools(): void {
    $limiter = $this->createLimiter([]);

    $this->assertFalse($limiter->check('user:1', 'get_node')['allowed']);
  }

  /**
   * Tests that a global limit of 0 disables all tools.
   */
  public function testGlobalLimitZeroBlocksAllTools(): void {
    $limiter = $this->createLimiter($this->enabledConfig(0, 10));

    $result = $limiter->check('user:1', 'get_node');

    $this->assertFalse($result['allowed']);
    $this->assertSame('All tools are disabled via global rate limiter = 0', $result['message']);
  }

  /**
   * Tests that a tool limit of 0 disables that tool.
   */
  public function testToolLimitZeroBlocksTool(): void {
    $limiter = $this->createLimiter($this->enabledConfig(10, 0));

    $result = $limiter->check('user:1', 'get_node');

    $this->assertFalse($result['allowed']);
    $this->assertStringContainsString('"get_node" is disabled', $result['message']);
  }

  /**
   * Tests that a tool without its own limit (such as a new tool) is blocked.
   */
  public function testToolWithoutConfiguredLimitIsBlocked(): void {
    $limiter = $this->createLimiter($this->enabledConfig(10, 10));

    $result = $limiter->check('user:1', 'list_content_types_sub');

    $this->assertFalse($result['allowed']);
    $this->assertStringContainsString('"list_content_types_sub" is disabled', $result['message']);
  }

  /**
   * Tests that a call under both limits is allowed.
   */
  public function testCallUnderLimitsIsAllowed(): void {
    $limiter = $this->createLimiter($this->enabledConfig(10, 10));

    $result = $limiter->check('user:1', 'get_node');

    $this->assertSame(['allowed' => TRUE, 'message' => '', 'retry_after' => 0], $result);
  }

  /**
   * Tests the global limit boundary: call N is allowed, call N + 1 is not.
   */
  public function testGlobalLimitBoundary(): void {
    $limiter = $this->createLimiter($this->enabledConfig(3, 10));

    for ($call = 1; $call <= 3; $call++) {
      $this->assertTrue($limiter->check('user:1', 'get_node')['allowed'], "Call $call should be allowed.");
    }
    $result = $limiter->check('user:1', 'get_node');

    $this->assertFalse($result['allowed']);
    $this->assertSame('Global MCP rate limit exceeded.', $result['message']);
  }

  /**
   * Tests the tool limit boundary when it is lower than the global limit.
   */
  public function testToolLimitBoundary(): void {
    $limiter = $this->createLimiter($this->enabledConfig(10, 2));

    $limiter->check('user:1', 'get_node');
    $limiter->check('user:1', 'get_node');
    $result = $limiter->check('user:1', 'get_node');

    $this->assertFalse($result['allowed']);
    $this->assertSame('Rate limit exceeded for tool "get_node".', $result['message']);
  }

  /**
   * Tests that retry_after is the number of seconds left in the window.
   */
  public function testRetryAfterCountsDownToWindowEnd(): void {
    $limiter = $this->createLimiter($this->enabledConfig(1, 10));
    $limiter->check('user:1', 'get_node');

    $this->now += 20;
    $result = $limiter->check('user:1', 'get_node');

    $this->assertSame(40, $result['retry_after']);
  }

  /**
   * Tests that the global limit is shared by all tools for one client.
   */
  public function testGlobalLimitIsSharedAcrossTools(): void {
    $limiter = $this->createLimiter($this->enabledConfig(2, 10));

    $limiter->check('user:1', 'get_node');
    $limiter->check('user:1', 'search_nodes');

    $this->assertFalse($limiter->check('user:1', 'search_nodes')['allowed']);
  }

  /**
   * Tests that each client has its own counters.
   */
  public function testClientsAreCountedSeparately(): void {
    $limiter = $this->createLimiter($this->enabledConfig(1, 10));
    $limiter->check('user:1', 'get_node');

    $this->assertTrue($limiter->check('user:2', 'get_node')['allowed']);
  }

  /**
   * Tests the window boundary: blocked 1 second before the end, reset at it.
   */
  public function testCountersResetWhenWindowEnds(): void {
    $limiter = $this->createLimiter($this->enabledConfig(1, 10));
    $limiter->check('user:1', 'get_node');

    $this->now += 59;
    $this->assertFalse($limiter->check('user:1', 'get_node')['allowed']);

    $this->now += 1;
    $this->assertTrue($limiter->check('user:1', 'get_node')['allowed']);
  }

  /**
   * Tests that a denied call does not use up the client's allowance.
   */
  public function testDeniedCallIsNotCounted(): void {
    $limiter = $this->createLimiter($this->enabledConfig(2, 1));
    $limiter->check('user:1', 'get_node');
    // The second get_node call is denied by its tool limit of 1.
    $limiter->check('user:1', 'get_node');

    // Had the denied call counted, the global limit of 2 would be used up.
    $this->assertTrue($limiter->check('user:1', 'search_nodes')['allowed']);
  }

  /**
   * Tests that an unexpected value in the cache is treated as a new counter.
   */
  public function testInvalidCachedCounterIsReset(): void {
    $limiter = $this->createLimiter($this->enabledConfig(1, 10));
    $limiter->check('user:1', 'get_node');
    foreach (array_keys($this->cacheItems) as $cid) {
      $this->cacheItems[$cid]['data'] = 'not an array';
    }

    $this->assertTrue($limiter->check('user:1', 'get_node')['allowed']);
  }

}
