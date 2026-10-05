<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Contract;

interface ProjectProvisionalSumRepositoryInterface {

  public function available(): bool;

  public function projectContractId(int $projectId): ?int;

  public function numberExists(int $projectId, string $number): bool;

  /** @param array<string,mixed> $fields */
  public function create(array $fields): int;

}
