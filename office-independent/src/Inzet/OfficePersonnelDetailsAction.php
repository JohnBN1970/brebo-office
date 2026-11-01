<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Verified Office session boundary for editing an existing personnel dossier.
 * Operator identity and session ID must never be accepted from POST.
 */
final class OfficePersonnelDetailsAction {
  public function __construct(private OfficePersonnelDetailsRepository $repository) {}

  /** @param array<string,mixed> $post */
  public function update(array $post, string $verifiedOperatorId, string $verifiedSessionId, string $csrfSecret): bool {
    if (!OfficeInzetPersonnelProfileCsrf::verify($post['csrf_token'] ?? null, $verifiedSessionId, $csrfSecret)) {
      throw new \DomainException('Personnel details request verification failed');
    }
    $projectId = filter_var($post['project_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    $personId = filter_var($post['person_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    if (!is_int($projectId) || !is_int($personId)) throw new \InvalidArgumentException('Invalid personnel context');
    return $this->repository->save($verifiedOperatorId, $projectId, $personId, $post);
  }
}
