<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Domain;

/** Classification system used by a calculation structure. */
enum ClassificationSystem: string {
  case NlSfb = 'nl_sfb';
  case Stabu = 'stabu';
  case Custom = 'custom';
}
