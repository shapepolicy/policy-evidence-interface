<?php

namespace Drupal\policy_evidence_interface\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\policy_evidence_interface\Plugin\McpToolPluginManager;
use Drupal\policy_evidence_interface\Plugin\McpResourcePluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\policy_evidence_interface\Service\McpRateLimiter;

/**
 * Controller for the MCP server endpoint.
 *
 * Implements the Model Context Protocol (MCP) over HTTP+SSE or
 * streamable HTTP as per the MCP specification.
 */
class McpServerController extends ControllerBase {

  /**
   * MCP Protocol version.
   */
  public const MCP_VERSION = '2024-11-05';

  /**
   * Server info.
   */
  public const SERVER_NAME = 'Drupal MCP Server';
  public const SERVER_VERSION = '1.0.0';

  public function __construct(
    protected McpToolPluginManager $toolManager,
    protected McpResourcePluginManager $resourceManager,
    protected McpRateLimiter $rateLimiter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('plugin.manager.policy_evidence_interface.tool'),
      $container->get('plugin.manager.policy_evidence_interface.resource'),
      $container->get('policy_evidence_interface.rate_limiter'),
    );
  }

  /**
   * Main handler for all MCP requests.
   */
  public function handle(Request $request): Response {
    // Handle CORS preflight.
    if ($request->getMethod() === 'OPTIONS') {
      return $this->corsResponse(new Response('', 204));
    }

    // All MCP traffic is POST with JSON-RPC body.
    if ($request->getMethod() !== 'POST') {
      return $this->corsResponse(new JsonResponse(
        $this->errorResponse(null, -32600, 'Method not allowed. Use POST.'),
        405
      ));
    }

    $body = $request->getContent();
    $data = json_decode($body, TRUE);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
      return $this->corsResponse(new JsonResponse(
        $this->errorResponse(null, -32700, 'Parse error: invalid JSON'),
        400
      ));
    }

    // Handle batch requests (array of messages).
    if (isset($data[0]) && is_array($data[0])) {
      $responses = array_map(fn($msg) => $this->dispatch($msg), $data);
      $responses = array_filter($responses); // Remove nulls (notifications).
      return $this->corsResponse(new JsonResponse(array_values($responses)));
    }

    $result = $this->dispatch($data);

    // Notifications return no content.
    if ($result === NULL) {
      return $this->corsResponse(new Response('', 204));
    }

    return $this->corsResponse(new JsonResponse($result));
  }

  /**
   * Dispatches a single JSON-RPC message to the appropriate handler.
   */
  protected function dispatch(array $message): ?array {
    $id     = $message['id'] ?? NULL;
    $method = $message['method'] ?? NULL;
    $params = $message['params'] ?? [];

    if (!$method) {
      return $this->errorResponse($id, -32600, 'Invalid Request: missing method');
    }

    try {
      $result = match ($method) {
        'initialize'        => $this->handleInitialize($params),
        'notifications/initialized' => NULL, // notification, no response
        'ping'              => [],
        'tools/list'        => $this->handleToolsList($params),
        'tools/call'        => $this->handleToolsCall($params),
        'resources/list'    => $this->handleResourcesList($params),
        'resources/read'    => $this->handleResourcesRead($params),
        'resources/templates/list' => $this->handleResourceTemplatesList(),
        'prompts/list'      => ['prompts' => []],
        default             => throw new \InvalidArgumentException("Method not found: {$method}", -32601),
      };
    }
    catch (\InvalidArgumentException $e) {
      return $this->errorResponse($id, $e->getCode() ?: -32601, $e->getMessage());
    }
    catch (\Exception $e) {
      return $this->errorResponse($id, -32603, 'Internal error: ' . $e->getMessage());
    }

    // Notifications (no id) and NULL results produce no response.
    if ($result === NULL) {
      return NULL;
    }

    return $this->successResponse($id, $result);
  }

  // ---------------------------------------------------------------------------
  // MCP Method Handlers
  // ---------------------------------------------------------------------------

  /**
   * Handles the initialize handshake.
   */
  protected function handleInitialize(array $params): array {
    return [
      'protocolVersion' => self::MCP_VERSION,
      'capabilities'    => [
        'tools'     => ['listChanged' => FALSE],
        'resources' => ['listChanged' => FALSE, 'subscribe' => FALSE],
        'prompts'   => ['listChanged' => FALSE],
        'logging'   => new \stdClass(),
      ],
      'serverInfo'      => [
        'name'    => self::SERVER_NAME,
        'version' => self::SERVER_VERSION,
      ],
      'instructions'    => 'This MCP server provides tools and resources for interacting with a Drupal site.',
    ];
  }

  /**
   * Lists all available tools.
   */
  protected function handleToolsList(array $params): array {
    $definitions = $this->toolManager->getDefinitions();
    $tools = [];

    foreach ($definitions as $id => $definition) {
      /** @var \Drupal\policy_evidence_interface\Plugin\McpToolInterface $plugin */
      $plugin = $this->toolManager->createInstance($id);
      $tools[] = $plugin->getToolDefinition();
    }

    return ['tools' => $tools];
  }

  /**
   * Calls a tool by name.
   */
  protected function handleToolsCall(array $params): array {
    $toolName  = $params['name'] ?? NULL;
    $arguments = $params['arguments'] ?? [];

    if (!$toolName) {
      throw new \InvalidArgumentException('Missing tool name', -32602);
    }

    // Find the plugin whose tool name matches.
    foreach ($this->toolManager->getDefinitions() as $id => $definition) {
      /** @var \Drupal\policy_evidence_interface\Plugin\McpToolInterface $plugin */
      $plugin = $this->toolManager->createInstance($id);
      $toolDef = $plugin->getToolDefinition();

      if ($toolDef['name'] === $toolName) {
         if ($this->currentUser()->isAuthenticated()) {
          $clientId = 'user:' . $this->currentUser()->id();
        }
        else {
          // The ip message of unknown user
          $clientIp = \Drupal::request()->getClientIp() ?? 'unknown';
          $clientId = 'ip:' . $clientIp;
        }

        // check the tool
        $rateLimitResult = $this->rateLimiter->check(
          $clientId,
          $toolName,
        );

        // if not allowed, return the message 
        if (!$rateLimitResult['allowed']) {
          return [
            'content' => [
              [
                'type' => 'text',
                'text' => $rateLimitResult['message']
                  . ' Try again in '
                  . $rateLimitResult['retry_after']
                  . ' seconds.',
              ],
            ],
            'isError' => TRUE,
          ];
        }

        
        $result = $plugin->execute($arguments);
        return [
          'content' => [
            ['type' => 'text', 'text' => is_string($result) ? $result : json_encode($result, JSON_PRETTY_PRINT)],
          ],
          'isError' => FALSE,
        ];
      }
    }

    throw new \InvalidArgumentException("Unknown tool: {$toolName}", -32601);
  }

  /**
   * Lists all available resources.
   */
  protected function handleResourcesList(array $params): array {
    $definitions = $this->resourceManager->getDefinitions();
    $resources = [];

    foreach ($definitions as $id => $definition) {
      /** @var \Drupal\policy_evidence_interface\Plugin\McpResourceInterface $plugin */
      $plugin = $this->resourceManager->createInstance($id);
      $resources[] = $plugin->getResourceDefinition();
    }

    return ['resources' => $resources];
  }

  /**
   * Reads a resource by URI.
   */
  protected function handleResourcesRead(array $params): array {
    $uri = $params['uri'] ?? NULL;

    if (!$uri) {
      throw new \InvalidArgumentException('Missing resource URI', -32602);
    }

    foreach ($this->resourceManager->getDefinitions() as $id => $definition) {
      /** @var \Drupal\policy_evidence_interface\Plugin\McpResourceInterface $plugin */
      $plugin = $this->resourceManager->createInstance($id);
      $resDef = $plugin->getResourceDefinition();

      if ($resDef['uri'] === $uri) {
        $content = $plugin->read();
        return ['contents' => [$content]];
      }
    }

    throw new \InvalidArgumentException("Resource not found: {$uri}", -32601);
  }

  /**
   * Lists resource templates.
   */
  protected function handleResourceTemplatesList(): array {
    return [
      'resourceTemplates' => [
        [
          'uriTemplate' => 'drupal://node/{nid}',
          'name'        => 'Drupal Node',
          'description' => 'Fetch a Drupal node by its numeric ID.',
          'mimeType'    => 'application/json',
        ],
        [
          'uriTemplate' => 'drupal://user/{uid}',
          'name'        => 'Drupal User',
          'description' => 'Fetch a Drupal user account by its numeric ID.',
          'mimeType'    => 'application/json',
        ],
      ],
    ];
  }

  // ---------------------------------------------------------------------------
  // JSON-RPC Helpers
  // ---------------------------------------------------------------------------

  protected function successResponse(mixed $id, array $result): array {
    return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
  }

  protected function errorResponse(mixed $id, int $code, string $message): array {
    return [
      'jsonrpc' => '2.0',
      'id'      => $id,
      'error'   => ['code' => $code, 'message' => $message],
    ];
  }

  /**
   * Adds CORS headers required for browser-based MCP clients.
   */
  protected function corsResponse(Response $response): Response {
    $response->headers->set('Access-Control-Allow-Origin', '*');
    $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
    $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, Mcp-Session-Id');
    $response->headers->set('Content-Type', 'application/json');
    return $response;
  }

}
