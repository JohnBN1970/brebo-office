<?php

declare(strict_types=1);

namespace Drupal\brebo_sandbox_reset\Contract;

/** Framework boundary for destructive sandbox-reset operations. */
interface SandboxResetGatewayInterface {

  /** @return array<string, int|string> */
  public function preview(string $scope): array;

  public function resetMail(string $scope): void;

  public function resetWebsiteEuropakozijn(): void;

  public function resetObjects(string $scope): void;

  /** Execute a reset operation atomically where supported. */
  public function transactional(callable $operation): void;

}
