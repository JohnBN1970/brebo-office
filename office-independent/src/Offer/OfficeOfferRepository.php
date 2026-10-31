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

    /**
     * Issue a specific immutable draft version. Does not approve any change order.
     * The caller must have verified authorization for this offer.
     */
    public function issueVersion(int $offerId, int $versionId): void
    {
        if ($offerId < 1 || $versionId < 1) {
            throw new \InvalidArgumentException('Invalid offer version');
        }
        $this->db->beginTransaction();
        try {
            $offerLock = $this->db->prepare(
                'SELECT status FROM office_offers WHERE id = :id FOR UPDATE'
            );
            $offerLock->execute([':id' => $offerId]);
            if ($offerLock->fetchColumn() !== 'draft') {
                throw new \DomainException('Offer must be draft');
            }
            $versionLock = $this->db->prepare(
                'SELECT status FROM office_offer_versions
                 WHERE id = :version_id AND offer_id = :offer_id FOR UPDATE'
            );
            $versionLock->execute([':version_id' => $versionId, ':offer_id' => $offerId]);
            if ($versionLock->fetchColumn() !== 'draft') {
                throw new \DomainException('Version must belong to offer and be draft');
            }
            $markVersion = $this->db->prepare(
                "UPDATE office_offer_versions SET status = 'issued'
                 WHERE id = :version_id AND offer_id = :offer_id AND status = 'draft'"
            );
            $markVersion->execute([':version_id' => $versionId, ':offer_id' => $offerId]);
            if ($markVersion->rowCount() !== 1) {
                throw new \DomainException('Version issue conflict');
            }
            $markOffer = $this->db->prepare(
                "UPDATE office_offers SET status = 'issued'
                 WHERE id = :offer_id AND status = 'draft'"
            );
            $markOffer->execute([':offer_id' => $offerId]);
            if ($markOffer->rowCount() !== 1) {
                throw new \DomainException('Offer issue conflict');
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
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
