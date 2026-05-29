<?php

namespace Drupal\policy_evidence_interface\Plugin\McpResource;

use Drupal\policy_evidence_interface\Plugin\McpResourceBase;

/**
 * Exposes all content types as an MCP resource.
 *
 * @McpResource(
 *   id = "content_types",
 *   uri = "drupal://content-types",
 *   name = "Content Types",
 *   description = "A list of all node content types (bundles) configured on this Drupal site.",
 *   mimeType = "application/json"
 * )
 */
class ContentTypes extends McpResourceBase {

  /**
   * {@inheritdoc}
   */
  public function read(): array {
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

    return [
      'uri'      => 'drupal://content-types',
      'mimeType' => 'application/json',
      'text'     => json_encode(['content_types' => $types], JSON_PRETTY_PRINT),
    ];
  }

}
