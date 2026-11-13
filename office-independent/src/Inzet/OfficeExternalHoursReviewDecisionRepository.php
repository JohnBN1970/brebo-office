<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;

/**
 * Persist a reviewed decision only inside the caller's authorized transaction.
 * Caller MUST authenticate both operators, verify independent project permissions,
 * source document, invoice evidence and OnSite presence before invoking this method.
 * This service does not post financial entries or mutate any source.
 */
final class OfficeExternalHoursReviewDecisionRepository
{
    public function __construct(private PDO $db) {}

    public function recordInsideTransaction(
        int $claimId,
        string $decisionReference,
        string $expectedSourceLineSha256,
        int $presentSeconds,
        ?int $invoicedSeconds,
        int $payableSeconds,
        string $firstReviewer,
        string $secondReviewer,
        string $reason
    ): int {
        if (!$this->db->inTransaction()) throw new \LogicException('Caller-owned transaction required');
        if ($claimId < 1 || !preg_match('/^[a-f0-9]{64}$/D',$decisionReference)) {
            throw new \DomainException('Invalid decision reference');
        }
        $find=$this->db->prepare('SELECT id,claimed_seconds,source_line_sha256
            FROM office_inzet_external_hours_claims WHERE id=? FOR UPDATE');
        $find->execute([$claimId]);
        $claim=$find->fetch(PDO::FETCH_ASSOC);
        if ($claim===false || !hash_equals((string)$claim['source_line_sha256'],$expectedSourceLineSha256)) {
            throw new \DomainException('Missing or changed external hours claim');
        }
        $decision=OfficeExternalHoursFourEyesPolicy::approve(
            (int)$claim['claimed_seconds'],$presentSeconds,$invoicedSeconds,$payableSeconds,
            $firstReviewer,$secondReviewer,$reason,$expectedSourceLineSha256
        );
        $findExisting=$this->db->prepare('SELECT * FROM office_inzet_external_hours_review_decisions
            WHERE external_claim_id=? FOR UPDATE');
        $findExisting->execute([$claimId]);
        $existing=$findExisting->fetchAll(PDO::FETCH_ASSOC);
        foreach ($existing as $row) {
            if (hash_equals((string)$row['decision_reference'],$decisionReference)
                && (int)$row['present_seconds']===$decision['present_seconds']
                && ($row['invoiced_seconds']===null ? null : (int)$row['invoiced_seconds'])===$decision['invoiced_seconds']
                && (int)$row['approved_payable_seconds']===$decision['approved_payable_seconds']
                && hash_equals((string)$row['source_line_sha256'],$expectedSourceLineSha256)
                && (string)$row['first_reviewer']===$decision['first_reviewer']
                && (string)$row['second_reviewer']===$decision['second_reviewer']
                && (string)$row['reason']===$decision['reason']) {
                return (int)$row['id'];
            }
            throw new \DomainException('External hours claim already reviewed; amendment workflow required');
        }
        $insert=$this->db->prepare('INSERT INTO office_inzet_external_hours_review_decisions
            (external_claim_id,decision_reference,source_line_sha256,present_seconds,
             invoiced_seconds,approved_payable_seconds,first_reviewer,second_reviewer,reason)
            VALUES (?,?,?,?,?,?,?,?,?)');
        $insert->execute([$claimId,$decisionReference,$expectedSourceLineSha256,
            $decision['present_seconds'],$decision['invoiced_seconds'],
            $decision['approved_payable_seconds'],$decision['first_reviewer'],
            $decision['second_reviewer'],$decision['reason']]);
        return (int)$this->db->lastInsertId();
    }
}
