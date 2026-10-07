<?php

declare(strict_types=1);

namespace Drupal\brebo_article\Contract;

/** Clock boundary for SALES005 import timestamps. */
interface Sales005ClockInterface {

  public function now(): int;

}
