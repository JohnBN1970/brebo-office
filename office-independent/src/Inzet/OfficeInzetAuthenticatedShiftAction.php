<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

use Brebo\Office\Intake\WebsiteCrmOperatorContext;
use RuntimeException;

/**
 * Adapts the verified Office login context to the shift POST boundary.
 * Never accepts operator IDs or session IDs from submitted form fields.
 * The caller must verify the session through Office's cookie session provider.
 */
final class OfficeInzetAuthenticatedShiftAction {
  public function __construct(private OfficeInzetShiftPostHandler $handler) {}

  /** @param array<string,mixed> $post */
  public function handle(
    string $method,
    WebsiteCrmOperatorContext $operator,
    array $post,
    string $serverSecret
  ): int {
    $token = OfficeInzetSessionCsrf::token($operator->sessionId, $serverSecret);
    return $this->handler->handle($method, $operator->operatorId, $post, $token);
  }

  public static function formToken(WebsiteCrmOperatorContext $operator, string $serverSecret): string {
    return OfficeInzetSessionCsrf::token($operator->sessionId, $serverSecret);
  }
}
