<?php

namespace Drupal\policy_evidence_interface\Plugin\Mcp;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp\Attribute\Mcp;
use Drupal\mcp\Plugin\McpPluginBase;
use Drupal\mcp\ServerFeatures\Tool;
use Drupal\mcp\ServerFeatures\ToolAnnotations;

/**
 * MCP plugin for exposing APO policy resources to AI systems.
 */
#[Mcp(
  id: 'policy_evidence_interface',
  name: new TranslatableMarkup('Policy Evidence Interface'),
  description: new TranslatableMarkup('Exposes APO policy resources to AI systems via MCP.'),
)]
class PolicyEvidence extends McpPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getTools(): array {
    return [
      new Tool(
        name: 'search_policies',
        description: 'Search APO policy resources by keyword, topic, or date range.',
        inputSchema: [
          'type' => 'object',
          'properties' => [
            'query' => [
              'type' => 'string',
              'description' => 'Search keywords',
            ],
            'limit' => [
              'type' => 'integer',
              'default' => 10,
              'maximum' => 50,
              'description' => 'Maximum number of results',
            ],
          ],
          'required' => ['query'],
        ],
        annotations: new ToolAnnotations(
          readOnlyHint: true,
          idempotentHint: true,
          destructiveHint: false,
        ),
      ),
      new Tool(
        name: 'get_policy',
        description: 'Retrieve a single APO policy document by its Drupal node ID.',
        inputSchema: [
          'type' => 'object',
          'properties' => [
            'id' => [
              'type' => 'integer',
              'description' => 'Drupal node ID',
            ],
          ],
          'required' => ['id'],
        ],
        annotations: new ToolAnnotations(
          readOnlyHint: true,
          idempotentHint: true,
          destructiveHint: false,
        ),
      ),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function executeTool(string $toolId, mixed $arguments): array {
    if ($toolId === 'search_policies' || $toolId === md5('search_policies')) {
      return [['type' => 'text', 'text' => json_encode(['status' => 'not yet implemented'])]];
    }
    if ($toolId === 'get_policy' || $toolId === md5('get_policy')) {
      return [['type' => 'text', 'text' => json_encode(['status' => 'not yet implemented'])]];
    }
    throw new \InvalidArgumentException("Unknown tool: $toolId");
  }

  /**
   * {@inheritdoc}
   */
  public function checkRequirements(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getRequirementsDescription(): string {
    return '';
  }

}
