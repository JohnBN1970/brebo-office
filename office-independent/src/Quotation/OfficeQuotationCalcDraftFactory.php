<?php
declare(strict_types=1);

namespace Brebo\Office\Quotation;

use DomainException;
use PDO;
use Throwable;

/**
 * Creates an Office quotation draft from a server-verified Calc sales snapshot.
 * The caller must verify authenticated user, source Calc ownership, relation,
 * opportunity and exact source revision before calling this method.
 * No project creation, acceptance or financial posting occurs here.
 */
final class OfficeQuotationCalcDraftFactory
{
    public function __construct(private readonly PDO $db) {}

    public function create(
        int $relationId,
        int $operatorId,
        string $title,
        array $verifiedSnapshot,
        string $type = 'project',
        ?int $opportunityId = null,
        ?callable $bindBudget = null
    ): int {
        require_once __DIR__ . '/OfficeQuotationComposer.php';
        require_once __DIR__ . '/OfficeQuotationCalculationImport.php';
        require_once __DIR__ . '/OfficeQuotationRubricBuilder.php';
        if ($relationId < 1 || $operatorId < 1 || trim($title) === ''
            || mb_strlen($title, 'UTF-8') > 255 || $this->db->inTransaction()) {
            throw new DomainException('Invalid quotation creation context');
        }
        $chapters = OfficeQuotationComposer::sections($type);
        if (!in_array('price', $chapters, true)) {
            throw new DomainException('Quotation template has no price chapter');
        }
        $source = OfficeQuotationCalculationImport::prepare($verifiedSnapshot);
        $this->db->beginTransaction();
        try {
            $relation = $this->db->prepare(
                "SELECT id FROM office_crm_relations WHERE id = ? AND status = 'active' LIMIT 1"
            );
            $relation->execute([$relationId]);
            if ($relation->fetchColumn() === false) throw new DomainException('CRM relation not active');
            $insert = $this->db->prepare(
                "INSERT INTO office_quotations
                 (relation_id, opportunity_id, calculation_id, calculation_version, quotation_type, title,
                  status, version, amount_ex_vat_cents, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, 'draft', 1, ?, ?)"
            );
            $insert->execute([
                $relationId, $opportunityId, $source['calculation_id'], $source['calculation_version'],
                $type, trim($title), $source['total_ex_vat_cents'], $operatorId,
            ]);
            $id = (int)$this->db->lastInsertId();
            $sectionInsert = $this->db->prepare(
                'INSERT INTO office_quotation_sections
                 (quotation_id, section_key, sort_order, enabled, body, provenance)
                 VALUES (?, ?, ?, 1, ?, ?)'
            );
            $priceLines = [];
            foreach ($source['sales_lines'] as $item) {
                $priceLines[] = $item['description'] . ' — '
                    . number_format($item['quantity'], 2, ',', '.') . ' ' . $item['unit']
                    . ' × € ' . number_format($item['unit_sales_price_ex_vat'], 2, ',', '.')
                    . ' — € ' . number_format($item['amount_ex_vat_cents'] / 100, 2, ',', '.');
            }
            $priceLines[] = 'Totaal exclusief btw: € '
                . number_format($source['total_ex_vat_cents'] / 100, 2, ',', '.');
            $priceBody = implode("\n", $priceLines);
            if (mb_strlen($priceBody, 'UTF-8') > 50000) {
                throw new DomainException('Calculation price chapter exceeds maximum length');
            }
            foreach ($chapters as $index => $key) {
                $sectionInsert->execute([
                    $id, $key, $index, $key === 'price' ? $priceBody : '',
                    $key === 'price' ? 'calculation' : 'manual',
                ]);
            }
            $snapshot = [
                'kind' => 'calculation_sales_import',
                'source' => [
                    'calculation_id' => $source['calculation_id'],
                    'calculation_version' => $source['calculation_version'],
                    'source_hash' => $source['source_hash'],
                ],
                'sales_lines' => $source['sales_lines'],
                'rubric_proposals' => OfficeQuotationRubricBuilder::build($source['sales_lines']),
                'total_ex_vat_cents' => $source['total_ex_vat_cents'],
            ];
            $json = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $version = $this->db->prepare(
                'INSERT INTO office_quotation_versions
                 (quotation_id, version, snapshot_json, snapshot_sha256, created_by)
                 VALUES (?, 1, ?, ?, ?)'
            );
            $version->execute([$id, $json, hash('sha256', $json), $operatorId]);
            if ($bindBudget !== null) $bindBudget($this->db,$id,$source);
            $this->db->commit();
            return $id;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }
}
