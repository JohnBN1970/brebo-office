<?php

declare(strict_types=1);

namespace Brebo\Office\Finance;

use PDO;

/**
 * Read-only Office-native listing of legacy sales invoice drafts.
 *
 * The legacy source connection is deliberately separate from the Office
 * identity connection. Standalone drafts (project_nid=0) are not returned
 * until a separately authorized debtor-based access policy exists.
 */
final class OfficeSalesInvoiceDraftList
{
    public function __construct(
        private readonly PDO $officeDb,
        private readonly PDO $salesDb
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function forOperator(string $operatorId, ?int $officeProjectId = null): array
    {
        $access = new OfficeFinanceAccess($this->officeDb, $operatorId);
        if (!$access->allows('read')) {
            throw new \DomainException('Geen Finance-toegang');
        }

        $visible = $access->visibleProjects();
        $sourceIds = [];
        foreach ($visible as $sourceId => $project) {
            if ($officeProjectId === null || (int) $project['office_project_id'] === $officeProjectId) {
                $sourceIds[] = (int) $sourceId;
            }
        }

        if ($officeProjectId !== null && $sourceIds === []) {
            throw new \OutOfBoundsException('Project niet toegankelijk');
        }

        if ($sourceIds === []) {
            return [];
        }

        if (OfficeSalesInvoiceSourceMap::missingTables($this->salesDb) !== []) {
            throw new \RuntimeException('Verkoopfactuurbron niet beschikbaar');
        }

        $placeholders = implode(',', array_fill(0, count($sourceIds), '?'));
        $query = $this->salesDb->prepare(
            'SELECT d.* FROM ' . OfficeSalesInvoiceSourceMap::DRAFT
            . ' d WHERE d.status = ? AND d.project_nid IN (' . $placeholders . ')'
            . ' ORDER BY d.id DESC LIMIT 500'
        );
        $query->execute(array_merge(['draft'], $sourceIds));
        $rows = $query->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $sourceId = (int) $row['project_nid'];
            $row['office_project_id'] = (int) $visible[$sourceId]['office_project_id'];
        }
        unset($row);

        return $rows;
    }
}
