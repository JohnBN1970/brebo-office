<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Domain;

/** Commercial disposition of one project-scope subject. */
enum ProjectScopeDisposition: string {
  case UNRESOLVED = 'unresolved';
  case IN_SCOPE = 'in_scope';
  case ALTERNATIVE = 'alternative';
  case THIRD_PARTY = 'third_party';
  case DECLINED = 'declined';
  case OUT_OF_SCOPE = 'out_of_scope';
}
