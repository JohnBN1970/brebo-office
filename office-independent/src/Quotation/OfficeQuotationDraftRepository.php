<?php
declare(strict_types=1);

namespace Brebo\Office\Quotation;

use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Draft-only persistence adapter. Schema must be verified before constructing.
 * No approval, dispatch, acceptance or finance side effects.
 */
final class OfficeQuotationDraftRepository
{
    public function __construct(private PDO $db) {}

    public function create(int $relationId, int $operatorId, string $type, string $title, array $sections): int
    {
        if ($relationId <= 0 || $operatorId <= 0 || trim($title) === ''
            || mb_strlen($title, 'UTF-8') > 255) {
            throw new InvalidArgumentException('Invalid quotation identity');
        }
        $allowed = OfficeQuotationComposer::sections($type);
        foreach ($sections as $key => $text) {
            if (!in_array($key, $allowed, true) || !is_string($text) || mb_strlen($text, 'UTF-8') > 50000) {
                throw new InvalidArgumentException('Invalid quotation section');
            }
        }
        $this->db->beginTransaction();
        try {
            $relation = $this->db->prepare("SELECT id FROM office_crm_relations WHERE id = ? AND status = 'active' LIMIT 1");
            $relation->execute([$relationId]);
            if ($relation->fetchColumn() === false) {
                throw new InvalidArgumentException('Relation not active');
            }
            $insert = $this->db->prepare(
                "INSERT INTO office_quotations (relation_id, quotation_type, title, status, created_by) VALUES (?, ?, ?, 'draft', ?)"
            );
            $insert->execute([$relationId, $type, trim($title), $operatorId]);
            $id = (int) $this->db->lastInsertId();
            $sectionInsert = $this->db->prepare(
                'INSERT INTO office_quotation_sections (quotation_id, section_key, sort_order, enabled, body, provenance) VALUES (?, ?, ?, 1, ?, ?)'
            );
            foreach ($allowed as $index => $key) {
                $sectionInsert->execute([$id, $key, $index, $sections[$key] ?? '', 'manual']);
            }
            $this->db->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function updateSection(int $quotationId, string $key, string $body): void
    {
        if ($quotationId <= 0 || mb_strlen($body, 'UTF-8') > 50000) {
            throw new InvalidArgumentException('Invalid section');
        }
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT quotation_type, status FROM office_quotations WHERE id = ? FOR UPDATE');
            $stmt->execute([$quotationId]);
            $quotation = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$quotation || $quotation['status'] !== 'draft'
                || !in_array($key, OfficeQuotationComposer::sections((string) $quotation['quotation_type']), true)) {
                throw new RuntimeException('Quotation is not an editable draft');
            }
            $update = $this->db->prepare(
                'UPDATE office_quotation_sections SET body = ?, provenance = ? WHERE quotation_id = ? AND section_key = ?'
            );
            $update->execute([$body, 'manual', $quotationId, $key]);
            if ($update->rowCount() === 0) {
                $exists = $this->db->prepare('SELECT 1 FROM office_quotation_sections WHERE quotation_id = ? AND section_key = ?');
                $exists->execute([$quotationId, $key]);
                if ($exists->fetchColumn() === false) throw new RuntimeException('Section missing');
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }
}
