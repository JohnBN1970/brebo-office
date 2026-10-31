<?php

declare(strict_types=1);

namespace Brebo\Office\Offer;

use PDO;

/**
 * Storage boundary only. Caller must authorize relation/project access before use.
 * Never accepts a contract or term write.
 */
final class OfficeOfferRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function createDraft(OfficeOfferDraft $draft): int
    {
        $statement = $this->db->prepare(
            'INSERT INTO office_offers
             (relation_id, opportunity_id, office_project_id,
              calculation_id, calculation_version, change_order_id,
              change_type, title, status)
             VALUES
             (:relation_id, :opportunity_id, :project_id,
              :calculation_id, :calculation_version, :change_order_id,
              :change_type, :title, :status)'
        );
        $statement->execute([
            ':relation_id' => $draft->relationId,
            ':opportunity_id' => $draft->opportunityId,
            ':project_id' => $draft->projectId,
            ':calculation_id' => $draft->calculationId,
            ':calculation_version' => $draft->calculationVersion,
            ':change_order_id' => $draft->changeOrderId,
            ':change_type' => $draft->changeType,
            ':title' => trim($draft->title),
            ':status' => 'draft',
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function forProject(int $projectId): array
    {
        if ($projectId < 1) {
            throw new \InvalidArgumentException('Invalid project');
        }
        $statement = $this->db->prepare(
            'SELECT id, relation_id, opportunity_id, office_project_id,
                    calculation_id, calculation_version, change_order_id,
                    change_type, title, status, created_at
             FROM office_offers WHERE office_project_id = :project_id
             ORDER BY id DESC'
        );
        $statement->execute([':project_id' => $projectId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function forRelation(int $relationId): array
    {
        if ($relationId < 1) {
            throw new \InvalidArgumentException('Invalid relation');
        }
        $statement = $this->db->prepare(
            'SELECT id, relation_id, opportunity_id, office_project_id,
                    calculation_id, calculation_version, change_order_id,
                    change_type, title, status, created_at
             FROM office_offers WHERE relation_id = :relation_id
             ORDER BY id DESC'
        );
        $statement->execute([':relation_id' => $relationId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
