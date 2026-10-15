<?php

declare(strict_types=1);

namespace Brebo\Office\Project;

use DomainException;

/** Calc-scoped project context; not a general project dossier permission. */
final class OfficeAuthorizedProjectContext {
  public function __construct(
    private readonly OfficeProjectAccess $access,
    private readonly OfficeCanonicalProjectContextClient $client,
  ) {}

  public function getForOperator(string $operatorId, int $projectId): OfficeCanonicalProjectContext {
    if ($projectId < 1 || !$this->access->canRead($operatorId, new OfficeProjectIdentity($projectId, ''))) {
      throw new DomainException('No access to project context.');
    }
    return $this->client->get($projectId);
  }
}
