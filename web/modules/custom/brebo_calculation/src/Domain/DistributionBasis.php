<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Domain;

/** Basis used to distribute a distributed calculation row. */
enum DistributionBasis: string {
  case DirectCost = 'direct_cost';
  case Labour = 'labour';
  case Material = 'material';
  case Quantity = 'quantity';
  case Equal = 'equal';
  case Manual = 'manual';
}
