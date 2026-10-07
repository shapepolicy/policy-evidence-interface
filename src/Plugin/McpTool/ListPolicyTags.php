<?php

namespace Drupal\policy_evidence_interface\Plugin\McpTool;

use Drupal\policy_evidence_interface\Plugin\McpToolBase;

/**
 * Lists available policy tags.
 *
 * @McpTool(
 *   id = "list_policy_tags",
 *   tool_name = "list_policy_tags",
 *   description = "Returns the existing tags available for policy documents.",
 *   rate_limit = {
 *     "limit" = 30,
 *     "window" = 60
 *   }
 * )
 */
class ListPolicyTags extends McpToolBase {

  /**
   * {@inheritdoc}
   */
  protected function inputSchema(): array {
    return [
      'type' => 'object',
      'properties' => [],
      'required' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $arguments): mixed {
    $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

    $tids = $term_storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('vid', 'policy_tags')
      ->sort('name', 'ASC')
      ->execute();

    $terms = $term_storage->loadMultiple($tids);

    $tags = [];
    foreach ($terms as $term) {
      $tags[] = [
        'id' => (int) $term->id(),
        'name' => $term->label(),
      ];
    }

    return [
      'total' => count($tags),
      'tags' => $tags,
    ];
  }

}