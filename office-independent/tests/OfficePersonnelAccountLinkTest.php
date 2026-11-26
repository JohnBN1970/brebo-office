<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Inzet/OfficePersonnelAccountLink.php';
use Brebo\Office\Inzet\OfficePersonnelAccountLink;
use Brebo\Office\Inzet\OfficeInzetPersonnelProfileCsrf;

$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec("CREATE TABLE office_operator_accounts (id INTEGER PRIMARY KEY, active INTEGER);
CREATE TABLE office_test_site_administrators (operator_id INTEGER PRIMARY KEY);
CREATE TABLE office_inzet_people (id INTEGER PRIMARY KEY, active INTEGER);
CREATE TABLE office_inzet_project_team (person_id INTEGER, project_id INTEGER);
CREATE TABLE office_project_memberships (project_id INTEGER, operator_id INTEGER, permission TEXT);
CREATE TABLE office_account_personnel_links (operator_id INTEGER PRIMARY KEY, person_id INTEGER UNIQUE, linked_by_operator_id INTEGER);
INSERT INTO office_operator_accounts VALUES (1,1),(2,1),(3,0),(4,1);
INSERT INTO office_test_site_administrators VALUES (1);
INSERT INTO office_inzet_people VALUES (1,1),(2,1),(3,0),(4,1);
INSERT INTO office_inzet_project_team VALUES (1,10),(2,10),(3,10),(4,20);
INSERT INTO office_project_memberships VALUES (10,1,'manage'),(10,2,'manage');");
$service = new OfficePersonnelAccountLink($db);
$session = str_repeat('a',64);
$secret = str_repeat('b',32);
$input = ['person_id'=>1,'account_id'=>2,'project_id'=>10,'csrf_token'=>OfficeInzetPersonnelProfileCsrf::token($session,$secret)];
$check = static function(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException($label);
};
$check(!$service->link('2',$session,$secret,$input,true),'non-admin');
$check(!$service->link('1',$session,$secret,$input,false),'production');
$check(!$service->link('1',$session,$secret,array_replace($input,['csrf_token'=>'bad']),true),'csrf');
$check(!$service->link('1',$session,$secret,array_replace($input,['account_id'=>3]),true),'inactive account');
$check(!$service->link('1',$session,$secret,array_replace($input,['person_id'=>3]),true),'inactive person');
$check(!$service->link('1',$session,$secret,array_replace($input,['person_id'=>4,'project_id'=>20]),true),'outside project');
$check($service->link('1',$session,$secret,$input,true),'valid link');
$check(!$service->link('1',$session,$secret,$input,true),'duplicate');
$check(!$service->link('1',$session,$secret,array_replace($input,['person_id'=>2]),true),'account already linked');
$check(!$service->link('1',$session,$secret,array_replace($input,['account_id'=>4]),true),'person already linked');
$check((int)$db->query('SELECT COUNT(*) FROM office_account_personnel_links WHERE operator_id=2 AND person_id=1 AND linked_by_operator_id=1')->fetchColumn()===1,'stored identity and actor');
echo "OfficePersonnelAccountLinkTest OK\n";
