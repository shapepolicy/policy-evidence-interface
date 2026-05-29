<?php

namespace Drupal\policy_evidence_interface\Plugin;

/**
 * Interface for MCP Resource plugins.
 */
interface McpResourceInterface {

  /**
   * Returns the MCP resource definition (uri, name, description, mimeType).
   *
   * @return array
   *   Array with keys: uri, name, description, mimeType.
   */
  public function getResourceDefinition(): array;

  /**
   * Reads and returns the resource contents.
   *
   * @return array
   *   Array with keys: uri, mimeType, text (or blob).
   */
  public function read(): array;

}
