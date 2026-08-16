<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Domain;

/**
 * Canonical behavior types for calculation rows.
 *
 * Cost carriers are deliberately not row types. A normal row may contain
 * labour, material, equipment, subcontracting and other cost components.
 */
enum RuleType: string {
  case Normal = 'normal';
  case Allowance = 'allowance';
  case Option = 'option';
  case Note = 'note';
  case Distributed = 'distributed';
  case Adjustable = 'adjustable';
}
