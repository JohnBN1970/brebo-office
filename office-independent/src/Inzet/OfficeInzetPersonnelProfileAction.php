<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Application boundary for a verified Office operator and session.
 * The HTTP route must resolve identity server-side, not from POST fields.
 */
final class OfficeInzetPersonnelProfileAction {
  public function __construct(private OfficeInzetPersonnelProfileRepository $repository) {}

  /** @param array<string,mixed> $post */
  public function update(array $post, string $verifiedOperatorId, string $verifiedSessionId, string $csrfSecret): bool {
    if (!OfficeInzetPersonnelProfileCsrf::verify($post['csrf_token'] ?? null, $verifiedSessionId, $csrfSecret)) {
      throw new \DomainException('Personnel profile request verification failed');
    }
    $projectId = filter_var($post['project_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    $personId = filter_var($post['person_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    $number = $post['personnel_number'] ?? null;
    if (!is_int($projectId) || !is_int($personId) || !is_string($number)) {
      throw new \InvalidArgumentException('Invalid personnel profile input');
    }
    return $this->repository->updatePersonnelNumber($verifiedOperatorId, $projectId, $personId, $number);
  }
}
