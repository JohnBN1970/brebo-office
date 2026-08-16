<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Domain;

/** Structural levels in the calculation tree. */
enum StructureNodeType: string {
  case MainGroup = 'main_group';
  case Paragraph = 'paragraph';
}
