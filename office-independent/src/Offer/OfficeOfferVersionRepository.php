<?php

declare(strict_types=1);

namespace Brebo\Office\Offer;

use PDO;
use JsonException;

/**
 * Append-only version snapshots. Issuing or accepting an offer requires
 * a separate authorized workflow; this class cannot mutate contracts.
 */
final class OfficeOfferVersionRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function createDraftVersion(
        int $offerId,
        array $content,
        ?string $commercialInstalmentText = null
    ): int {
        if ($offerId < 1) {
            throw new \InvalidArgumentException('Invalid offer');
        }
        try {
            $json = json_encode($content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $e) {
            throw new \InvalidArgumentException('Invalid offer content', 0, $e);
        }

        $this->db->beginTransaction();
        try {
            $lock = $this->db->prepare(
                'SELECT id, status FROM office_offers WHERE id = :id FOR UPDATE'
            );
            $lock->execute([':id' => $offerId]);
            $offer = $lock->fetch(PDO::FETCH_ASSOC);
            if (!is_array($offer) || $offer['status'] !== 'draft') {
                throw new \DomainException('Only draft offers may receive new versions');
            }

            $number = $this->db->prepare(
                'SELECT COALESCE(MAX(version_number), 0) + 1
                 FROM office_offer_versions WHERE offer_id = :offer_id'
            );
            $number->execute([':offer_id' => $offerId]);
            $next = (int) $number->fetchColumn();

            $insert = $this->db->prepare(
                'INSERT INTO office_offer_versions
                 (offer_id, version_number, content_json,
                  commercial_instalment_text, status)
                 VALUES (:offer_id, :version_number, :content_json,
                         :instalment_text, :status)'
            );
            $insert->execute([
                ':offer_id' => $offerId,
                ':version_number' => $next,
                ':content_json' => $json,
                ':instalment_text' => $commercialInstalmentText,
                ':status' => 'draft',
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

    public function forOffer(int $offerId): array
    {
        if ($offerId < 1) {
            throw new \InvalidArgumentException('Invalid offer');
        }
        $statement = $this->db->prepare(
            'SELECT id, offer_id, version_number, content_json,
                    commercial_instalment_text, status, created_at
             FROM office_offer_versions WHERE offer_id = :offer_id
             ORDER BY version_number DESC'
        );
        $statement->execute([':offer_id' => $offerId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
