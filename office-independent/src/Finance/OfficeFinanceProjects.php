<?php

declare(strict_types=1);

namespace Brebo\Office\Finance;

use Brebo\Finance\Contract\ProjectReferenceGatewayInterface;

/** Request-scoped authorized project snapshot; no source-system entity lookup. */
final class OfficeFinanceProjects implements ProjectReferenceGatewayInterface {
  public function __construct(private readonly int $actorId, private readonly array $projects) {}
  public function exists(int $projectId): bool { return isset($this->projects[$projectId]); }
  public function canView(int $projectId): bool { return $this->exists($projectId); }
  public function canViewAs(int $projectId, int $actorUid): bool { return $actorUid === $this->actorId && $this->exists($projectId); }
  public function label(int $projectId): ?string { return $this->projects[$projectId]['title'] ?? null; }
  public function viewableIds(int $actorUid): array { return $actorUid === $this->actorId ? array_keys($this->projects) : []; }
}
