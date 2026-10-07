<?php

declare(strict_types=1);

namespace Drupal\brebo_control\Contract;

/** Source boundary for active project control facts used by the portfolio. */
interface PortfolioProjectSourceInterface {

  /**
   * @return array<int, array{
   *   project_id:int,
   *   project:string,
   *   warning:array<string,mixed>,
   *   trend:array<string,mixed>
   * }>
   */
  public function activeProjectFacts(): array;

}
