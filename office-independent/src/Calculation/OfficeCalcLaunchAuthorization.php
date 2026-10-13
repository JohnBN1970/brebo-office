<?php

declare(strict_types=1);

namespace Brebo\Office\Calculation;

use Brebo\Office\Intake\WebsiteCrmOperatorContext;

/**
 * Fail-closed Calc launch authorization contract.
 * Caller must verify project/calculation ownership in trusted Office storage.
 */
final class OfficeCalcLaunchAuthorization {
  public static function requireAuthorized(
    WebsiteCrmOperatorContext $operator,
    ?int $actorId,
    int $projectId,
    int $calculationId,
    bool $projectAccessible,
    bool $calculationBelongsToProject,
  ): int {
    if ($operator->operatorId === '' || $actorId === null || $actorId < 1
      || $projectId < 1 || $calculationId < 1
      || !$projectAccessible || !$calculationBelongsToProject) {
      throw new \DomainException('Calc launch denied.');
    }
    return $actorId;
  }
}
