<?php
declare(strict_types=1);

namespace Brebo\Office\Project;

/** Immutable identity of an existing Office-native project. */
final class OfficeProjectIdentity {
  public function __construct(
    public readonly int $projectId,
    public readonly string $projectCode,
  ) {
    if ($projectId < 1 || trim($projectCode) === '') {
      throw new \InvalidArgumentException('Invalid native project identity.');
    }
  }
}
