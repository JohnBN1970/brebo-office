<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Inzet/OnSitePresenceIntervalProjector.php';
require_once __DIR__.'/../src/Inzet/OnSitePresenceReviewRepository.php';
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE office_operator_accounts (id TEXT,active INTEGER)');
$db->exec('CREATE TABLE office_project_memberships (operator_id TEXT,project_id INTEGER,permission TEXT)');
$db->exec('CREATE TABLE office_onsite_event_inbox (event_id TEXT,person_id INTEGER,project_id TEXT,zone_id TEXT,event_kind TEXT,occurred_at_utc TEXT)');
$db->exec("INSERT INTO office_operator_accounts VALUES ('leader',1),('reader',1)");
$db->exec("INSERT INTO office_project_memberships VALUES ('leader',7,'manage'),('reader',7,'read')");
$q=$db->prepare('INSERT INTO office_onsite_event_inbox VALUES (?,?,?,?,?,?)');
foreach ([
    ['sun-in',1,'7','z','in','2026-11-15T07:00:00Z'],
    ['sun-out',1,'7','z','out','2026-11-15T15:00:00Z'],
    ['cutoff-in',2,'7','z','in','2026-11-15T23:00:00Z'],
    ['cutoff-out',2,'7','z','out','2026-11-16T01:00:00Z'],
    ['sql-in',3,'7','z','in','2026-11-15 08:00:00'],
    ['sql-out',3,'7','z','out','2026-11-15 09:00:00'],
] as $row) $q->execute($row);
$reader=new \Brebo\Office\Inzet\OnSitePresenceReviewRepository($db);
$week=$reader->forManagerWeek('leader','7','2026-11-15 23:00:00');
if (count($week['intervals'])!==2 || array_sum(array_column($week['intervals'],'elapsed_seconds'))!==32400
    || $week['active']!==[]) throw new RuntimeException('ISO UTC Sunday events omitted or boundary events leaked');
$denied=$reader->forManagerWeek('reader','7','2026-11-15 23:00:00');
if ($denied['intervals']!==[]) throw new RuntimeException('Read-only member accessed manager presence');
echo "ONSITE_ISO_UTC_WEEK_BOUNDARY=PASS\n";
