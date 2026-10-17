<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OnSiteClockEventAuthorization.php';

use Brebo\Office\Inzet\OnSiteClockEventAuthorization;

$db = new PDO('sqlite::memory:');
$db->exec('CREATE TABLE office_inzet_people (id INTEGER PRIMARY KEY, active INTEGER NOT NULL)');
$db->exec('CREATE TABLE office_projects (id INTEGER PRIMARY KEY)');
$db->exec('CREATE TABLE office_inzet_project_team (project_id INTEGER, person_id INTEGER)');
$db->exec('INSERT INTO office_inzet_people (id,active) VALUES (1,1),(2,0)');
$db->exec('INSERT INTO office_projects (id) VALUES (10),(11)');
$db->exec('INSERT INTO office_inzet_project_team (project_id,person_id) VALUES (10,1),(10,2)');
$auth = new OnSiteClockEventAuthorization($db);
if (!$auth->canClock(1,10)) throw new RuntimeException('Assigned active worker rejected.');
if ($auth->canClock(1,11)) throw new RuntimeException('Worker permitted on unrelated project.');
if ($auth->canClock(2,10)) throw new RuntimeException('Inactive worker permitted.');
if ($auth->canClock(0,10)) throw new RuntimeException('Invalid identity permitted.');
try {
  $auth->assertAuthorized(['person_id' => 2, 'project_id' => 10], 1);
  throw new RuntimeException('Worker impersonation permitted.');
} catch (DomainException $expected) {
}
$auth->assertAuthorized(['person_id' => 1, 'project_id' => 10], 1);
echo "OnSite clock authorization OK\n";
