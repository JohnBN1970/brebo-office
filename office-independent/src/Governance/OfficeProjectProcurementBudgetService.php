<?php
declare(strict_types=1);
namespace Brebo\Office\Governance;

/** Orchestrates the authorized procurement source and canonical locked work budget. */
final class OfficeProjectProcurementBudgetService
{
    public function __construct(
        private readonly OfficeWorkBudgetBaselineReader $baselines,
        private readonly OfficeProcurementOrderSource $procurement
    ) {}

    public function forProject(string $actor, int $projectId, array $buildingIds): array
    {
        if ($actor === '' || $projectId < 1 || $buildingIds === []) {
            throw new \InvalidArgumentException('Geautoriseerde project- en gebouwcontext vereist.');
        }
        // Authorization is enforced by the procurement service for each building.
        // Do not return budget data if that scope check fails.
        $commitment=$this->procurement->awardedProjectCommitment($actor,$projectId,$buildingIds);
        $baseline=$this->baselines->forProject($projectId);
        return OfficeWorkBudgetCommitmentComparison::summarize($baseline,$commitment);
    }
}
