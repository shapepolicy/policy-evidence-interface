<?php

namespace Drupal\policy_evidence_interface\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines an MCP Resource plugin annotation.
 *
 * @Annotation
 */
class McpResource extends Plugin {

  /**
   * The plugin ID.
   */
  public string $id;

  /**
   * The resource URI exposed in the MCP protocol (e.g. drupal://site-info).
   */
  public string $uri;

  /**
   * A human-readable name for the resource.
   */
  public string $name;

  /**
   * A human-readable description of the resource.
   */
  public string $description;

  /**
   * The MIME type of the resource content.
   */
  public string $mimeType = 'application/json';

}
