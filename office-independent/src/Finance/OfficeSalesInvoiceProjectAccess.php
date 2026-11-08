<?php

declare(strict_types=1);

namespace Brebo\Office\Finance;

use PDO;

/**
 * Authorization boundary for sales invoices attached to Office projects.
 * The legacy project_nid is never treated as an Office project id.
 */
final class OfficeSalesInvoiceProjectAccess
{
    public function __construct(private readonly PDO $officeDb)
    {
    }

    public function requireVisibleProject(string $operatorId, int $officeProjectId): void
    {
        if ($officeProjectId < 1) {
            throw new \OutOfBoundsException('Project niet toegankelijk');
        }

        $access = new OfficeFinanceAccess($this->officeDb, $operatorId);
        if (!$access->allows('read')) {
            throw new \DomainException('Geen Finance-toegang');
        }

        foreach ($access->draftProjects() as $project) {
            if ((int) $project['office_project_id'] === $officeProjectId) {
                return;
            }
        }

        throw new \OutOfBoundsException('Project niet toegankelijk');
    }

    /**
     * Only explicitly verified source-to-Office mappings are accepted.
     * Source project id 0 (standalone invoice) has no project access grant.
     */
    public function requireMappedSourceProject(string $operatorId, int $sourceProjectId): int
    {
        if ($sourceProjectId < 1) {
            throw new \OutOfBoundsException('Projectkoppeling ontbreekt');
        }

        $access = new OfficeFinanceAccess($this->officeDb, $operatorId);
        if (!$access->allows('read')) {
            throw new \DomainException('Geen Finance-toegang');
        }

        $projects = $access->visibleProjects();
        if (!isset($projects[$sourceProjectId])) {
            throw new \OutOfBoundsException('Projectkoppeling niet toegankelijk');
        }

        return (int) $projects[$sourceProjectId]['office_project_id'];
    }
}
