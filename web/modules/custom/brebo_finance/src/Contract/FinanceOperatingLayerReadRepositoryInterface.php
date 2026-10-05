<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

interface FinanceOperatingLayerReadRepositoryInterface {

  /** @return array<string,mixed> */
  public function overview(int $projectNid): array;

  public function budgetBelongsToProject(int $budgetId, int $projectNid): bool;

  public function commitmentBelongsToProject(int $commitmentId, int $projectNid): bool;

}
