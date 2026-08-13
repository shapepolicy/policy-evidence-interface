<?php

namespace Drupal\policy_evidence_interface\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\policy_evidence_interface\Plugin\McpToolPluginManager;
use Drupal\policy_evidence_interface\Plugin\McpResourcePluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('plugin.manager.policy_evidence_interface.tool'),
      $container->get('plugin.manager.policy_evidence_interface.resource'),
    );
  }

  /**
   * Main handler for all MCP requests.
   */
  public function handle(Request $request): Response {
    // Handle CORS preflight.
    //if ($request->getMethod() === 'OPTIONS') {
    //  return $this->corsResponse(new Response('', 204));
    //}
    if ($request->getMethod() === 'OPTIONS') {
      $response = new Response();
      $response->headers->set('Access-Control-Allow-Origin', '*');
      $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
      $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization');
      return $response;
    }

    //OAuth successfully authenticated the user
    $account = \Drupal::currentUser();

    //likely redundant
    if ($account->isAnonymous()) {
      // Token was missing, invalid, or expired. Simple OAuth failed to authenticate.
      return $this->buildUnauthorizedResponse();
    }
    // simple roles based filter
    // Check if the user DOES NOT have the 'mcp_connector' role
    if (!in_array('mcp_connector', $account->getRoles())) {
      // User does NOT have the 'mcp_connector' role 
      return $this->buildUnauthorizedResponse();
    }
        
    // if get get request return
    if ($request->isMethod('GET')) {
      return new JsonResponse([
        'status' => 'ok',
        'message' => 'MCP Endpoint active. Send JSON-RPC via POST requests.',
      ]);
    }

    // All MCP traffic is POST with JSON-RPC body.
    // mabey change to if ($request->isMethod('POST')) {
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

  // ---------------------------------------------------------------------------
  // Oauth 
  // ---------------------------------------------------------------------------

  /**
   * Resource Metadata Endpoint (/.well-known/oauth-protected-resource)
   * leave pupblic for routing 
   */
  public function getResourceMetadata(Request $request): JsonResponse {
    $baseUrl = $request->getSchemeAndHttpHost();

    return new JsonResponse([
      'resource' => $baseUrl . '/_mcp',
      'authorization_servers' => [
        $baseUrl
      ],
      'scopes_supported' => ['mcp_connector_scope'],
      'bearer_methods_supported' => ['header'],
    ]);
  }

  /**
   * Authorization Server Metadata Endpoint (/.well-known/oauth-authorization-server)
   * leave pupblic for routing 
   */
  public function getAuthMetadata(Request $request): JsonResponse {
    $baseUrl = $request->getSchemeAndHttpHost();
    
    $metadata =[
      'issuer' => $baseUrl,
      'authorization_endpoint' => $baseUrl . '/oauth/authorize',
      'token_endpoint' => $baseUrl . '/oauth/token',
      'response_types_supported' => ['code'],
      'grant_types_supported' => ['authorization_code', 'refresh_token'],
      'code_challenge_methods_supported' => ['S256'],// Enables PKCE
      //['client_secret_post', 'client_secret_basic'],//only if client is confidential
      'token_endpoint_auth_methods_supported' => ['none'], // public client non confidential
      //Bypassing the /oauth/register as simple oauth doesn't support
      //Will pass a set public client id 
      'registration_endpoint' =>  $baseUrl . '/oauth/getclientid', // '/oauth/authorize'
    ];
    return new JsonResponse($metadata);
  }
  
  /**
   * Mocks oauth dynamic client register functionality 
   * so that it has dcr functionality 
   * meaning that user dones't need to pass along a client id
   *  
   */
  public function mockedRegister(Request $request): JsonResponse {
    $baseUrl = $request->getSchemeAndHttpHost();
 
    // Query for your pre-established Consumer entity
    $entity_type_manager = $this->entityTypeManager();
    $storage = $entity_type_manager->getStorage('consumer');

    //  find consumer with label 'mcp connector'
    $consumer_ids = $storage->getQuery()
      ->condition('label', 'mcp connector')
      ->accessCheck(FALSE)
      ->range(0, 1)
      ->execute();

    if (empty($consumer_ids)) {
      return new JsonResponse([
        'error' => 'invalid_client_metadata',
        'error_description' => 'Pre-established PKCE consumer not configured.',
      ], Response::HTTP_BAD_REQUEST);
    }

    $consumer = $storage->load(reset($consumer_ids));
    //simple oauth uses Client ID rather than uuid
    $client_id = $consumer->get('client_id')->value;
 
    // extract redirect URIs configured on the consumer
    $registered_redirects = [];
    if ($consumer->hasField('redirect') && !$consumer->get('redirect')->isEmpty()) {
      foreach ($consumer->get('redirect') as $item) {
        $registered_redirects[] = $item->value;
      }
    }
    
    $metadata = [
      'client_id' => $client_id,
      'redirect_uris' => $registered_redirects,
      'grant_types' => [
        'authorization_code',
        'refresh_token',
      ],
      'response_types' => [
        'code',
      ],
      'token_endpoint_auth_method' => 'none', // Required for PKCE public clients
      'code_challenge_method' => 'S256',
    ];

    return new JsonResponse($metadata, Response::HTTP_CREATED, [
      'Cache-Control' => 'no-store',
      'Pragma' => 'no-cache',
    ]);
  }

  /**
   * Dispatches a OAuth 2.1 token error
   */
  private function buildUnauthorizedResponse(): JsonResponse {
    $baseUrl = \Drupal::request()->getSchemeAndHttpHost();

    $response = new JsonResponse(
        ['error' => 'unauthorized', 'error_description' => 'Bearer token required'],
        401
    );

    $response->headers->set(
        'WWW-Authenticate',
        sprintf(
            'Bearer resource_metadata="%s/.well-known/oauth-protected-resource"',
            $baseUrl
        )
    );

    return $response;
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
