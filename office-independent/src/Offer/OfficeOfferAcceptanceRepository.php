<?php

declare(strict_types=1);

namespace Brebo\Office\Offer;

use DateTimeImmutable;
use PDO;

/**
 * Records evidence of customer acceptance of an issued offer version.
 * The calling application MUST authorize the operator and verify the evidence.
 * This does not approve a change order, change a contract or invoice a term.
 */
final class OfficeOfferAcceptanceRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    private static function validOperatorId(int|string $id): bool
    {
        return is_int($id) ? $id > 0 : trim($id) !== '' && strlen($id) <= 128;
    }

    public function record(
        int $offerId,
        int $versionId,
        string $acceptedBy,
        string $evidenceReference,
        DateTimeImmutable $acceptedAt,
        int|string $operatorId
    ): int {
        $acceptedBy = trim($acceptedBy);
        $evidenceReference = trim($evidenceReference);
        if ($offerId < 1 || $versionId < 1 || !self::validOperatorId($operatorId)
            || $acceptedBy === '' || mb_strlen($acceptedBy) > 255
            || $evidenceReference === '' || mb_strlen($evidenceReference) > 500) {
            throw new \InvalidArgumentException('Invalid acceptance evidence');
        }
        $this->db->beginTransaction();
        try {
            $offer = $this->db->prepare(
                'SELECT status FROM office_offers WHERE id = :id FOR UPDATE'
            );
            $offer->execute([':id' => $offerId]);
            if ($offer->fetchColumn() !== 'issued') {
                throw new \DomainException('Only issued offers can be accepted');
            }
            $version = $this->db->prepare(
                'SELECT status FROM office_offer_versions
                 WHERE id = :version_id AND offer_id = :offer_id FOR UPDATE'
            );
            $version->execute([':version_id' => $versionId, ':offer_id' => $offerId]);
            if ($version->fetchColumn() !== 'issued') {
                throw new \DomainException('Only the issued offer version can be accepted');
            }
            $insert = $this->db->prepare(
                'INSERT INTO office_offer_acceptances
                 (offer_id, offer_version_id, accepted_by, evidence_reference,
                  accepted_at, recorded_by_operator_id)
                 VALUES (:offer_id, :version_id, :accepted_by, :evidence,
                         :accepted_at, :operator_id)'
            );
            $insert->execute([
                ':offer_id' => $offerId,
                ':version_id' => $versionId,
                ':accepted_by' => $acceptedBy,
                ':evidence' => $evidenceReference,
                ':accepted_at' => $acceptedAt->format('Y-m-d H:i:s'),
                ':operator_id' => $operatorId,
            ]);
            $id = (int) $this->db->lastInsertId();
            $updateVersion = $this->db->prepare(
                "UPDATE office_offer_versions SET status = 'accepted'
                 WHERE id = :version_id AND offer_id = :offer_id AND status = 'issued'"
            );
            $updateVersion->execute([':version_id' => $versionId, ':offer_id' => $offerId]);
            $updateOffer = $this->db->prepare(
                "UPDATE office_offers SET status = 'accepted'
                 WHERE id = :offer_id AND status = 'issued'"
            );
            $updateOffer->execute([':offer_id' => $offerId]);
            if ($updateVersion->rowCount() !== 1 || $updateOffer->rowCount() !== 1) {
                throw new \DomainException('Acceptance state conflict');
            }
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
