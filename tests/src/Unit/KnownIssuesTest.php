<?php

declare(strict_types=1);

namespace Drupal\Tests\policy_evidence_interface\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\policy_evidence_interface\Service\McpRateLimiter;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Bugs found while writing the unit tests. These tests fail until fixed.
 *
 * See the kernel KnownIssuesTest for how this group is used.
 *
 * @group policy_evidence_interface_known_issue
 */
#[Group('policy_evidence_interface_known_issue')]
final class KnownIssuesTest extends UnitTestCase {

  /**
   * When the stdio process started; its request time stays at this value.
   */
  private const START = 1000;

  /**
   * Cached counters with their expire time, keyed by cache ID.
   */
  private array $cacheItems = [];

  /**
   * The current time returned by the mocked clock.
   */
  private int $currentTime = self::START;

  /**
   * Tests that the rate-limit window ends in a long-running stdio session.
   *
   * The stdio server (drush mcp:server) is one PHP process, so the "request
   * time" stays at the moment it started. The limiter only reads the request
   * time, so once a limit is reached the session stays blocked until it is
   * restarted. Issue #24 fixed this in PR #25 by reading getCurrentTime()
   * instead, which is what this test expects; rollback ab95cc0 removed the fix.
   */
  public function testWindowEndsInLongRunningProcess(): void {
    // Like Drupal's cache backends, an item expires against the request time,
    // which in this process stays at START, unless it is permanent.
    $cache = $this->createMock(CacheBackendInterface::class);
    $cache->method('get')->willReturnCallback(
      function (string $cid): object|false {
        $item = $this->cacheItems[$cid] ?? NULL;
        $valid = $item && ($item['expire'] === CacheBackendInterface::CACHE_PERMANENT || $item['expire'] >= self::START);
        return $valid ? (object) ['data' => $item['data']] : FALSE;
      },
    );
    $cache->method('set')->willReturnCallback(
      function (string $cid, mixed $data, int $expire): void {
        $this->cacheItems[$cid] = ['data' => $data, 'expire' => $expire];
      },
    );

    // The clock moves on; the request time does not.
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(self::START);
    $time->method('getCurrentTime')->willReturnCallback(fn (): int => $this->currentTime);

    $config_factory = $this->getConfigFactoryStub([
      'policy_evidence_interface.settings' => [
        'rate_limit' => [
          'enabled' => TRUE,
          'global' => ['limit' => 1, 'window_seconds' => 60],
          'tools' => ['get_node' => ['limit' => 1]],
        ],
      ],
    ]);
    $limiter = new McpRateLimiter($cache, $time, $config_factory);
    $limiter->check('stdio', 'get_node');

    // 61 seconds later the 60-second window is over.
    $this->currentTime = self::START + 61;

    $this->assertTrue(
      $limiter->check('stdio', 'get_node')['allowed'],
      'The call should be allowed again after the 60-second window.',
    );
  }

}
