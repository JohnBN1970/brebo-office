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
    /** Return only drafts owned by the authenticated operator. */
    public function listOwn(int $operatorId): array
    {
        $stmt = $this->db->prepare(
            "SELECT q.id, q.title, q.quotation_type, q.updated_at, r.display_name AS relation_name
             FROM office_quotations q
             JOIN office_crm_relations r ON r.id = q.relation_id
             WHERE q.created_by = ? AND q.status = 'draft'
             ORDER BY q.updated_at DESC LIMIT 100"
        );
        $stmt->execute([$operatorId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOwnDraft(int $quotationId, int $operatorId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id, relation_id, calculation_id, calculation_version, quotation_type, title, status
             FROM office_quotations
             WHERE id = ? AND created_by = ? AND status = 'draft' LIMIT 1"
        );
        $stmt->execute([$quotationId, $operatorId]);
        $quote = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$quote) return null;
        $sections = $this->db->prepare(
            'SELECT section_key, body FROM office_quotation_sections WHERE quotation_id = ? ORDER BY sort_order'
        );
        $sections->execute([$quotationId]);
        $quote['sections'] = [];
        foreach ($sections->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $quote['sections'][$row['section_key']] = $row['body'];
        }
        return $quote;
    }

    public function updateOwnDraft(int $quotationId, int $operatorId, string $title, array $sections): void
    {
        if ($quotationId <= 0 || $operatorId <= 0 || trim($title) === '' || mb_strlen($title, 'UTF-8') > 255) throw new InvalidArgumentException('Invalid quotation');
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "SELECT quotation_type FROM office_quotations
                 WHERE id = ? AND created_by = ? AND status = 'draft' FOR UPDATE"
            );
            $stmt->execute([$quotationId, $operatorId]);
            $type = $stmt->fetchColumn();
            if ($type === false) throw new RuntimeException('Draft not accessible');
            $allowed = OfficeQuotationComposer::sections((string)$type);
            foreach ($sections as $key => $body) {
                if (!in_array($key, $allowed, true) || !is_string($body)
                    || mb_strlen($body, 'UTF-8') > 50000) {
                    throw new InvalidArgumentException('Invalid section');
                }
            }
            $rename = $this->db->prepare("UPDATE office_quotations SET title = ? WHERE id = ? AND created_by = ? AND status = 'draft'");
            $rename->execute([trim($title), $quotationId, $operatorId]);
            $stmt = $this->db->prepare(
                'UPDATE office_quotation_sections SET body = ?, provenance = ? WHERE quotation_id = ? AND section_key = ?'
            );
            foreach ($sections as $key => $body) {
                $stmt->execute([$body, 'manual', $quotationId, $key]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

}
