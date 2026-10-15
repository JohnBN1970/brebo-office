<?php

declare(strict_types=1);

namespace Brebo\Office\Calculation;

use Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository;

/**
 * Office-authoritative, read-only Calc grant check.
 *
 * This service does not expose an HTTP endpoint and must not receive raw
 * Office session cookies from Calc. A separate opaque reference resolver and
 * authenticated server-to-server transport are required before integration.
 */
final class OfficeCalcActiveGrantValidator {

  public function __construct(
    private readonly WebsiteOfficeCrmSessionRepository $sessions,
    private readonly PdoOfficeCalculationPermissionRepository $permissions,
    private readonly OfficeCalcProjectScopeRepository $scopes,
    private readonly OfficeCalcActorIdRepository $actors,
  ) {}

  public function isActive(
    string $officeSessionToken,
    int $expectedActorId,
    int $projectId,
    int $calculationId,
    int $now,
  ): bool {
    if ($expectedActorId < 1 || $projectId < 1 || $calculationId < 1 || $now < 1) {
      return false;
    }
    $operator = $this->sessions->findActive($officeSessionToken, $now);
    if ($operator === null) {
      return false;
    }
    $operatorId = $operator->operatorId;
    $actorId = $this->actors->findActorId($operatorId);
    return $actorId !== null
      && $actorId === $expectedActorId
      && $this->permissions->canCalculate($operatorId)
      && $this->scopes->canLaunch($operatorId, $projectId, $calculationId);
  }
}
