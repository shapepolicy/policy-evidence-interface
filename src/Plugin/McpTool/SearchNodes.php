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
          'minLength'   => 1,
          'description' => 'Keyword to search in node titles.',
        ],
        'content_type' => [
          'type'        => 'string',
          'description' => 'Optional. Filter by content type machine name (e.g. "article", "page").',
        ],
        'limit' => [
          'type'        => 'integer',
          'minimum'     => 1,
          'maximum'     => 50,
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
    $keyword = $arguments['keyword'] ?? NULL;
    $content_type = array_key_exists('content_type', $arguments)
      ? $arguments['content_type']
      : NULL;
    $limit = array_key_exists('limit', $arguments)
      ? $arguments['limit']
      : 10;

    if (!is_string($keyword) || $keyword === '') {
      return ['error' => 'A valid keyword is required.'];
    }

    if (array_key_exists('content_type', $arguments) && !is_string($content_type)) {
      return ['error' => 'content_type must be a string.'];
    }

    if (!is_int($limit) || $limit < 1 || $limit > 50) {
      return ['error' => 'limit must be an integer between 1 and 50.'];
    }

    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $etm */
    $etm     = \Drupal::entityTypeManager();
    $storage = $etm->getStorage('node');

    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->condition('title', '%' . $keyword . '%', 'LIKE')
      ->sort('created', 'DESC')
      ->range(0, $limit);

    if ($content_type !== NULL && $content_type !== '') {
      $query->condition('type', $content_type);
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
