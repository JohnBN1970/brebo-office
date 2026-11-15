<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;

/**
 * Caller owns authorization, identity matching and transaction.
 * Append-only import: identical evidence is idempotent; changed evidence fails closed.
 */
final class OfficeExternalHoursClaimRepository
{
    public function __construct(private PDO $db) {}

    public function importInsideTransaction(
        int $projectId,
        int $personId,
        string $workDate,
        int $claimedSeconds,
        string $supplierReference,
        string $documentSha256,
        string $lineReference,
        string $lineSha256,
        string $importedBy
    ): int {
        if (!$this->db->inTransaction()) throw new \LogicException('Caller-owned transaction required');
        if ($projectId < 1 || $personId < 1 || $claimedSeconds < 1 || $claimedSeconds > 86400
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/D',$workDate)
            || !checkdate((int)substr($workDate,5,2),(int)substr($workDate,8,2),(int)substr($workDate,0,4))
            || strlen($supplierReference) < 1 || strlen($supplierReference) > 128
            || strlen($lineReference) < 1 || strlen($lineReference) > 128
            || strlen($importedBy) < 1 || strlen($importedBy) > 128
            || !preg_match('/^[a-f0-9]{64}$/D',$documentSha256)
            || !preg_match('/^[a-f0-9]{64}$/D',$lineSha256)) {
            throw new \DomainException('Invalid external timesheet evidence');
        }
        $find=$this->db->prepare('SELECT * FROM office_inzet_external_hours_claims
            WHERE source_document_sha256=? AND source_line_reference=? FOR UPDATE');
        $find->execute([$documentSha256,$lineReference]);
        $existing=$find->fetch(PDO::FETCH_ASSOC);
        if ($existing !== false) {
            if ((int)$existing['project_id']!==$projectId || (int)$existing['person_id']!==$personId
                || (string)$existing['work_date']!==$workDate
                || (int)$existing['claimed_seconds']!==$claimedSeconds
                || (string)$existing['supplier_reference']!==$supplierReference
                || !hash_equals((string)$existing['source_line_sha256'],$lineSha256)) {
                throw new \DomainException('External timesheet source line changed');
            }
            return (int)$existing['id'];
        }
        $add=$this->db->prepare('INSERT INTO office_inzet_external_hours_claims
            (project_id,person_id,work_date,claimed_seconds,supplier_reference,
             source_document_sha256,source_line_reference,source_line_sha256,imported_by)
            VALUES (?,?,?,?,?,?,?,?,?)');
        $add->execute([$projectId,$personId,$workDate,$claimedSeconds,$supplierReference,
            $documentSha256,$lineReference,$lineSha256,$importedBy]);
        return (int)$this->db->lastInsertId();
    }
}
