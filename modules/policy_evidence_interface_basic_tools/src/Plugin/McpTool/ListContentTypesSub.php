<?php

namespace Drupal\policy_evidence_interface_basic_tools\Plugin\McpTool;

use Drupal\policy_evidence_interface\Plugin\McpToolBase;

/**
 * Lists all content types defined on the site.
 *
 * @McpTool(
 *   id = "list_content_types_sub",
 *   tool_name = "list_content_types_sub",
 *   description = "Returns all content types (node bundles) configured on this Drupal site, including their machine name, label, and description."
 * )
 */
class ListContentTypesSub extends McpToolBase {

  /**
   * {@inheritdoc}
   */
  protected function inputSchema(): array {
    return [
      'type'       => 'object',
      'properties' => new \stdClass(),
      'required'   => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $arguments): mixed {
    $node_types = \Drupal::entityTypeManager()
      ->getStorage('node_type')
      ->loadMultiple();

    $types = [];
    foreach ($node_types as $type) {
      $types[] = [
        'machine_name' => $type->id(),
        'label'        => $type->label(),
        'description'  => $type->getDescription(),
      ];
    }

    return ['content_types' => $types];
  }

}
