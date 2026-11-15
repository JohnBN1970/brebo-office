<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Inzet/OfficeApprovedLabourValuation.php';
use Brebo\Office\Inzet\OfficeApprovedLabourValuation as V;
$approved=['id'=>7,'project_id'=>42,'person_id'=>5,'work_date'=>'2026-11-15',
    'approved_seconds'=>21600,'source_sha256'=>str_repeat('a',64)];
$rate=['person_id'=>5,'project_id'=>42,'valid_from'=>'2026-01-01',
    'valid_until'=>null,'hourly_rate_cents'=>4250,'authorized'=>true];
$cost=V::prepare($approved,[$rate]);
if($cost['actual_cost_cents']!==25500 || $cost['cost_type']!=='labor'
    || $cost['approved_hours_id']!==7 || $cost['source_sha256']!==str_repeat('a',64)) {
    throw new RuntimeException('Verified labour cost evidence mismatch');
}
foreach ([[$approved,[]],[array_replace($approved,['source_sha256'=>'broken']),[$rate]],
    [array_replace($approved,['approved_seconds'=>0]),[$rate]],
    [$approved,[$rate,$rate]]] as [$entry,$rates]) {
    try { V::prepare($entry,$rates); throw new RuntimeException('Unsafe valuation accepted'); }
    catch(DomainException $expected) {}
}
echo "APPROVED_LABOUR_VALUATION_OK\n";
