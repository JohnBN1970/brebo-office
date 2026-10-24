<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Crm/OfficeCrmCompanyDetailReader.php';

use Brebo\Office\Crm\OfficeCrmCompanyDetailReader;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
  echo "OfficeCrmCompanyDetailReaderTest SKIP: pdo_sqlite unavailable\n";
  exit(0);
}

$db = new PDO('sqlite::memory:', null, null, [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$db->exec('CREATE TABLE office_crm_companies (id INTEGER PRIMARY KEY, name TEXT, email TEXT, phone TEXT)');
$db->exec('CREATE TABLE office_crm_contacts (id INTEGER PRIMARY KEY, first_name TEXT, last_name TEXT, email TEXT)');
$db->exec('CREATE TABLE office_crm_company_contacts (company_id INTEGER, contact_id INTEGER, role_label TEXT)');
$db->exec('CREATE TABLE office_crm_company_buildings (company_id INTEGER, building_id INTEGER, role_label TEXT)');
$db->exec('CREATE TABLE office_crm_company_projects (company_id INTEGER, project_id INTEGER, role_label TEXT)');
$db->exec("INSERT INTO office_crm_companies VALUES (1, 'Bedrijf A', 'a@example.org', ''), (2, 'Bedrijf B', 'b@example.org', '')");
$db->exec("INSERT INTO office_crm_contacts VALUES (10, 'Ada', 'Test', 'ada@example.org')");
$db->exec("INSERT INTO office_crm_company_contacts VALUES (1, 10, 'Beheerder'), (2, 10, 'Adviseur')");
$db->exec("INSERT INTO office_crm_company_buildings VALUES (1, 300, 'Eigenaar')");
$db->exec("INSERT INTO office_crm_company_projects VALUES (1, 400, 'Opdrachtgever')");
$reader = new OfficeCrmCompanyDetailReader($db);
if (($reader->company(1)['name'] ?? '') !== 'Bedrijf A') throw new RuntimeException('Company detail mismatch');
if ($reader->company(999) !== null || $reader->company(0) !== null) throw new RuntimeException('Unknown company must not resolve');
if (count($reader->contacts(1)) !== 1 || count($reader->contacts(2)) !== 1) throw new RuntimeException('Contact linkage mismatch');
if (($reader->buildingReferences(1)[0]['record_id'] ?? null) != 300) throw new RuntimeException('Building link mismatch');
if (($reader->projectReferences(1)[0]['record_id'] ?? null) != 400) throw new RuntimeException('Project link mismatch');
if ($reader->buildingReferences(2) !== [] || $reader->projectReferences(2) !== []) throw new RuntimeException('Cross-company leakage');
echo "OfficeCrmCompanyDetailReaderTest OK\n";
