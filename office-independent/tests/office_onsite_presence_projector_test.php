<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OnSitePresenceProjector.php';

use Brebo\Office\Inzet\OnSitePresenceProjector;

$p = new OnSitePresenceProjector();
$events = [
 ['event_id'=>'event-out','person_id'=>1,'project_id'=>10,'action'=>'out','occurred_at'=>'2026-10-16T15:00:00+02:00'],
 ['event_id'=>'event-in','person_id'=>1,'project_id'=>10,'action'=>'in','occurred_at'=>'2026-10-16T07:00:00+02:00'],
];
$result = $p->project($events);
if (count($result['sessions']) !== 1 || $result['sessions'][0]['duration_seconds'] !== 28800) throw new RuntimeException('IN/OUT projection failed.');
if ($result['sessions'][0]['approval_status'] !== 'pending') throw new RuntimeException('Hours incorrectly approved.');
if ($result['exceptions'] !== []) throw new RuntimeException('Unexpected exceptions.');
$open = $p->project([$events[1], $events[1]]);
if (count($open['exceptions']) !== 1 || $open['exceptions'][0]['reason'] !== 'open_session') throw new RuntimeException('Open session not detected.');
$wrong = $p->project([$events[1], ['event_id'=>'wrong-out','person_id'=>1,'project_id'=>11,'action'=>'out','occurred_at'=>'2026-10-16T15:00:00+02:00']]);
if (count($wrong['sessions']) !== 0 || count($wrong['exceptions']) !== 2) throw new RuntimeException('Wrong-project clock-out accepted.');
echo "OnSite presence projection OK\n";
