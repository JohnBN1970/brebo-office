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
$assert($handoff->projectId(1),11,'same project in both mappings');
$db->exec('INSERT INTO office_crm_direct_project_transfers VALUES (1,12)');
$assert($handoff->projectId(1),null,'ambiguous mappings must be hidden');
$assert($handoff->projectId(0),null,'invalid opportunity id');
echo "CRM_UNIFIED_PROJECT_HANDOFF=PASS\n";
