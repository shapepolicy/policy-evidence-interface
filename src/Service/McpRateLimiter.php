<?php

namespace Drupal\policy_evidence_interface\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Limits how often MCP tools can be called using dynamic module configuration.
 */
final class McpRateLimiter {

  public function __construct(
    private readonly CacheBackendInterface $cache,
    private readonly TimeInterface $time,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Checks whether this caller can call this tool.
   */
  public function check(string $clientId, string $toolName): array {
    
    $config = $this->configFactory->get('policy_evidence_interface.settings');
    $rateLimitConfig = $config->get('rate_limit') ?? [];

    // 1. Check if rate limiting is enabled globally.
    if (!($rateLimitConfig['enabled'] ?? FALSE)) {
      return [
        'allowed' => False,
        'message' => 'All tools are disabled',
        'retry_after' => "INFINITE",
      ];
    }

    $windowSeconds = (int) ($rateLimitConfig['global']['window_seconds'] ?? 9999);
    $globalLimit = (int) ($rateLimitConfig['global']['limit'] ?? 0);

    $clientHash = hash('sha256', $clientId);
    $globalKey = 'mcp_rate:global:' . $clientHash;
    $toolKey = 'mcp_rate:tool:' . $toolName . ':' . $clientHash;

    // 2. Check global rate limit.
    $globalCounter = $this->getCounter($globalKey, $windowSeconds);
    if($globalLimit == 0){
      return [
        'allowed' => FALSE,
        'message' => 'All tools are disabled via global rate limiter = 0',
        'retry_after' => "INFINITE",
      ];
    } 
    if ($globalCounter['count'] >= $globalLimit) {
      return [
        'allowed' => FALSE,
        'message' => 'Global MCP rate limit exceeded.',
        'retry_after' => max(0, $globalCounter['expires'] - $this->time->getRequestTime()),
      ];
    }

    // 3. Resolve tool-specific limit (falls back to global limit if not explicitly defined).
    $toolLimit = (int) ($rateLimitConfig['tools'][$toolName]['limit'] ?? 0);

    $toolCounter = $this->getCounter($toolKey, $windowSeconds);
    if ($toolLimit == 0) {
      return [
        'allowed' => FALSE,
        'message' => sprintf(
          'Tool "%s" is disabled via via tool rate limiter = 0.',
          $toolName,
        ),
        'retry_after' => "INFINITE",
      ];
    }
    if ($toolCounter['count'] >= $toolLimit) {
      return [
        'allowed' => FALSE,
        'message' => sprintf(
          'Rate limit exceeded for tool "%s".',
          $toolName,
        ),
        'retry_after' => max(0, $toolCounter['expires'] - $this->time->getRequestTime()),
      ];
    }

    // Increment and store updated counters.
    $globalCounter['count']++;
    $toolCounter['count']++;

    $this->saveCounter($globalKey, $globalCounter);
    $this->saveCounter($toolKey, $toolCounter);

    return [
      'allowed' => TRUE,
      'message' => '',
      'retry_after' => 0,
    ];
  }

  /**
   * Reads a counter or creates a new one using the configured window duration.
   */
  private function getCounter(string $key, int $windowSeconds): array {
    $now = $this->time->getRequestTime();
    $cached = $this->cache->get($key);

    if (
      !$cached ||
      !is_array($cached->data) ||
      ($cached->data['expires'] ?? 0) <= $now
    ) {
      return [
        'count' => 0,
        'expires' => $now + $windowSeconds,
      ];
    }

    return $cached->data;
  }

  /**
   * Saves the counter in Drupal cache.
   */
  private function saveCounter(string $key, array $counter): void {
    $this->cache->set(
      $key,
      $counter,
      $counter['expires'],
    );
  }

}