<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Inzet/OfficeExternalHoursApprovalAuthorityPolicy.php';
use Brebo\Office\Inzet\OfficeExternalHoursApprovalAuthorityPolicy as P;
$reason='Gemotiveerd directiebesluit wegens urenverschil';
$single=P::determine('director-1',true,true,null,$reason);
if ($single['mode']!=='highest_role_single' || $single['second_reviewer']!==null) {
    throw new RuntimeException('Highest-role single approval rejected');
}
$pair=P::determine('manager-1',false,true,'manager-2',$reason);
if ($pair['mode']!=='four_eyes') throw new RuntimeException('Four-eyes default missing');
foreach ([
    ['manager-1',false,true,null,$reason],
    ['director-1',true,false,null,$reason],
    ['director-1',true,true,'director-1',$reason],
    ['director-1',true,true,null,'short']
] as $args) {
    try { P::determine(...$args); throw new RuntimeException('Unsafe authority accepted'); }
    catch (DomainException $expected) {}
}
echo "EXTERNAL_HOURS_APPROVAL_AUTHORITY_OK\n";
