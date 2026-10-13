<?php

declare(strict_types=1);

namespace Brebo\Office\Calculation;

use Brebo\Office\Intake\WebsiteCrmOperatorContext;
use Brebo\Office\Intake\WebsiteCrmOperatorSessionProviderInterface;

/**
 * Orchestrates the server-side Calc launch checks.
 * No HTTP endpoint is exposed by this service.
 */
final class OfficeCalcLaunchService {
  public function __construct(
    private readonly WebsiteCrmOperatorSessionProviderInterface $sessions,
    private readonly PdoOfficeCalculationPermissionRepository $permissions,
    private readonly OfficeCalcActorIdRepository $actors,
    private readonly OfficeCalcProjectScopeRepository $projects,
    private readonly string $sharedSecret,
  ) {}

  public function issue(int $projectId, int $calculationId): string {
    $operator = $this->sessions->currentOperator();
    if (!$operator instanceof WebsiteCrmOperatorContext) {
      throw new \DomainException('Calc launch denied.');
    }
    OfficeCalculationPermission::requireCalculator(
      $operator, $this->permissions->canCalculate($operator->operatorId)
    );
    $actorId = $this->actors->findActorId($operator->operatorId);
    $scopeAllowed = $this->projects->canLaunch($operator->operatorId, $projectId, $calculationId);
    $verifiedActor = OfficeCalcLaunchAuthorization::requireAuthorized(
      $operator, $actorId, $projectId, $calculationId, $scopeAllowed, $scopeAllowed
    );
    return OfficeCalcLaunchToken::issue(
      $calculationId, $projectId, $verifiedActor, $this->sharedSecret
    );
  }
}
