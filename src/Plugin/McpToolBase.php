<?php

namespace Drupal\policy_evidence_interface\Plugin;

use Drupal\Core\Plugin\PluginBase;

/**
 * Base class for MCP Tool plugins.
 */
abstract class McpToolBase extends PluginBase implements McpToolInterface {

  /**
   * {@inheritdoc}
   *
   * Subclasses should override this with their specific schema and description.
   */
  public function getToolDefinition(): array {
    return [
      'name'        => $this->pluginDefinition['tool_name'] ?? $this->getPluginId(),
      'description' => $this->pluginDefinition['description'] ?? '',
      'inputSchema' => $this->inputSchema(),
    ];
  }

  /**
   * Returns the JSON Schema for the tool's input.
   *
   * Override in subclasses to define accepted parameters.
   */
  protected function inputSchema(): array {
    return [
      'type'       => 'object',
      'properties' => new \stdClass(),
      'required'   => [],
    ];
  }

}
