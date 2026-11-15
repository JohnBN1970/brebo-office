<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;

/**
 * Financial handoff gate: exactly one cost entry per approved-hours booking.
 * No database writes. Persist with a UNIQUE(source_kind, source_id) constraint
 * in the canonical FinanceCenter ledger before enabling any posting.
 */
final class OfficeApprovedLabourFinancialHandoffPolicy
{
    public static function prepare(array $valuation, array $existingSourceIds): array
    {
        foreach (['approved_hours_id','project_id','person_id','actual_cost_cents'] as $field) {
            if (!isset($valuation[$field]) || !is_int($valuation[$field]) || $valuation[$field] < 1) {
                throw new \DomainException('Verified positive labour cost required');
            }
        }
        if (($valuation['cost_type']??null)!=='labor' || ($valuation['currency']??null)!=='EUR'
            || !is_string($valuation['source_sha256']??null)
            || !preg_match('/^[a-f0-9]{64}$/D',$valuation['source_sha256'])) {
            throw new \DomainException('Unverified labour valuation evidence');
        }
        $id=$valuation['approved_hours_id'];
        if (in_array($id,$existingSourceIds,true)) {
            throw new \DomainException('Labour cost source already posted');
        }
        return [
            'source_kind'=>'onsite_approved_hours',
            'source_id'=>$id,
            'project_id'=>$valuation['project_id'],
            'person_id'=>$valuation['person_id'],
            'cost_type'=>'labor',
            'amount_cents'=>$valuation['actual_cost_cents'],
            'currency'=>'EUR',
            'source_sha256'=>$valuation['source_sha256'],
        ];
    }
}
