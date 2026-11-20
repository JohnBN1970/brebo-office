<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Crm/OfficeCrmProjectHandoff.php';
use Brebo\Office\Crm\OfficeCrmProjectHandoff;
if (!extension_loaded('pdo_sqlite')) throw new RuntimeException('pdo_sqlite is required');
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE office_projects (id INTEGER PRIMARY KEY)');
$db->exec('INSERT INTO office_projects(id) VALUES (11),(12)');
$handoff=new OfficeCrmProjectHandoff($db);
$assert=static function(?int $actual,?int $expected,string $label):void {
    if ($actual!==$expected) throw new RuntimeException($label.': expected '.var_export($expected,true).' got '.var_export($actual,true));
};
$assert($handoff->projectId(1),null,'no mapping tables');
$db->exec('CREATE TABLE office_calc_crm_acceptance_transfers (crm_opportunity_id INTEGER, office_project_id INTEGER)');
$db->exec('INSERT INTO office_calc_crm_acceptance_transfers VALUES (1,11),(2,99)');
$assert($handoff->projectId(1),11,'calculation link');
$assert($handoff->projectId(2),null,'orphan mapping cannot be shown');
$db->exec('CREATE TABLE office_crm_direct_project_transfers (opportunity_id INTEGER, project_id INTEGER)');
$db->exec('INSERT INTO office_crm_direct_project_transfers VALUES (3,12),(1,11)');
$assert($handoff->projectId(3),12,'direct-order link');
$assert($handoff->projectId(1),null,'duplicated Calc and direct mapping must be hidden');
$db->exec('INSERT INTO office_crm_direct_project_transfers VALUES (1,12)');
$assert($handoff->projectId(1),null,'ambiguous mappings must be hidden');
$assert($handoff->projectId(0),null,'invalid opportunity id');

require_once dirname(__DIR__).'/src/Crm/OfficeCrmOpportunityRepository.php';
require_once dirname(__DIR__).'/src/Crm/OfficeCrmFunnel.php';
require_once dirname(__DIR__).'/src/Crm/OfficeCrmFunnelViews.php';
$db->exec('CREATE TABLE office_crm_relations (id INTEGER PRIMARY KEY, display_name TEXT)');
$db->exec("INSERT INTO office_crm_relations VALUES (5,'Testrelatie')");
$db->exec('CREATE TABLE office_crm_opportunities (id INTEGER PRIMARY KEY, relation_id INTEGER, title TEXT, stage TEXT, status TEXT, next_action TEXT, next_action_at TEXT)');
$db->exec("INSERT INTO office_crm_opportunities VALUES
  (1,5,'Gegunde calc','won','closed',NULL,NULL),
  (2,5,'Zonder bevestiging','won','closed',NULL,NULL),
  (3,5,'Gegunde directe opdracht','won','closed',NULL,NULL)");
$rows=(new \Brebo\Office\Crm\OfficeCrmOpportunityRepository($db))->listForViews();
$byId=[]; foreach($rows as $row) $byId[(int)$row['id']]=$row;
$assert((int)$byId[3]['project_id'],12,'repository resolves direct transfer');
if (isset($byId[1]['project_id']) || isset($byId[2]['project_id'])) throw new RuntimeException('Ambiguous or orphan transfer leaked into CRM list');
$html=\Brebo\Office\Crm\OfficeCrmFunnelViews::render($rows,'list');
if (!str_contains($html,'projecten/detail?id=12') || !str_contains($html,'Projectoverdracht nog niet bevestigd')) {
    throw new RuntimeException('CRM list lacks project link or pending indicator');
}
echo "CRM_UNIFIED_PROJECT_HANDOFF=PASS\n";
