<?php

namespace Drupal\policy_evidence_interface\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Plugin manager for MCP Resource plugins.
 *
 * Discovers plugins in the Plugin/McpResource namespace annotated with @McpResource.
 */
class McpResourcePluginManager extends DefaultPluginManager {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
  ) {
    parent::__construct(
      'Plugin/McpResource',
      $namespaces,
      $module_handler,
      'Drupal\policy_evidence_interface\Plugin\McpResourceInterface',
      'Drupal\policy_evidence_interface\Annotation\McpResource',
    );

    $this->alterInfo('policy_evidence_interface_resource_info');
    $this->setCacheBackend($cache_backend, 'policy_evidence_interface_resource_plugins');
  }

}
