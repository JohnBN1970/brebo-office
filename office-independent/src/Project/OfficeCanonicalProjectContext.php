<?php
declare(strict_types=1);

namespace Brebo\Office\Project;

/** Read-only project context; the Office projects table remains the source. */
final class OfficeCanonicalProjectContext {
  public function __construct(
    public readonly OfficeProjectIdentity $identity,
    public readonly string $title,
    public readonly string $status,
  ) {}
}
