<?php

namespace Drupal\policy_evidence_interface\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Plugin manager for MCP Tool plugins.
 *
 * Discovers plugins in the Plugin/McpTool namespace annotated with @McpTool.
 */
class McpToolPluginManager extends DefaultPluginManager {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
  ) {
    parent::__construct(
      'Plugin/McpTool',
      $namespaces,
      $module_handler,
      'Drupal\policy_evidence_interface\Plugin\McpToolInterface',
      'Drupal\policy_evidence_interface\Annotation\McpTool',
    );

    $this->alterInfo('policy_evidence_interface_tool_info');
    $this->setCacheBackend($cache_backend, 'policy_evidence_interface_tool_plugins');
  }

}
