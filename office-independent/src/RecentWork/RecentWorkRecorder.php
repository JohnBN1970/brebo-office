<?php

declare(strict_types=1);

namespace Brebo\Office\RecentWork;

/**
 * Application service for recording only currently authorized work.
 * Caller derives operatorId from the validated Office session, never request data.
 */
final class RecentWorkRecorder {
  public function __construct(
    private readonly RecentWorkAccess $access,
    private readonly PdoRecentWorkRepository $repository,
  ) {}

  public function record(string $operatorId, RecentWorkEvent $event): bool {
    if (!$this->access->canAccess($operatorId, $event)) {
      return false;
    }
    $this->repository->record($operatorId, $event);
    return true;
  }
}
