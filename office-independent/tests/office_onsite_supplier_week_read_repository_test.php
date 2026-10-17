<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/src/Inzet/OnSiteSupplierWeekReadRepository.php';

use Brebo\Office\Inzet\OnSiteSupplierWeekReadRepository;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
  echo "Supplier week read repository integration skipped (pdo_sqlite unavailable)\n";
  exit(0);
}
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
foreach ([
  'CREATE TABLE office_operator_accounts (id INTEGER PRIMARY KEY, active INTEGER)',
  'CREATE TABLE office_project_memberships (operator_id INTEGER, project_id INTEGER, permission TEXT)',
  'CREATE TABLE office_inzet_project_suppliers (project_id INTEGER, supplier_id INTEGER)',
  'CREATE TABLE office_inzet_supplier_week_imports (id INTEGER PRIMARY KEY, supplier_id INTEGER, iso_year INTEGER, iso_week INTEGER, source_sha256 TEXT, source_filename TEXT, created_at TEXT)',
  'CREATE TABLE office_inzet_supplier_week_import_projects (import_id INTEGER, project_id INTEGER)',
] as $sql) $db->exec($sql);
$db->exec("INSERT INTO office_operator_accounts VALUES (7,1),(8,0)");
$db->exec("INSERT INTO office_project_memberships VALUES (7,101,'read'),(7,102,'none'),(8,102,'manage')");
$db->exec("INSERT INTO office_inzet_project_suppliers VALUES (101,3),(102,3)");
$db->exec("INSERT INTO office_inzet_supplier_week_imports VALUES
  (1,3,2026,35,'aaa','project-a.xlsx','2026-09-01'),
  (2,3,2026,35,'bbb','project-b.xlsx','2026-09-02'),
  (3,4,2026,35,'ccc','unassigned.xlsx','2026-09-03'),
  (4,3,2026,35,'ddd','mixed-projects.xlsx','2026-09-04'),
  (5,3,2026,35,'eee','no-projects.xlsx','2026-09-05')");
$db->exec('INSERT INTO office_inzet_supplier_week_import_projects VALUES (1,101),(2,102),(3,101),(4,101),(4,102)');
$repo = new OnSiteSupplierWeekReadRepository($db);
$visible = $repo->listVisible(7,[101,102]);
if (count($visible) !== 1 || (int)$visible[0]['id'] !== 1) {
  throw new RuntimeException('Cross-project supplier week disclosure.');
}
// Multi-project workbook 4 must not leak to operator 7, even via project 101.
// Import 5 with no linked projects must also remain invisible.
if ($repo->listVisible(7,[102]) !== [] || $repo->listVisible(8,[102]) !== []
  || $repo->listVisible(7,[]) !== []) {
  throw new RuntimeException('Inactive operator or unauthorized project received supplier data.');
}
echo "OnSite supplier week read repository OK\n";
