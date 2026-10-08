<?php

namespace Drupal\policy_evidence_interface\Plugin\McpTool;

use Drupal\policy_evidence_interface\Plugin\McpToolBase;

/**
 * Searches Drupal nodes by keyword.
 *
 * @McpTool(
 *   id = "search_nodes",
 *   tool_name = "search_nodes",
 *   description = "Search published Drupal nodes by title keyword, optionally filtered by content type. Returns node ID, title, type, URL, and creation date."
 * )
 */
class SearchNodes extends McpToolBase {

  /**
   * {@inheritdoc}
   */
  protected function inputSchema(): array {
    return [
      'type'       => 'object',
      'properties' => [
        'keyword' => [
          'type'        => 'string',
          'description' => 'Keyword to search in node titles.',
        ],
        'content_type' => [
          'type'        => 'string',
          'description' => 'Optional. Filter by content type machine name (e.g. "article", "page").',
        ],
        'limit' => [
          'type'        => 'integer',
          'description' => 'Maximum number of results to return (default 10, max 50).',
          'default'     => 10,
        ],
      ],
      'required' => ['keyword'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $arguments): mixed {
    $keyword      = $arguments['keyword'] ?? '';
    $content_type = $arguments['content_type'] ?? NULL;
    $limit        = min((int) ($arguments['limit'] ?? 10), 50);

    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $etm */
    $etm     = \Drupal::entityTypeManager();
    $storage = $etm->getStorage('node');

    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->condition('title', '%' . $keyword . '%', 'LIKE')
      ->sort('created', 'DESC')
      ->range(0, $limit);

    if ($content_type) {
      $query->condition('type', $content_type);
    }
    //doing a pre filter could have done it in post.
    $config = \Drupal::config('policy_evidence_interface.settings');
    $disallow_tag_ids = $config->get('access_control_tags.disallow_tag_ids');
    // Exclude nodes referencing disallowed taxonomy terms.
    if (!empty($disallow_tag_ids)) {
      // 1. Find all node IDs that have AT LEAST ONE disallowed tag.
      $disallowed_nids = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('field_tags.target_id', $disallow_tag_ids, 'IN')
        ->execute();

      // 2. Exclude those node IDs from the main search.
      if (!empty($disallowed_nids)) {
        $query->condition('nid', $disallowed_nids, 'NOT IN');
      }
    }

    $nids  = $query->execute();
    $nodes = $storage->loadMultiple($nids);

    $results = [];
    foreach ($nodes as $node) {
      $results[] = [
        'nid'          => (int) $node->id(),
        'title'        => $node->label(),
        'type'         => $node->bundle(),
        'url'          => $node->toUrl('canonical', ['absolute' => TRUE])->toString(),
        'created'      => date('c', $node->getCreatedTime()),
        'changed'      => date('c', $node->getChangedTime()),
      ];
    }

    return [
      'total'   => count($results),
      'results' => $results,
    ];
  }

}
