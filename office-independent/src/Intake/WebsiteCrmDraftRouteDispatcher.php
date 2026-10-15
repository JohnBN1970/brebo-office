<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

/**
 * Fail-closed routing adapter. Only mount behind verified Office authentication.
 * Deployment must explicitly enable the write feature after session integration.
 */
final class WebsiteCrmDraftRouteDispatcher {

  public function __construct(
    private readonly WebsiteCrmDraftHttpController $controller,
    private readonly bool $writeEnabled = false,
  ) {}

  /** @param array<string,mixed> $query
   *  @param array<string,mixed> $post
   *  @return array{status:int,body:string}
   */
  public function dispatch(string $method, string $path, array $query, array $post, int $now): array {
    if (!$this->writeEnabled) {
      return ['status' => 404, 'body' => 'CRM bewerken is niet ingeschakeld.'];
    }
    if ($path !== '/brebo-internal/crm/migration/draft') {
      return ['status' => 404, 'body' => 'CRM-route niet gevonden.'];
    }
    if ($method !== 'GET' && $method !== 'POST') {
      return ['status' => 405, 'body' => 'HTTP-methode niet toegestaan.'];
    }
    if ($method === 'GET') {
      $id = $query['id'] ?? null;
      if (!is_string($id) || !ctype_digit($id) || strlen($id) > 18 || (int) $id < 1) {
        return ['status' => 400, 'body' => 'Ongeldig kansnummer.'];
      }
      return $this->controller->form((int) $id, $now);
    }
    if ($method === 'POST') {
      return $this->controller->submit($post, $now);
    }
    return ['status' => 404, 'body' => 'CRM-route niet gevonden.'];
  }
}
