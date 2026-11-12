<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Canonical immutable approval evidence payload.
 * Only a persisted, independently verified approval decision may supply this data.
 */
final class OfficeApprovedHoursEvidence
{
    /** @param array<string,mixed> $session @param array<string,mixed> $decision
     *  @return array<string,mixed>
     */
    public static function fromVerifiedDecision(array $session, array $decision, string $reviewer): array
    {
        if (($decision['status'] ?? null) !== 'approved'
            || ($decision['persisted'] ?? null) !== true
            || !isset($decision['decision_id']) || !is_string($decision['decision_id'])
            || trim($decision['decision_id']) === ''
            || !isset($decision['approved_at']) || !is_string($decision['approved_at'])
            || !isset($decision['reviewed_seconds']) || !is_int($decision['reviewed_seconds'])
            || $decision['reviewed_seconds'] < 0 || $decision['reviewed_seconds'] > 86400
            || ($decision['reviewer_id'] ?? null) !== $reviewer
            || empty($session['in_event_id']) || empty($session['out_event_id'])) {
            throw new \DomainException('Verified persisted OnSite approval required');
        }
        foreach (['project_id','person_id'] as $key) {
            if (!isset($session[$key]) || !ctype_digit((string)$session[$key])
                || (int)$session[$key] < 1) {
                throw new \InvalidArgumentException('Invalid approved session identity');
            }
        }
        $date = $session['work_date'] ?? null;
        if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)
            || !checkdate((int)substr($date,5,2),(int)substr($date,8,2),(int)substr($date,0,4))) {
            throw new \InvalidArgumentException('Invalid approved session date');
        }
        $reference = 'onsite:' . $decision['decision_id'];
        $canonical = implode('|', [
            $reference, (string)$session['in_event_id'], (string)$session['out_event_id'],
            (string)$session['project_id'], (string)$session['person_id'], $date,
            (string)$decision['reviewed_seconds'], $reviewer, $decision['approved_at'],
        ]);
        return [
            'project_id'=>(int)$session['project_id'], 'person_id'=>(int)$session['person_id'],
            'work_date'=>$date, 'approved_seconds'=>$decision['reviewed_seconds'],
            'source_kind'=>'onsite_review', 'source_reference'=>$reference,
            'source_sha256'=>hash('sha256',$canonical),
            'approved_by'=>$reviewer, 'approved_at'=>$decision['approved_at'],
            'approval_reference'=>$decision['decision_id'],
        ];
    }
}
