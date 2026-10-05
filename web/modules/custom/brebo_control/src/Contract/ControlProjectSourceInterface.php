<?php

declare(strict_types=1);

namespace Drupal\brebo_control\Contract;

/** Framework boundary for active project control source data. */
interface ControlProjectSourceInterface {

  /**
   * @return list<array{
   *   project_id:int,
   *   project_label:string,
   *   controller_actions:?array,
   *   early_warning:?array,
   *   financial_control:?array
   * }>
   */
  public function activeProjects(): array;

}
