<?php

declare(strict_types=1);

namespace Brebo\Mail\Contract;

interface ProvisioningJobRepositoryInterface {
  public function enqueue(string $resourceType, int $resourceId, string $operation): int;

  /** @return array<string,mixed>|null */
  public function load(int $jobId): ?array;

  public function markProvisioning(int $jobId): void;

  public function markActive(int $jobId, string $providerReference): void;

  public function markError(int $jobId, string $message): void;

  /** @return array<int,array<string,mixed>> */
  public function recent(int $limit = 50): array;
}
