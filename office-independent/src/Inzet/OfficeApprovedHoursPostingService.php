<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;

/**
 * Posts only a decision already stored in the OnSite decision ledger.
 * No request-supplied duration, project, person or reviewer can override it.
 */
final class OfficeApprovedHoursPostingService
{
    public function __construct(private PDO $db) {}

    public function postPersistedApproval(string $decisionReference, string $authenticatedOperatorId): int
    {
        if ($this->db->inTransaction()) {
            throw new \LogicException('Posting must own its transaction');
        }
        $this->db->beginTransaction();
        try {
            $source = (new OfficeApprovedHoursDecisionRepository($this->db))
                ->verified($decisionReference, $authenticatedOperatorId);
            $entry = OfficeApprovedHoursEvidence::fromVerifiedDecision(
                $source['session'], $source['decision'], $authenticatedOperatorId
            );
            $stmt = $this->db->prepare('SELECT id, source_sha256 FROM office_inzet_approved_hours
                WHERE source_kind = :kind AND source_reference = :reference LIMIT 1 FOR UPDATE');
            $stmt->execute([':kind'=>$entry['source_kind'], ':reference'=>$entry['source_reference']]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($existing)) {
                if (!hash_equals((string)$existing['source_sha256'], $entry['source_sha256'])) {
                    throw new \DomainException('Previously booked approval has changed');
                }
                $id = (int)$existing['id'];
            } else {
                $id = (new OfficeApprovedHoursBooking($this->db))->append($entry, $authenticatedOperatorId);
            }
            $this->db->commit();
            return $id;
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }
}
