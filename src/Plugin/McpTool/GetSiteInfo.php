<?php

namespace Drupal\policy_evidence_interface\Plugin\McpTool;

use Drupal\policy_evidence_interface\Plugin\McpToolBase;

/**
 * Returns basic information about the Drupal site.
 *
 * @McpTool(
 *   id = "get_site_info",
 *   tool_name = "get_site_info",
 *   description = "Returns general information about this Drupal site including name, slogan, Drupal version, and base URL."
 * )
 */
class GetSiteInfo extends McpToolBase {

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
    $config   = \Drupal::config('system.site');
    $base_url = \Drupal::request()->getSchemeAndHttpHost();

    return [
      'site_name'      => $config->get('name') ?? '',
      'slogan'         => $config->get('slogan') ?? '',
      'mail'           => $config->get('mail') ?? '',
      'base_url'       => $base_url,
      'drupal_version' => \Drupal::VERSION,
      'default_langcode' => $config->get('default_langcode') ?? 'en',
    ];
  }

}
