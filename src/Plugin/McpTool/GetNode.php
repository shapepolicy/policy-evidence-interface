<?php

namespace Drupal\policy_evidence_interface\Plugin\McpTool;

use Drupal\policy_evidence_interface\Plugin\McpToolBase;

/**
 * Fetches a single Drupal node by its ID.
 *
 * @McpTool(
 *   id = "get_node",
 *   tool_name = "get_node",
 *   description = "Fetch the full details of a single published Drupal node by its numeric node ID (nid), including title, body, content type, author, and field values."
 * )
 */
class GetNode extends McpToolBase {

  /**
   * {@inheritdoc}
   */
  protected function inputSchema(): array {
    return [
      'type'       => 'object',
      'properties' => [
        'nid' => [
          'type'        => 'integer',
          'description' => 'The numeric node ID (nid) to fetch.',
        ],
      ],
      'required' => ['nid'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $arguments): mixed {
    $nid = (int) ($arguments['nid'] ?? 0);

    if (!$nid) {
      return ['error' => 'A valid nid is required.'];
    }

    /** @var \Drupal\node\NodeInterface|null $node */
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);

    if (!$node) {
      return ['error' => "Node {$nid} not found."];
    }

    if (!$node->isPublished()) {
      return ['error' => "Node {$nid} is not published."];
    }

    // is dissallowed taxonimy tag check
    $terms = $node->get('field_tags')->referencedEntities();
    // Load the configuration object based on the YAML filename
    $config = \Drupal::config('policy_evidence_interface.settings');
    // Traverse into the nested array directly using dot notation
    $disallow_tag_ids = $config->get('access_control_tags.disallow_tag_ids');
    foreach ($terms as $term) {
      $term_id = $term->id();
      $term_name = $term->label();
      // Check if the current term ID exists in your other list
      if (in_array($term_id, $disallow_tag_ids)) {
        return ['error' => "Node {$nid} is not allowed to be read due to containing tag: {$term_name}."];
      }
    }

    // Build a field value map for all non-computed fields.
    $fields = [];
    foreach ($node->getFieldDefinitions() as $field_name => $definition) {
      // Skip internal fields already surfaced at top level.
      if (in_array($field_name, ['nid', 'vid', 'type', 'uuid', 'langcode', 'status', 'title', 'uid', 'created', 'changed', 'promote', 'sticky', 'default_langcode', 'revision_translation_affected'])) {
        continue;
      }
      if ($node->hasField($field_name) && !$node->get($field_name)->isEmpty()) {
        $fields[$field_name] = $node->get($field_name)->getString();
      }
    }

    return [
      'nid'     => (int) $node->id(),
      'title'   => $node->label(),
      'type'    => $node->bundle(),
      'status'  => $node->isPublished(),
      'author'  => $node->getOwner()->getAccountName(),
      'url'     => $node->toUrl('canonical', ['absolute' => TRUE])->toString(),
      'created' => date('c', $node->getCreatedTime()),
      'changed' => date('c', $node->getChangedTime()),
      'body'    => $node->hasField('body') && !$node->get('body')->isEmpty()
                    ? strip_tags($node->get('body')->value)
                    : NULL,
      'fields'  => $fields,
    ];
  }

}
