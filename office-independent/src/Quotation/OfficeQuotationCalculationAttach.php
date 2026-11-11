<?php
declare(strict_types=1);

namespace Brebo\Office\Quotation;

use DomainException;
use PDO;
use Throwable;

/**
 * Attach a server-side verified Calc sales snapshot to an owned draft.
 * The caller MUST authenticate the operator and authorize the calculation,
 * its CRM opportunity and relation before invoking this service.
 * Never pass raw browser data as $verifiedSnapshot.
 */
final class OfficeQuotationCalculationAttach
{
    public function __construct(private readonly PDO $db) {}

    public function attach(int $quotationId, int $operatorId, array $verifiedSnapshot): array
    {
        if ($quotationId < 1 || $operatorId < 1 || $this->db->inTransaction()) {
            throw new DomainException('Invalid draft context or active transaction');
        }
        require_once __DIR__ . '/OfficeQuotationCalculationImport.php';
        $prepared = OfficeQuotationCalculationImport::prepare($verifiedSnapshot);
        $this->db->beginTransaction();
        try {
            $lock = $this->db->prepare(
                "SELECT id, version FROM office_quotations
                 WHERE id = ? AND created_by = ? AND status = 'draft' FOR UPDATE"
            );
            $lock->execute([$quotationId, $operatorId]);
            $row = $lock->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) throw new DomainException('Draft not editable by operator');
            $nextVersion = (int)$row['version'] + 1;
            if ($nextVersion < 2 || $nextVersion > 4294967295) throw new DomainException('Invalid next version');
            $snapshot = [
                'kind' => 'calculation_sales_import',
                'source' => [
                    'calculation_id' => $prepared['calculation_id'],
                    'calculation_version' => $prepared['calculation_version'],
                    'source_hash' => $prepared['source_hash'],
                ],
                'sales_lines' => $prepared['sales_lines'],
                'total_ex_vat_cents' => $prepared['total_ex_vat_cents'],
            ];
            $json = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $insert = $this->db->prepare(
                'INSERT INTO office_quotation_versions
                 (quotation_id, version, snapshot_json, snapshot_sha256, created_by)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $insert->execute([$quotationId, $nextVersion, $json, hash('sha256', $json), $operatorId]);
            $update = $this->db->prepare(
                'UPDATE office_quotations
                 SET calculation_id = ?, calculation_version = ?, amount_ex_vat_cents = ?, version = ?
                 WHERE id = ? AND created_by = ? AND status = \'draft\''
            );
            $update->execute([
                $prepared['calculation_id'], $prepared['calculation_version'],
                $prepared['total_ex_vat_cents'], $nextVersion, $quotationId, $operatorId,
            ]);
            if ($update->rowCount() !== 1) throw new DomainException('Draft changed during import');
            $this->db->commit();
            return ['quotation_id' => $quotationId, 'quotation_version' => $nextVersion,
                'calculation_id' => $prepared['calculation_id'],
                'calculation_version' => $prepared['calculation_version'],
                'amount_ex_vat_cents' => $prepared['total_ex_vat_cents']];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }
}
