<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;

require_once __DIR__.'/OfficeApprovedLabourRatePolicy.php';
require_once __DIR__.'/OfficeApprovedLabourCostPolicy.php';

/**
 * Read-only preparation of a traceable actual labour cost.
 * Only pass rows fetched server-side from the immutable approved-hours ledger.
 * This does not authorize posting into FinanceCenter or Werkbegroting.
 */
final class OfficeApprovedLabourValuation
{
    public static function prepare(array $approved, array $authorizedRates): array
    {
        foreach (['id','project_id','person_id','approved_seconds'] as $field) {
            if (!isset($approved[$field]) || !is_int($approved[$field]) || $approved[$field] < 1) {
                throw new \DomainException('Verified approved-hours ledger record required');
            }
        }
        $date=$approved['work_date']??null;
        $hash=$approved['source_sha256']??null;
        if (!is_string($date) || !is_string($hash) || !preg_match('/^[a-f0-9]{64}$/D',$hash)) {
            throw new \DomainException('Immutable approval evidence required');
        }
        $rate=OfficeApprovedLabourRatePolicy::resolve(
            $approved['person_id'],$approved['project_id'],$date,$authorizedRates
        );
        $cost=OfficeApprovedLabourCostPolicy::calculate($approved['approved_seconds'],$rate);
        return [
            'approved_hours_id'=>$approved['id'],
            'project_id'=>$approved['project_id'],
            'person_id'=>$approved['person_id'],
            'work_date'=>$date,
            'approved_seconds'=>$approved['approved_seconds'],
            'hourly_rate_cents'=>$rate,
            'actual_cost_cents'=>$cost,
            'cost_type'=>'labor',
            'currency'=>'EUR',
            'source_sha256'=>$hash,
        ];
    }
}
