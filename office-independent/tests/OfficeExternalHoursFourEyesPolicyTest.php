<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Inzet/OfficeExternalHoursFourEyesPolicy.php';
use Brebo\Office\Inzet\OfficeExternalHoursFourEyesPolicy as Policy;
$hash=str_repeat('a',64);
$result=Policy::approve(28800,21600,28800,21600,'reviewer-a','reviewer-b','Aanwezigheid wijkt af van claim',$hash);
if ($result['approved_payable_seconds']!==21600 || $result['present_seconds']!==21600
    || $result['disputed_seconds']!==7200 || $result['status']!=='four_eyes_approved') {
    throw new RuntimeException('Reconciliation must retain source hours and difference');
}
$cases=[
    [28800,21600,28800,21600,'same','same','Verschil uitgelegd',$hash],
    [28800,21600,28800,30000,'a','b','Verschil uitgelegd',$hash],
    [28800,21600,14400,21600,'a','b','Verschil uitgelegd',$hash],
    [28800,21600,28800,21600,'a','b','kort',$hash],
    [28800,21600,28800,21600,'a','b','Verschil uitgelegd','bad'],
];
foreach ($cases as $args) {
    try { Policy::approve(...$args); throw new RuntimeException('Unsafe approval accepted'); }
    catch (DomainException $expected) {}
}
echo "EXTERNAL_HOURS_FOUR_EYES_POLICY_OK\n";
