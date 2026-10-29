<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Questions/OfficeConversationAccess.php';
require_once __DIR__ . '/../src/Finance/OfficeFinanceAccess.php';
require_once __DIR__ . '/../src/Finance/OfficeFinanceDraftRegister.php';
require_once __DIR__ . '/../src/Questions/OfficeFinanceConversationAccess.php';

use Brebo\Office\Questions\OfficeFinanceConversationAccess;

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$schema = [
    'CREATE TABLE office_operator_accounts (id TEXT PRIMARY KEY, active INTEGER)',
    'CREATE TABLE office_module_permissions (operator_id TEXT, module TEXT, permission TEXT)',
    'CREATE TABLE office_projects (id INTEGER PRIMARY KEY, title TEXT)',
    'CREATE TABLE office_project_memberships (operator_id TEXT, project_id INTEGER, permission TEXT)',
    'CREATE TABLE office_finance_invoices (id INTEGER PRIMARY KEY, status TEXT)',
    'CREATE TABLE office_finance_invoice_lines (id INTEGER PRIMARY KEY, invoice_id INTEGER, office_project_id INTEGER, line_number INTEGER)',
    'CREATE TABLE office_finance_invoice_documents (id INTEGER PRIMARY KEY, invoice_id INTEGER)',
    'CREATE TABLE office_finance_invoice_events (id INTEGER PRIMARY KEY, invoice_id INTEGER)',
];

foreach ($schema as $sql) {
    $db->exec($sql);
}

$db->exec("
    INSERT INTO office_operator_accounts VALUES ('manager',1),('viewer',1),('outsider',1);
    INSERT INTO office_module_permissions VALUES
      ('manager','finance','read'),
      ('manager','finance','draft_edit'),
      ('viewer','finance','read'),
      ('outsider','finance','read');
    INSERT INTO office_projects VALUES (10,'Project A'),(20,'Project B');
    INSERT INTO office_project_memberships VALUES
      ('manager',10,'manage'),
      ('viewer',10,'read'),
      ('outsider',20,'read');
    INSERT INTO office_finance_invoices VALUES (100,'draft'),(200,'draft');
    INSERT INTO office_finance_invoice_lines VALUES
      (1,100,10,1),
      (2,200,20,1);
");

$access = OfficeFinanceConversationAccess::create($db);

$checks = [
    ['manager','finance','invoice',100,'read',true],
    ['manager','finance','invoice',100,'create',true],
    ['manager','finance','invoice',100,'reply',true],
    ['manager','finance','invoice',100,'resolve',false],
    ['manager','finance','invoice',200,'read',false],
    ['viewer','finance','invoice',100,'read',true],
    ['viewer','finance','invoice',100,'create',false],
    ['viewer','finance','invoice',100,'reply',true],
    ['outsider','finance','invoice',100,'read',false],
    ['manager','crm','invoice',100,'read',false],
];

$passed = 0;

foreach ($checks as [$operator,$module,$type,$id,$action,$expected]) {
    try {
        $access->require($operator,$module,$type,$id,$action);
        $allowed = true;
    } catch (DomainException | OutOfBoundsException $e) {
        $allowed = false;
    }

    if ($allowed !== $expected) {
        throw new RuntimeException(
            "FOUT: $operator $module/$type/$id $action"
        );
    }

    echo "OK: $operator $action " . ($allowed ? 'toegestaan' : 'geweigerd') . "\n";
    $passed++;
}

echo "Finance dossierautorisatie: $passed checks passed\n";
