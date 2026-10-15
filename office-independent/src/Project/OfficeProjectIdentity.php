<?php

declare(strict_types=1);

namespace Brebo\Office\Project;

use InvalidArgumentException;

/** Immutable reference to the existing brebo_project; not an authorization grant. */
final class OfficeProjectIdentity {
  public function __construct(
    public readonly int $projectId,
    public readonly string $projectCode,
  ) {
    if ($projectId < 1 || strlen($projectCode) > 128) {
      throw new InvalidArgumentException('Invalid canonical project identity.');
    }
  }
}
