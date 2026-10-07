<?php

declare(strict_types=1);

namespace Drupal\brebo_control\Contract;

/** Source boundary for legacy project control analyses. */
interface ControlProjectAnalysisSourceInterface {

  /** @return array<string, mixed>|null */
  public function controllerActions(int $projectId): ?array;

  /**
   * @return array{
   *   warning:array<string,mixed>,
   *   finance:array<string,mixed>
   * }|null
   */
  public function historySnapshot(int $projectId): ?array;

}
