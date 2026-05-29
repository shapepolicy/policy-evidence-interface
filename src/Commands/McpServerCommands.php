<?php

namespace Drupal\policy_evidence_interface\Commands;

use Drupal\policy_evidence_interface\Plugin\McpToolPluginManager;
use Drupal\policy_evidence_interface\Plugin\McpResourcePluginManager;
use Drupal\policy_evidence_interface\Controller\McpServerController;
use Drush\Commands\DrushCommands;
use Symfony\Component\HttpFoundation\Request;

/**
 * Drush commands for the MCP server.
 *
 * Runs the MCP server over stdio so Claude Desktop (and other MCP clients
 * that use the stdio transport) can connect via:
 *
 *   ddev exec vendor/bin/drush mcp:server
 */
class McpServerCommands extends DrushCommands {

  public function __construct(
    protected McpToolPluginManager $toolManager,
    protected McpResourcePluginManager $resourceManager,
  ) {
    parent::__construct();
  }

  /**
   * Runs the MCP server over stdio (newline-delimited JSON-RPC).
   *
   * @command mcp:server
   * @aliases mcp-server
   * @description Start the MCP server and listen for JSON-RPC messages on stdin.
   */
  public function server(): void {
    // Silence all Drupal/PHP output so only our JSON goes to stdout.
    // Drush may have already bootstrapped and printed things — reset.
    while (ob_get_level()) {
      ob_end_clean();
    }

    $stdin = fopen('php://stdin', 'r');

    while (!feof($stdin)) {
      $line = fgets($stdin);

      if ($line === FALSE || trim($line) === '') {
        continue;
      }

      $message = json_decode(trim($line), TRUE);

      if (json_last_error() !== JSON_ERROR_NONE) {
        $this->writeJson([
          'jsonrpc' => '2.0',
          'id'      => null,
          'error'   => ['code' => -32700, 'message' => 'Parse error'],
        ]);
        continue;
      }

      // Handle batch.
      if (isset($message[0]) && is_array($message[0])) {
        foreach ($message as $msg) {
          $response = $this->dispatch($msg);
          if ($response !== null) {
            $this->writeJson($response);
          }
        }
        continue;
      }

      $response = $this->dispatch($message);
      if ($response !== null) {
        $this->writeJson($response);
      }
    }

    fclose($stdin);
  }

  /**
   * Dispatches a single JSON-RPC message, reusing the HTTP controller logic.
   */
  protected function dispatch(array $message): ?array {
    $id     = $message['id'] ?? null;
    $method = $message['method'] ?? null;
    $params = $message['params'] ?? [];

    if (!$method) {
      return $this->error($id, -32600, 'Invalid Request: missing method');
    }

    try {
      $result = match ($method) {
        'initialize'                   => $this->handleInitialize($params),
        'notifications/initialized'    => null,
        'ping'                         => [],
        'tools/list'                   => $this->handleToolsList(),
        'tools/call'                   => $this->handleToolsCall($params),
        'resources/list'               => $this->handleResourcesList(),
        'resources/read'               => $this->handleResourcesRead($params),
        'resources/templates/list'     => $this->handleResourceTemplatesList(),
        'prompts/list'                 => ['prompts' => []],
        default => throw new \InvalidArgumentException("Method not found: {$method}", -32601),
      };
    }
    catch (\InvalidArgumentException $e) {
      return $this->error($id, $e->getCode() ?: -32601, $e->getMessage());
    }
    catch (\Throwable $e) {
      return $this->error($id, -32603, 'Internal error: ' . $e->getMessage());
    }

    if ($result === null) {
      return null; // Notification — no response.
    }

    return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
  }

  // ---------------------------------------------------------------------------
  // Handlers (mirrors McpServerController, but without HTTP layer)
  // ---------------------------------------------------------------------------

  protected function handleInitialize(array $params): array {
    return [
      'protocolVersion' => McpServerController::MCP_VERSION,
      'capabilities' => [
        'tools'     => ['listChanged' => false],
        'resources' => ['listChanged' => false, 'subscribe' => false],
        'prompts'   => ['listChanged' => false],
        'logging'   => new \stdClass(),
      ],
      'serverInfo' => [
        'name'    => McpServerController::SERVER_NAME,
        'version' => McpServerController::SERVER_VERSION,
      ],
      'instructions' => 'This MCP server provides tools and resources for interacting with a Drupal site.',
    ];
  }

  protected function handleToolsList(): array {
    $tools = [];
    foreach ($this->toolManager->getDefinitions() as $id => $def) {
      $plugin  = $this->toolManager->createInstance($id);
      $tools[] = $plugin->getToolDefinition();
    }
    return ['tools' => $tools];
  }

  protected function handleToolsCall(array $params): array {
    $toolName  = $params['name'] ?? null;
    $arguments = $params['arguments'] ?? [];

    if (!$toolName) {
      throw new \InvalidArgumentException('Missing tool name', -32602);
    }

    foreach ($this->toolManager->getDefinitions() as $id => $def) {
      $plugin  = $this->toolManager->createInstance($id);
      $toolDef = $plugin->getToolDefinition();
      if ($toolDef['name'] === $toolName) {
        $result = $plugin->execute($arguments);
        return [
          'content' => [
            ['type' => 'text', 'text' => is_string($result) ? $result : json_encode($result, JSON_PRETTY_PRINT)],
          ],
          'isError' => false,
        ];
      }
    }

    throw new \InvalidArgumentException("Unknown tool: {$toolName}", -32601);
  }

  protected function handleResourcesList(): array {
    $resources = [];
    foreach ($this->resourceManager->getDefinitions() as $id => $def) {
      $plugin      = $this->resourceManager->createInstance($id);
      $resources[] = $plugin->getResourceDefinition();
    }
    return ['resources' => $resources];
  }

  protected function handleResourcesRead(array $params): array {
    $uri = $params['uri'] ?? null;
    if (!$uri) {
      throw new \InvalidArgumentException('Missing resource URI', -32602);
    }
    foreach ($this->resourceManager->getDefinitions() as $id => $def) {
      $plugin  = $this->resourceManager->createInstance($id);
      $resDef  = $plugin->getResourceDefinition();
      if ($resDef['uri'] === $uri) {
        return ['contents' => [$plugin->read()]];
      }
    }
    throw new \InvalidArgumentException("Resource not found: {$uri}", -32601);
  }

  protected function handleResourceTemplatesList(): array {
    return [
      'resourceTemplates' => [
        ['uriTemplate' => 'drupal://node/{nid}', 'name' => 'Drupal Node', 'mimeType' => 'application/json'],
        ['uriTemplate' => 'drupal://user/{uid}', 'name' => 'Drupal User', 'mimeType' => 'application/json'],
      ],
    ];
  }

  // ---------------------------------------------------------------------------
  // Helpers
  // ---------------------------------------------------------------------------

  protected function writeJson(array $data): void {
    // Must be a single line followed by a newline — stdio MCP framing.
    fwrite(STDOUT, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
    fflush(STDOUT);
  }

  protected function error(mixed $id, int $code, string $message): array {
    return [
      'jsonrpc' => '2.0',
      'id'      => $id,
      'error'   => ['code' => $code, 'message' => $message],
    ];
  }

}
