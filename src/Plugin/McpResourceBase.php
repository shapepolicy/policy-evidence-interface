<?php

namespace Drupal\policy_evidence_interface\Plugin;

use Drupal\Core\Plugin\PluginBase;

/**
 * Base class for MCP Resource plugins.
 */
abstract class McpResourceBase extends PluginBase implements McpResourceInterface {

  /**
   * {@inheritdoc}
   */
  public function getResourceDefinition(): array {
    return [
      'uri'         => $this->pluginDefinition['uri'] ?? '',
      'name'        => $this->pluginDefinition['name'] ?? $this->getPluginId(),
      'description' => $this->pluginDefinition['description'] ?? '',
      'mimeType'    => $this->pluginDefinition['mimeType'] ?? 'application/json',
    ];
  }

}
