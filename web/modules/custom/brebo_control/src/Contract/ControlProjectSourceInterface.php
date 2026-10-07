<?php

declare(strict_types=1);

namespace Drupal\brebo_control\Contract;

/** Source boundary for active BREBO project ids. */
interface ControlProjectSourceInterface {

  /** @return list<int> */
  public function activeProjectIds(): array;

}
