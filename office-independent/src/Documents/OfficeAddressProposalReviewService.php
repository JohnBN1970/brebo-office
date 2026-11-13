<?php
declare(strict_types=1);
namespace Brebo\Office\Documents;

use PDO;
use InvalidArgumentException;
use RuntimeException;

/**
 * Review-only decisions. Never writes canonical buildings, addresses or residents.
 * Caller must authorize the document and project before calling.
 */
final class OfficeAddressProposalReviewService
{
    public function __construct(private readonly PDO $db) {}

    public function pendingForDocument(int $documentId): array
    {
        if ($documentId < 1) throw new InvalidArgumentException('invalid_document_id');
        $query = $this->db->prepare(
            'SELECT id, source_location, address_line, postal_code, city, resident_name,
                    resident_email, resident_phone, suggested_building_id, match_status, review_status
             FROM office_document_address_proposals
             WHERE document_id = ? ORDER BY id LIMIT 1000'
        );
        $query->execute([$documentId]);
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    public function decide(int $documentId, int $proposalId, string $decision): bool
    {
        if ($documentId < 1 || $proposalId < 1 || !in_array($decision, ['accepted', 'rejected'], true)) {
            throw new InvalidArgumentException('invalid_review_decision');
        }
        // Accepting an exact match acknowledges evidence only; it does not
        // create a building/resident or authorize an ambiguous candidate.
        $query = $this->db->prepare(
            "UPDATE office_document_address_proposals
             SET review_status = ?
             WHERE document_id = ? AND id = ? AND review_status = 'pending'
               AND (? = 'rejected' OR (match_status = 'exact_candidate' AND suggested_building_id IS NOT NULL))"
        );
        $query->execute([$decision, $documentId, $proposalId, $decision]);
        return $query->rowCount() === 1;
    }
}
