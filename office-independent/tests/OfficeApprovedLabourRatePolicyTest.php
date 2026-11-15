<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Inzet/OfficeApprovedLabourRatePolicy.php';
use Brebo\Office\Inzet\OfficeApprovedLabourRatePolicy as P;
$rate=['person_id'=>5,'project_id'=>42,'valid_from'=>'2026-01-01','valid_until'=>null,'hourly_rate_cents'=>4250,'authorized'=>true];
if(P::resolve(5,42,'2026-11-15',[$rate])!==4250)throw new RuntimeException('Rate resolution failed');
foreach ([[],[$rate,$rate],[array_replace($rate,['authorized'=>false])],[array_replace($rate,['valid_from'=>'2026-11-16'])],[array_replace($rate,['hourly_rate_cents'=>0])]] as $rows) {
    try { P::resolve(5,42,'2026-11-15',$rows); throw new RuntimeException('Expected rate rejection'); }
    catch(DomainException $expected) {}
}
echo "APPROVED_LABOUR_RATE_POLICY_OK\n";
