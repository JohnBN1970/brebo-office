<?php
declare(strict_types=1);

namespace Brebo\Office\WorkingBudget;

use PDO;

/**
 * Native project-facing read model. Caller must already authorize access to
 * the project; procurement/Finance inputs must be ACL-scoped upstream.
 * No direct HTTP route, schema mutation or external ledger.
 */
final class ProjectBudgetReadModel
{
    public function __construct(private readonly PDO $db) {}

    public function forAuthorizedProject(int $projectId, ?array $procurement = null, ?array $finance = null): array
    {
        if ($projectId < 1) throw new \InvalidArgumentException('Invalid project.');
        $baseline = (new LockedBaselineLines($this->db))->forProject($projectId);
        $kpis = WorkingBudgetKpiProjection::fromSources($baseline, $procurement, $finance);
        return [
            'project_id'=>$projectId,
            'baseline_id'=>$baseline['baseline_id'] ?? null,
            'baseline_version'=>$baseline['version_number'] ?? null,
            'baseline_locked_at'=>$baseline['locked_at'] ?? null,
            'currency'=>'EUR',
            'vat_basis'=>'excluding',
            'kpis'=>$kpis,
            'cost_lines'=>$baseline['lines'] ?? [],
            'source_status'=>[
                'baseline'=>$baseline === null ? 'missing' : 'locked',
                'procurement'=>$procurement === null ? 'not_connected' : 'provided',
                'finance'=>$finance === null ? 'not_connected' : ($finance['state'] ?? 'incomplete'),
                'labor'=>'not_connected',
                'remaining_work'=>'not_connected',
            ],
        ];
    }
}
