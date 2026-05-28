<?php

namespace Drupal\policy_evidence_interface\Plugin\McpResource;

use Drupal\policy_evidence_interface\Plugin\McpResourceBase;

/**
 * Exposes the Drupal site configuration as an MCP resource.
 *
 * @McpResource(
 *   id = "site_info",
 *   uri = "drupal://site-info",
 *   name = "Site Information",
 *   description = "General configuration of this Drupal site: name, slogan, admin email, Drupal version, and base URL.",
 *   mimeType = "application/json"
 * )
 */
class SiteInfo extends McpResourceBase {

  /**
   * {@inheritdoc}
   */
  public function read(): array {
    $config   = \Drupal::config('system.site');
    $base_url = \Drupal::request()->getSchemeAndHttpHost();

    $data = [
      'site_name'        => $config->get('name') ?? '',
      'slogan'           => $config->get('slogan') ?? '',
      'mail'             => $config->get('mail') ?? '',
      'base_url'         => $base_url,
      'drupal_version'   => \Drupal::VERSION,
      'default_langcode' => $config->get('default_langcode') ?? 'en',
      'front_page'       => $config->get('page.front') ?? '/node',
    ];

    return [
      'uri'      => 'drupal://site-info',
      'mimeType' => 'application/json',
      'text'     => json_encode($data, JSON_PRETTY_PRINT),
    ];
  }

}
