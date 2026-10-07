<?php

namespace Drupal\policy_evidence_interface\Plugin\McpTool;

use Drupal\policy_evidence_interface\Plugin\McpToolBase;

/**
 * Updates policy tags for a Drupal node.
 *
 * @McpTool(
 *   id = "set_node_tags",
 *   tool_name = "set_node_tags",
 *   description = "Updates the policy tags assigned to a Drupal node using existing Policy Tags taxonomy term IDs.",
 *   rate_limit = {
 *     "limit" = 20,
 *     "window" = 60
 *   }
 * )
 */
class SetNodeTags extends McpToolBase {

  /**
   * {@inheritdoc}
   */
  protected function inputSchema(): array {
    return [
      'type' => 'object',
      'properties' => [
        'nid' => [
          'type' => 'integer',
          'description' => 'The numeric node ID (nid) to update.',
        ],
        'tag_ids' => [
          'type' => 'array',
          'description' => 'List of existing Policy Tags taxonomy term IDs.',
          'items' => [
            'type' => 'integer',
          ],
        ],
      ],
      'required' => ['nid', 'tag_ids'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $arguments): mixed {
    $nid = (int) ($arguments['nid'] ?? 0);
    $tag_ids = array_values(array_unique(array_map('intval', $arguments['tag_ids'] ?? [])));

    if (!$nid) {
      return ['error' => 'A valid nid is required.'];
    }

    /** @var \Drupal\node\NodeInterface|null $node */
    $node = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->load($nid);

    if (!$node) {
      return ['error' => "Node {$nid} not found."];
    }

    if (!$node->access('update')) {
      return ['error' => "You do not have permission to update node {$nid}."];
    }

    if (!$node->hasField('field_policy_tags')) {
      return ['error' => "Node {$nid} does not support policy tags."];
    }

    $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

    $valid_tag_ids = [];
    $tag_names = [];

    if ($tag_ids) {
      $terms = $term_storage->loadMultiple($tag_ids);

      foreach ($tag_ids as $tag_id) {
        if (!isset($terms[$tag_id])) {
          return ['error' => "Policy tag {$tag_id} not found."];
        }

        $term = $terms[$tag_id];

        if ($term->bundle() !== 'policy_tags') {
          return ['error' => "Taxonomy term {$tag_id} is not a Policy Tag."];
        }

        $valid_tag_ids[] = [
          'target_id' => $tag_id,
        ];

        $tag_names[] = $term->label();
      }
    }

    $node->set('field_policy_tags', $valid_tag_ids);
    $node->save();

    return [
      'nid' => (int) $node->id(),
      'title' => $node->label(),
      'tags' => $tag_names,
    ];
  }

}