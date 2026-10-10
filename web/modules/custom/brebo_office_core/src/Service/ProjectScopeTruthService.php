<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

use Drupal\brebo_office_core\Contract\ProjectScopeDecisionRepositoryInterface;
use Drupal\brebo_office_core\Domain\ProjectScopeDecision;

/**
 * Maintains the reviewed project-scope truth without rewriting source history.
 */
final class ProjectScopeTruthService {

  public function __construct(
    private readonly ProjectScopeDecisionRepositoryInterface $repository,
  ) {}

  public function confirm(ProjectScopeDecision $decision): int {
    return $this->repository->transactional(function () use ($decision): int {
      if ($decision->supersedesId !== NULL) {
        $previous = $this->repository->get($decision->supersedesId);
        if ($previous === NULL) {
          throw new \InvalidArgumentException('Superseded scope decision does not exist.');
        }
        if ((int) $previous['project_id'] !== $decision->projectId
          || (string) $previous['subject_key'] !== $decision->subjectKey) {
          throw new \InvalidArgumentException('A scope decision may only supersede the same project subject.');
        }
        if ((string) ($previous['status'] ?? '') !== 'active') {
          throw new \InvalidArgumentException('Only an active scope decision may be superseded.');
        }
      }

      $id = $this->repository->save($decision);
      if ($decision->supersedesId !== NULL) {
        $this->repository->markSuperseded($decision->supersedesId, $id);
      }
      return $id;
    });
  }

  /** @return list<array<string,mixed>> */
  public function currentTruth(int $projectId): array {
    return $this->repository->currentForProject($projectId);
  }

  /** @return list<array<string,mixed>> */
  public function history(int $projectId, string $subjectKey): array {
    return $this->repository->historyForSubject($projectId, $subjectKey);
  }

}
