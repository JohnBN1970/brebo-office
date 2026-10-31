<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Finance/OfficeFinanceAccess.php';
require_once __DIR__ . '/../src/Finance/OfficeSalesInvoiceSourceMap.php';
require_once __DIR__ . '/../src/Finance/OfficeSalesInvoiceDraftList.php';

use Brebo\Office\Finance\OfficeSalesInvoiceDraftList;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo "OfficeSalesInvoiceDraftListTest: SKIP (pdo_sqlite unavailable)\n";
    exit(0);
}

$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE office_operator_accounts (id TEXT PRIMARY KEY, active INTEGER)');
$db->exec('CREATE TABLE office_module_permissions (operator_id TEXT, module TEXT, permission TEXT)');
$db->exec('CREATE TABLE office_projects (id INTEGER PRIMARY KEY, title TEXT)');
$db->exec('CREATE TABLE office_project_memberships (operator_id TEXT, project_id INTEGER, permission TEXT)');
$db->exec('CREATE TABLE office_finance_project_mapping (source_project_id INTEGER, office_project_id INTEGER, verified INTEGER)');
$db->exec("INSERT INTO office_operator_accounts VALUES ('allowed',1),('denied',1)");
$db->exec("INSERT INTO office_module_permissions VALUES ('allowed','finance','read')");
$db->exec("INSERT INTO office_projects VALUES (10,'Allowed'),(20,'Hidden')");
$db->exec("INSERT INTO office_project_memberships VALUES ('allowed',10,'read')");
$db->exec("INSERT INTO office_finance_project_mapping VALUES (100,10,1),(200,20,1),(300,10,0)");

$source = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$list = new OfficeSalesInvoiceDraftList($db, $source);

try {
    $list->forOperator('denied');
    throw new RuntimeException('Unauthorized user was allowed');
} catch (DomainException $expected) {
}

try {
    $list->forOperator('allowed', 20);
    throw new RuntimeException('Unauthorized project was allowed');
} catch (OutOfBoundsException $expected) {
}

try {
    $list->forOperator('allowed', 30);
    throw new RuntimeException('Unknown project was allowed');
} catch (OutOfBoundsException $expected) {
}

echo "OfficeSalesInvoiceDraftListTest: OK\n";
