<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OnSiteInvoiceEligibility.php';

use Brebo\Office\Inzet\OnSiteInvoiceEligibility;

$gate = new OnSiteInvoiceEligibility();
$session = ['in_event_id'=>'in-1','out_event_id'=>'out-1','project_id'=>12,'person_id'=>7];
$approved = ['status'=>'approved','reviewed_seconds'=>25200];
if (!$gate->evaluate($session,$approved)['eligible']) throw new RuntimeException('Approved hours blocked.');
if ($gate->evaluate($session,['status'=>'pending','reviewed_seconds'=>25200])['eligible']) throw new RuntimeException('Pending hours released.');
if ($gate->evaluate($session,['status'=>'rejected','reviewed_seconds'=>0])['eligible']) throw new RuntimeException('Rejected hours released.');
if ($gate->evaluate(array_replace($session,['out_event_id'=>null]),$approved)['eligible']) throw new RuntimeException('Incomplete evidence released.');
if ($gate->evaluate($session,['status'=>'approved','reviewed_seconds'=>-1])['eligible']) throw new RuntimeException('Invalid duration released.');
echo "OnSite invoice eligibility OK\n";
