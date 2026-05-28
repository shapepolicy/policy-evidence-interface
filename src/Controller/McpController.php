<?php

declare(strict_types=1);

namespace Drupal\policy_evidence_interface\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\policy_evidence_interface\Service\PolicyEvidence;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Handles MCP-style tool requests for policy evidence.
 */
final class McpController extends ControllerBase {

  public function __construct(
    private readonly PolicyEvidence $policyEvidence,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('policy_evidence_interface.policy_evidence'),
    );
  }

  public function handle(Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE);

    if (!is_array($data)) {
      return new JsonResponse([
        'error' => 'Invalid JSON request body.',
      ], 400);
    }

    $toolName = $data['tool'] ?? '';
    $arguments = $data['arguments'] ?? [];

    if (!is_string($toolName) || $toolName === '') {
      return new JsonResponse([
        'error' => 'Missing tool name.',
      ], 400);
    }

    if (!is_array($arguments)) {
      return new JsonResponse([
        'error' => 'Arguments must be an object.',
      ], 400);
    }

    $result = $this->policyEvidence->executeTool($toolName, $arguments);

    return new JsonResponse($result);
  }

}