<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OnSiteSubcontractorAssignment.php';

use Brebo\Office\Inzet\OnSiteSubcontractorAssignment;

$gate = new OnSiteSubcontractorAssignment();
$assignment = [
  'project_id'=>12,'person_id'=>7,'status'=>'active','rate_cents_per_hour'=>6250,
  'starts_at'=>'2026-10-01T00:00:00+02:00','ends_at'=>'2026-11-01T00:00:00+01:00',
];
$session = [
  'project_id'=>12,'person_id'=>7,
  'started_at'=>'2026-10-15T07:00:00+02:00','ended_at'=>'2026-10-15T16:00:00+02:00',
];
$valid = $gate->evaluate($assignment,$session);
if (!$valid['eligible'] || $valid['rate_cents_per_hour'] !== 6250) throw new RuntimeException('Valid assignment rejected.');
if ($gate->evaluate(array_replace($assignment,['status'=>'cancelled']),$session)['eligible']) throw new RuntimeException('Inactive assignment accepted.');
if ($gate->evaluate($assignment,array_replace($session,['project_id'=>13]))['eligible']) throw new RuntimeException('Wrong project accepted.');
if ($gate->evaluate($assignment,array_replace($session,['person_id'=>8]))['eligible']) throw new RuntimeException('Wrong person accepted.');
if ($gate->evaluate($assignment,array_replace($session,['ended_at'=>'2026-11-02T16:00:00+01:00']))['eligible']) throw new RuntimeException('Out-of-period hours accepted.');
echo "OnSite subcontractor assignment OK\n";
