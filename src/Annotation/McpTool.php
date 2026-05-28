<?php

namespace Drupal\policy_evidence_interface\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines an MCP Tool plugin annotation.
 *
 * @Annotation
 */
class McpTool extends Plugin {

  /**
   * The plugin ID.
   */
  public string $id;

  /**
   * The machine-readable tool name exposed in the MCP protocol.
   */
  public string $tool_name;

  /**
   * A human-readable description of what the tool does.
   */
  public string $description;

}
