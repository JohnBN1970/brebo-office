<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

use RuntimeException;

/**
 * Application boundary for an authenticated Office shift form POST.
 * HTTP adapter must supply operator identity and session-held CSRF token
 * from the verified Office session, never from the submitted form.
 */
final class OfficeInzetShiftPostHandler {
  public function __construct(private OfficeInzetShiftSubmission $submission) {}

  /** @param array<string,mixed> $post */
  public function handle(string $method, int $operatorId, array $post, ?string $sessionCsrfToken): int {
    if ($method !== 'POST') {
      throw new RuntimeException('Shift changes require POST.');
    }
    if ($operatorId < 1) {
      throw new RuntimeException('Authenticated Office operator required.');
    }
    if (!OfficeInzetShiftCsrf::verify($post['csrf_token'] ?? null, $sessionCsrfToken)) {
      throw new RuntimeException('Invalid planning request token.');
    }
    return $this->submission->createForOperator($operatorId, $post);
  }
}
