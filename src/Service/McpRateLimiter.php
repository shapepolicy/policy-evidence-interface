<?php

namespace Drupal\policy_evidence_interface\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;

/**
 * Limits how often MCP tools can be called.
 */
final class McpRateLimiter {

  /**
   * One caller can call all MCP tools 5 times per minute.
   */
  private const GLOBAL_LIMIT = 5;

  /**
   * One caller can call search_nodes 2 times per minute.
   */
  private const SEARCH_NODES_LIMIT = 2;

  /**
   * One rate-limit window lasts 60 seconds.
   */
  private const WINDOW_SECONDS = 60;

  public function __construct(
    private readonly CacheBackendInterface $cache,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Checks whether this caller can call this tool.
   */
  public function check(string $clientId, string $toolName): array {
    $clientHash = hash('sha256', $clientId);

    // The calculator
    $globalKey = 'mcp_rate:global:' . $clientHash;

    // Calculator for every tool
    $toolKey = 'mcp_rate:tool:' . $toolName . ':' . $clientHash;

    $globalCounter = $this->getCounter($globalKey);

    // check the global limit 
    if ($globalCounter['count'] >= self::GLOBAL_LIMIT) {
      return [
        'allowed' => FALSE,
        'message' => 'Global MCP rate limit exceeded.',
        'retry_after' => max(0, $globalCounter['expires'] - $this->time->getRequestTime()),
      ];
    }

    // search_nodes single limit is 2 times
    $toolLimit = $toolName === 'search_nodes'
      ? self::SEARCH_NODES_LIMIT
      : self::GLOBAL_LIMIT;

    $toolCounter = $this->getCounter($toolKey);

    // check the tool limit
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
   * Reads a counter or creates a new one.
   */
  private function getCounter(string $key): array {
    $now = $this->time->getRequestTime();
    $cached = $this->cache->get($key);


    if (
      !$cached ||
      !is_array($cached->data) ||
      ($cached->data['expires'] ?? 0) <= $now
    ) {
      return [
        'count' => 0,
        'expires' => $now + self::WINDOW_SECONDS,
      ];
    }

    return $cached->data;
  }

  /**
   * Saves the counter.
   */
  private function saveCounter(string $key, array $counter): void {
    $this->cache->set(
      $key,
      $counter,
      $counter['expires'],
    );
  }

}