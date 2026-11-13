<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;

require_once __DIR__.'/OfficeExternalTimesheetCsvReader.php';
require_once __DIR__.'/OfficeExternalHoursClaimRepository.php';

/**
 * Explicitly reviewed CSV rows only. The caller must authenticate/authorize the reviewer,
 * validate project/person/supplier identity and retain the original source document.
 * No automatic payable-hours approval or invoice posting.
 */
final class OfficeExternalTimesheetReviewedImport
{
    public function __construct(private PDO $db) {}

    public function import(
        string $csv,
        array $approvedLineReferences,
        string $reviewer,
        string $delimiter = ';'
    ): array {
        if (!$this->db->inTransaction()) {
            throw new \LogicException('Caller-owned transaction required');
        }
        if ($reviewer==='' || strlen($reviewer)>128) {
            throw new \DomainException('Verified reviewer required');
        }
        $preview=OfficeExternalTimesheetCsvReader::preview($csv,$delimiter);
        $rows=$preview['rows'];
        $indexed=[];
        foreach ($rows as $row) {
            $indexed[$row['source_line_reference']]=$row;
        }
        if ($approvedLineReferences===[] || count($approvedLineReferences)!==count(array_unique($approvedLineReferences))) {
            throw new \DomainException('Explicit unique reviewed line references required');
        }
        foreach ($approvedLineReferences as $ref) {
            if (!is_string($ref) || !isset($indexed[$ref])) {
                throw new \DomainException('Reviewed line not found in source');
            }
        }
        $repository=new OfficeExternalHoursClaimRepository($this->db);
        $ids=[];
        foreach ($approvedLineReferences as $ref) {
            $row=$indexed[$ref];
            $ids[$ref]=$repository->importInsideTransaction(
                $row['project_id'],$row['person_id'],$row['work_date'],$row['claimed_seconds'],
                $row['supplier_reference'],$preview['source_document_sha256'],
                $ref,$row['source_line_sha256'],$reviewer
            );
        }
        return $ids;
    }
}
