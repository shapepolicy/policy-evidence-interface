<?php

namespace Drupal\policy_evidence_interface\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Adds CORS headers to Drupal's response for MCP browser preflight requests.
 */
final class McpPreflightResponseSubscriber implements EventSubscriberInterface {

  /**
   * Adds the controller's CORS headers to a successful MCP preflight response.
   */
  public function onResponse(ResponseEvent $event): void {
    $request = $event->getRequest();
    if (!$event->isMainRequest() || !$request->isMethod('OPTIONS') || $request->getPathInfo() !== '/_mcp' || !$request->headers->has('Origin') || !$request->headers->has('Access-Control-Request-Method') || !$event->getResponse()->isSuccessful()) {
      return;
    }

    $headers = $event->getResponse()->headers;
    $headers->set('Access-Control-Allow-Origin', '*');
    $headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
    $headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, Mcp-Session-Id');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [KernelEvents::RESPONSE => 'onResponse'];
  }

}
