<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Crm/OfficeCrmAuthorizedProjectReader.php';
require_once dirname(__DIR__) . '/src/Crm/OfficeCrmAuthorizedBuildingReader.php';

use Brebo\Office\Crm\OfficeCrmAuthorizedProjectReader;
use Brebo\Office\Crm\OfficeCrmAuthorizedBuildingReader;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
  echo "OfficeCrmRelationshipAccessTest SKIP: pdo_sqlite unavailable\n";
  exit(0);
}
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
foreach ([
  'CREATE TABLE office_projects (id INTEGER PRIMARY KEY, code TEXT, title TEXT)',
  'CREATE TABLE office_operator_accounts (id INTEGER PRIMARY KEY, active INTEGER)',
  'CREATE TABLE office_project_memberships (project_id INTEGER, operator_id INTEGER, permission TEXT)',
  'CREATE TABLE office_crm_company_projects (company_id INTEGER, project_id INTEGER, role_label TEXT)',
  'CREATE TABLE office_project_buildings (project_id INTEGER, building_id INTEGER)',
  'CREATE TABLE office_crm_company_buildings (company_id INTEGER, building_id INTEGER, role_label TEXT)',
] as $sql) $db->exec($sql);
$db->exec("INSERT INTO office_projects VALUES (10,'P10','Allowed'),(20,'P20','Denied'),(30,'P30','Other company')");
$db->exec("INSERT INTO office_operator_accounts VALUES (1,1),(2,0),(3,1)");
$db->exec("INSERT INTO office_project_memberships VALUES (10,1,'read'),(20,2,'manage'),(20,3,'none'),(30,3,'read')");
$db->exec("INSERT INTO office_crm_company_projects VALUES (100,10,'Klant'),(100,20,'Klant'),(200,30,'Klant')");
$db->exec("INSERT INTO office_project_buildings VALUES (10,1000),(20,2000),(30,3000)");
$db->exec("INSERT INTO office_crm_company_buildings VALUES (100,1000,'Eigenaar'),(100,2000,'Eigenaar'),(200,3000,'Eigenaar')");
$projects = new OfficeCrmAuthorizedProjectReader($db);
$buildings = new OfficeCrmAuthorizedBuildingReader($db);
$assertIds = static function(array $rows, array $expected, string $context): void {
  $ids = array_column($rows, 'id');
  if ($ids !== $expected) throw new RuntimeException($context . ': ' . json_encode($ids));
};
$assertIds($projects->forCompany(100,1), [10], 'active read project');
$assertIds($buildings->forCompany(100,1), [1000], 'active read building');
$assertIds($projects->forCompany(100,2), [], 'inactive operator project');
$assertIds($buildings->forCompany(100,2), [], 'inactive operator building');
$assertIds($projects->forCompany(100,3), [], 'no permission project');
$assertIds($buildings->forCompany(100,3), [], 'no permission building');
$assertIds($projects->forCompany(200,1), [], 'other company project');
$assertIds($buildings->forCompany(200,1), [], 'other company building');
$assertIds($projects->forCompany(100,0), [], 'invalid operator project');
$assertIds($buildings->forCompany(100,0), [], 'invalid operator building');
echo "OfficeCrmRelationshipAccessTest OK\n";
