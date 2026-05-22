<?php

declare(strict_types=1);

namespace Drupal\policy_evidence_interface\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

final class PolicyEvidence {

    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
        private readonly ConfigFactoryInterface $configFactory,
      ) {}
      
    public function executeTool(string $toolName, array $arguments = []): array {
        return match ($toolName) {
            'search_policies' => $this->searchPolicies($arguments),
            'get_policy' => $this->getPolicy($arguments),
            default => [
                'error' => 'Unknown tool: ' . $toolName,
            ],
        };
    }

    private function searchPolicies(array $arguments): array {
    $queryText = trim($arguments['query'] ?? '');
  
    if ($queryText === '') {
      return [
        'results' => [],
        'message' => 'Missing query.',
      ];
    }
  
    $config = $this->configFactory->get('policy_evidence_interface.settings');
  
    $maxResults = (int) ($config->get('max_results') ?? 10);
    $enabledTypes = $config->get('enabled_content_types') ?? [];
  
    if (empty($enabledTypes)) {
      return [
        'results' => [],
        'message' => 'No content types enabled.',
      ];
    }
  
    $query = $this->entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->condition('type', $enabledTypes, 'IN')
      ->range(0, $maxResults);
  
    $escapedQueryText = $query->escapeLike($queryText);
    $or = $query->orConditionGroup()
      ->condition('title', '%' . $escapedQueryText . '%', 'LIKE')
      ->condition('body.value', '%' . $escapedQueryText . '%', 'LIKE');
  
    $query->condition($or);
  
    $nids = $query->execute();
  
    if (empty($nids)) {
      return [
        'results' => [],
      ];
    }
  
    $nodes = $this->entityTypeManager
      ->getStorage('node')
      ->loadMultiple($nids);
  
    $results = [];
  
    foreach ($nodes as $node) {
      $results[] = [
        'id' => (int) $node->id(),
        'title' => $node->label(),
        'type' => $node->bundle(),
        'url' => $node->toUrl('canonical', ['absolute' => TRUE])->toString(),
      ];
    }
  
    return [
      'results' => $results,
    ];
  }

  private function getPolicy(array $arguments): array {
    $nid = (int) ($arguments['id'] ?? 0);
  
    if ($nid <= 0) {
      return [
        'error' => 'Missing or invalid policy id.',
      ];
    }
  
    $config = $this->configFactory->get('policy_evidence_interface.settings');
    $enabledTypes = $config->get('enabled_content_types') ?? [];
  
    $node = $this->entityTypeManager
      ->getStorage('node')
      ->load($nid);
  
    if (!$node) {
      return [
        'error' => 'Policy not found.',
      ];
    }
  
    if (!$node->access('view')) {
      return [
        'error' => 'Access denied.',
      ];
    }
  
    if (!empty($enabledTypes) && !in_array($node->bundle(), $enabledTypes, TRUE)) {
      return [
        'error' => 'This content type is not enabled for policy search.',
      ];
    }
  
    $body = '';
  
    if ($node->hasField('body') && !$node->get('body')->isEmpty()) {
      $body = $node->get('body')->value;
    }
  
    return [
      'id' => (int) $node->id(),
      'title' => $node->label(),
      'type' => $node->bundle(),
      'body' => $body,
      'url' => $node->toUrl('canonical', ['absolute' => TRUE])->toString(),
    ];
  }



}
