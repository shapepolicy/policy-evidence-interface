<?php

namespace Drupal\policy_evidence_interface\Plugin;

/**
 * Interface for MCP Tool plugins.
 */
interface McpToolInterface {

  /**
   * Returns the MCP tool definition (name, description, inputSchema).
   *
   * @return array
   *   Array with keys: name, description, inputSchema.
   */
  public function getToolDefinition(): array;

  /**
   * Executes the tool with the given arguments.
   *
   * @param array $arguments
   *   Key/value arguments matching the tool's inputSchema.
   *
   * @return mixed
   *   String or array result; will be JSON-encoded if not a string. Return
   *   ['error' => 'message'] to report a failure; the MCP response then sets
   *   isError to TRUE.
   */
  public function execute(array $arguments): mixed;

}
