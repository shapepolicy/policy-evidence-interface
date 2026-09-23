<?php

namespace Drupal\policy_evidence_interface\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Handles MCP CORS preflight before Drupal's generic OPTIONS response.
 */
final class McpPreflightSubscriber implements EventSubscriberInterface {

  /**
   * Responds to preflight requests for the MCP endpoint.
   */
  public function onRequest(RequestEvent $event): void {
    $request = $event->getRequest();
    if (!$event->isMainRequest() || !$request->isMethod('OPTIONS') || $request->getPathInfo() !== '/_mcp') {
      return;
    }

    $response = new Response('', Response::HTTP_NO_CONTENT);
    $response->headers->set('Access-Control-Allow-Origin', '*');
    $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
    $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, Mcp-Session-Id');
    $event->setResponse($response);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Drupal's generic OPTIONS subscriber runs at priority 1000.
    return [KernelEvents::REQUEST => ['onRequest', 1001]];
  }

}
