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
 *   rate_limit = {
 *     "limit" = 10,
 *     "window" = 60
 * }
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
          'description' => 'Optional. Keyword to search in node titles.',
        ],
        'content_type' => [
          'type'        => 'string',
          'description' => 'Optional. Filter by content type machine name (e.g. "article", "page").',
        ],
        'tag' => [
          'type'        => 'string',
          'description' => 'Optional. Filter nodes by policy tag name.',
        ],
        'limit' => [
          'type'        => 'integer',
          'description' => 'Maximum number of results to return (default 10, max 50).',
          'default'     => 10,
        ],
      ],
      'required' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $arguments): mixed {
    $keyword      = $arguments['keyword'] ?? '';
    $content_type = $arguments['content_type'] ?? NULL;
    $tag          = $arguments['tag'] ?? NULL;
    $limit        = min((int) ($arguments['limit'] ?? 10), 50);

    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $etm */
    $etm     = \Drupal::entityTypeManager();
    $storage = $etm->getStorage('node');

    // Resolve the tag name to taxonomy term IDs.
    $tag_ids = [];
    if ($tag) {
      $term_storage = $etm->getStorage('taxonomy_term');
      $tag_ids = $term_storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('vid', 'policy_tags')
        ->condition('name', $tag)
        ->execute();

      // If the requested tag does not exist, there are no matching nodes.
      if (!$tag_ids) {
        return [
          'total' => 0,
          'results' => [],
        ];
      }
    }

    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->sort('created', 'DESC')
      ->range(0, $limit);

    if ($keyword !== '') {
      $query->condition('title', '%' . $keyword . '%', 'LIKE');
    }

    if ($content_type) {
      $query->condition('type', $content_type);
    }
    if ($tag_ids) {
      $query->condition('field_policy_tags.target_id', array_values($tag_ids), 'IN');
    }

    $nids  = $query->execute();
    $nodes = $storage->loadMultiple($nids);

    $results = [];
    foreach ($nodes as $node) {
      // Extract policy tag names from the taxonomy reference field.
      $tags = [];
      if ($node->hasField('field_policy_tags') && !$node->get('field_policy_tags')->isEmpty()) {
        foreach ($node->get('field_policy_tags')->referencedEntities() as $term) {
          $tags[] = $term->label();
        }
      }

      $results[] = [
        'nid'          => (int) $node->id(),
        'title'        => $node->label(),
        'type'         => $node->bundle(),
        'tags'         => $tags,
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
