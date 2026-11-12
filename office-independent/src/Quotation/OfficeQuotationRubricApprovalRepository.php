<?php
declare(strict_types=1);

namespace Brebo\Office\Quotation;

use DomainException;
use PDO;
use RuntimeException;

/**
 * Transactional rubric approvals. Requires the separately installed
 * office_quotation_rubric_approvals table. No schema mutations here.
 */
final class OfficeQuotationRubricApprovalRepository
{
    public function __construct(private readonly PDO $db) {}

    public function approve(
        int $quotationId,
        int $operatorId,
        string $rubricKey,
        string $sourceHash,
        array $checks
    ): void {
        require_once __DIR__ . '/OfficeQuotationRubricReview.php';
        if ($quotationId < 1 || $operatorId < 1 || $rubricKey === ''
            || mb_strlen($rubricKey, 'UTF-8') > 200 || $this->db->inTransaction()) {
            throw new DomainException('Invalid approval context');
        }
        // Validate the three confirmations before any write.
        OfficeQuotationRubricReview::approve(
            ['lines' => [['verified' => true]]], $checks, $sourceHash, $operatorId
        );
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "SELECT q.status, q.calculation_id, q.created_by, v.snapshot_json
                 FROM office_quotations q
                 JOIN office_quotation_versions v ON v.quotation_id = q.id
                 WHERE q.id = ? AND q.created_by = ? AND q.status = 'draft'
                 ORDER BY v.version DESC LIMIT 1 FOR UPDATE"
            );
            $stmt->execute([$quotationId, $operatorId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || empty($row['calculation_id'])) throw new RuntimeException('Calc quotation draft not accessible');
            $snapshot = json_decode((string)$row['snapshot_json'], true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($snapshot) || ($snapshot['kind'] ?? '') !== 'calculation_sales_import'
                || !hash_equals((string)($snapshot['source']['source_hash'] ?? ''), $sourceHash)) {
                throw new RuntimeException('Calculation snapshot changed');
            }
            require_once __DIR__ . '/OfficeQuotationRubricBuilder.php';
            $rubrics = OfficeQuotationRubricBuilder::build($snapshot['sales_lines'] ?? []);
            $found = false;
            foreach ($rubrics as $rubric) {
                if ($rubric['rubric'] === $rubricKey) { $found = true; break; }
            }
            if (!$found) throw new RuntimeException('Rubric not present in Calc snapshot');
            $write = $this->db->prepare(
                'INSERT INTO office_quotation_rubric_approvals
                 (quotation_id, rubric_key, source_hash, reviewer_id, description_checked,
                  quantities_checked, prices_checked, approved_at)
                 VALUES (?, ?, ?, ?, 1, 1, 1, UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE source_hash = VALUES(source_hash),
                  reviewer_id = VALUES(reviewer_id), description_checked = 1,
                  quantities_checked = 1, prices_checked = 1, approved_at = UTC_TIMESTAMP()'
            );
            $write->execute([$quotationId, $rubricKey, $sourceHash, $operatorId]);
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function approvedKeys(int $quotationId, int $operatorId, string $sourceHash): array
    {
        if ($quotationId < 1 || $operatorId < 1 || !preg_match('/^[a-f0-9]{64}$/D', $sourceHash)) {
            throw new DomainException('Invalid approval lookup');
        }
        $stmt = $this->db->prepare(
            "SELECT a.rubric_key FROM office_quotation_rubric_approvals a
             JOIN office_quotations q ON q.id = a.quotation_id
             WHERE a.quotation_id = ? AND q.created_by = ? AND q.status = 'draft'
               AND a.source_hash = ? AND a.description_checked = 1
               AND a.quantities_checked = 1 AND a.prices_checked = 1"
        );
        $stmt->execute([$quotationId, $operatorId, $sourceHash]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
