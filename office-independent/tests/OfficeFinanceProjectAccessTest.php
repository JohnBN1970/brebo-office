<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Finance/bootstrap.php';

use Brebo\Office\Finance\OfficeFinanceApplication;

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$db->exec("
CREATE TABLE office_operator_accounts (
    id TEXT PRIMARY KEY,
    active INTEGER NOT NULL
);
CREATE TABLE office_module_permissions (
    operator_id TEXT,
    module TEXT,
    permission TEXT
);
CREATE TABLE office_projects (
    id INTEGER PRIMARY KEY,
    title TEXT
);
CREATE TABLE office_project_memberships (
    operator_id TEXT,
    project_id INTEGER,
    permission TEXT
);
CREATE TABLE office_finance_project_mapping (
    office_project_id INTEGER,
    source_project_id INTEGER,
    verified INTEGER
);
");

$db->exec("
INSERT INTO office_operator_accounts VALUES ('1', 1);
INSERT INTO office_operator_accounts VALUES ('2', 1);
INSERT INTO office_module_permissions VALUES ('1', 'finance', 'read');
INSERT INTO office_projects VALUES (10, 'Toegestaan project');
INSERT INTO office_projects VALUES (20, 'Verboden project');
INSERT INTO office_project_memberships VALUES ('1', 10, 'manage');
INSERT INTO office_project_memberships VALUES ('2', 20, 'manage');
");

$app = new OfficeFinanceApplication($db);

$result = $app->read('1', null, 10);

if (($result['finance_unlinked_project'] ?? false) !== true
    || ($result['project']['office_project_id'] ?? null) != 10
    || array_sum($result['counts']) !== 0) {
    throw new RuntimeException('Lege Finance-projectweergave mislukt');
}

echo "OK: toegestaan project zonder Finance-koppeling\n";

try {
    $app->read('1', null, 20);
    throw new RuntimeException('Onbevoegd project toegankelijk');
} catch (OutOfBoundsException $expected) {
    echo "OK: onbevoegd project geblokkeerd\n";
}

try {
    $app->read('1', 999, 10);
    throw new RuntimeException('Factuur zonder koppeling toegankelijk');
} catch (OutOfBoundsException $expected) {
    echo "OK: factuur zonder koppeling geblokkeerd\n";
}

try {
    $app->read('2', null, 20);
    throw new RuntimeException('Finance zonder modulemachtiging toegankelijk');
} catch (DomainException $expected) {
    echo "OK: Finance zonder modulemachtiging geblokkeerd\n";
}

echo "FINANCE PROJECTAUTORISATIE: GESLAAGD\n";
