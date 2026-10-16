<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Domain;

/** Provenance class of a project-scope statement. */
enum ProjectScopeStatementType: string {
  case REQUESTED = 'requested';
  case OBSERVED = 'observed';
  case DECIDED = 'decided';
}
