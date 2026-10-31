<?php

declare(strict_types=1);

namespace Brebo\Office\Offer;

use PDO;

/**
 * Storage-only linkage. Caller MUST authorize the operator for the project.
 * No contract amount, payment term or change-order approval is modified.
 */
final class OfficeOfferChangeLinkRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function link(int $acceptanceId, int $operatorId): int
    {
        if ($acceptanceId < 1 || $operatorId < 1) {
            throw new \InvalidArgumentException('Invalid acceptance or operator');
        }
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'SELECT a.offer_id, o.office_project_id, o.change_order_id,
                        o.change_type, o.status AS offer_status,
                        v.status AS version_status,
                        c.office_project_id AS contract_project,
                        ch.change_type AS actual_change_type,
                        ch.status AS change_status
                 FROM office_offer_acceptances a
                 JOIN office_offers o ON o.id = a.offer_id
                 JOIN office_offer_versions v ON v.id = a.offer_version_id
                     AND v.offer_id = o.id
                 JOIN office_project_change_orders ch ON ch.id = o.change_order_id
                 JOIN office_project_contracts c ON c.id = ch.contract_id
                 WHERE a.id = :acceptance_id FOR UPDATE'
            );
            $stmt->execute([':acceptance_id' => $acceptanceId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)
                || $row['offer_status'] !== 'accepted'
                || $row['version_status'] !== 'accepted'
                || $row['office_project_id'] === null
                || (int) $row['office_project_id'] !== (int) $row['contract_project']
                || $row['change_type'] !== $row['actual_change_type']
                || $row['change_status'] !== 'draft') {
                throw new \DomainException('Accepted offer/change order mismatch');
            }
            $insert = $this->db->prepare(
                'INSERT INTO office_offer_change_links
                 (acceptance_id, offer_id, change_order_id, office_project_id,
                  linked_by_operator_id)
                 VALUES (:acceptance_id, :offer_id, :change_order_id,
                         :project_id, :operator_id)'
            );
            $insert->execute([
                ':acceptance_id' => $acceptanceId,
                ':offer_id' => $row['offer_id'],
                ':change_order_id' => $row['change_order_id'],
                ':project_id' => $row['office_project_id'],
                ':operator_id' => $operatorId,
            ]);
            $id = (int) $this->db->lastInsertId();
            $this->db->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
