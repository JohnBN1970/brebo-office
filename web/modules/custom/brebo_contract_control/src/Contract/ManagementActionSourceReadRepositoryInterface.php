<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Contract;

/** Read boundary for management-action operational sources. */
interface ManagementActionSourceReadRepositoryInterface {

  /** @return array<string, mixed>|null */
  public function findAction(int $actionId): ?array;

  /** @return array<int, array<string, mixed>> */
  public function findOpenCriticalControllerCases(): array;

  /** @return array<int, array<string, mixed>> */
  public function findBlockedInvoices(): array;

  /** @return array<int, array<string, mixed>> */
  public function findOverdueObligations(int $now): array;

}
