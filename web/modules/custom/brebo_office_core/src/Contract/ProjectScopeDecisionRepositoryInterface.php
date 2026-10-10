<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Contract;

use Drupal\brebo_office_core\Domain\ProjectScopeDecision;

/** Persistence boundary for auditable project-scope decisions. */
interface ProjectScopeDecisionRepositoryInterface {

  /** @return array<string,mixed>|null */
  public function get(int $decisionId): ?array;

  /** @return list<array<string,mixed>> */
  public function currentForProject(int $projectId): array;

  /** @return list<array<string,mixed>> */
  public function historyForSubject(int $projectId, string $subjectKey): array;

  public function save(ProjectScopeDecision $decision): int;

  public function markSuperseded(int $decisionId, int $supersededById): void;

  /** @param callable():mixed $callback */
  public function transactional(callable $callback): mixed;

}
