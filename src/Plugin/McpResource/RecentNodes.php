<?php

namespace Drupal\policy_evidence_interface\Plugin\McpResource;

use Drupal\policy_evidence_interface\Plugin\McpResourceBase;

/**
 * Exposes the 20 most recently updated nodes as an MCP resource.
 *
 * @McpResource(
 *   id = "recent_nodes",
 *   uri = "drupal://recent-nodes",
 *   name = "Recent Nodes",
 *   description = "The 20 most recently updated published nodes on this Drupal site, including nid, title, type, URL, and dates.",
 *   mimeType = "application/json"
 * )
 */
class RecentNodes extends McpResourceBase {

  /**
   * {@inheritdoc}
   */
  public function read(): array {
    $storage = \Drupal::entityTypeManager()->getStorage('node');

    $nids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->sort('changed', 'DESC')
      ->range(0, 20)
      ->execute();

    $nodes   = $storage->loadMultiple($nids);
    $results = [];

    foreach ($nodes as $node) {
      $results[] = [
        'nid'     => (int) $node->id(),
        'title'   => $node->label(),
        'type'    => $node->bundle(),
        'url'     => $node->toUrl('canonical', ['absolute' => TRUE])->toString(),
        'created' => date('c', $node->getCreatedTime()),
        'changed' => date('c', $node->getChangedTime()),
      ];
    }

    return [
      'uri'      => 'drupal://recent-nodes',
      'mimeType' => 'application/json',
      'text'     => json_encode(['nodes' => $results, 'count' => count($results)], JSON_PRETTY_PRINT),
    ];
  }

}
