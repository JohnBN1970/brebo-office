<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Finance/OfficeFinanceAccess.php';
require_once __DIR__ . '/../src/Project/OfficeNativeProjectAccess.php';
require_once __DIR__ . '/../src/Project/OfficeProjectTermRepository.php';
require_once __DIR__ . '/../src/Project/OfficeProjectChangeOrderRepository.php';
require_once __DIR__ . '/../src/Project/OfficeProjectContractValue.php';
require_once __DIR__ . '/../src/Project/OfficeProjectTermService.php';

use Brebo\Office\Project\OfficeProjectTermService;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    fwrite(STDERR, "FAIL: SQLite ontbreekt\n");
    exit(1);
}

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$db->exec("
    CREATE TABLE office_projects (
        id INTEGER PRIMARY KEY,
        title TEXT NOT NULL
    );
    CREATE TABLE office_operator_accounts (
        id INTEGER PRIMARY KEY,
        active INTEGER NOT NULL
    );
    CREATE TABLE office_project_memberships (
        operator_id INTEGER,
        project_id INTEGER,
        permission TEXT
    );
    CREATE TABLE office_module_permissions (
        operator_id INTEGER,
        module TEXT,
        permission TEXT
    );
    CREATE TABLE office_project_change_orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        contract_id INTEGER NOT NULL,
        change_number TEXT NOT NULL,
        description TEXT NOT NULL,
        change_type TEXT NOT NULL,
        amount DECIMAL(15,2) NOT NULL,
        status TEXT NOT NULL DEFAULT 'draft',
        approved_at TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(contract_id, change_number)
    );

    CREATE TABLE office_project_contracts (
        id INTEGER PRIMARY KEY,
        office_project_id INTEGER NOT NULL,
        contract_amount DECIMAL(15,2) NOT NULL, status TEXT NOT NULL DEFAULT 'draft'
    );
    CREATE TABLE office_project_terms (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        contract_id INTEGER NOT NULL,
        term_number INTEGER NOT NULL,
        description TEXT NOT NULL,
        percentage DECIMAL(7,4),
        amount DECIMAL(15,2) NOT NULL,
        status TEXT NOT NULL,
        due_date TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(contract_id, term_number)
    );
");

$db->exec("
    INSERT INTO office_projects VALUES
        (12, 'Project A'),
        (13, 'Project B');

    INSERT INTO office_operator_accounts VALUES
        (1, 1), (2, 1), (3, 1);

    INSERT INTO office_project_memberships VALUES
        (1, 12, 'manage'),
        (2, 12, 'read'),
        (3, 12, 'manage');

    INSERT INTO office_module_permissions VALUES
        (1, 'finance', 'read'),
        (2, 'finance', 'read');

    INSERT INTO office_project_contracts
        (id, office_project_id, contract_amount) VALUES
        (10, 12, 100000.00),
        (11, 13, 50000.00);
");

$service = new OfficeProjectTermService($db);

function expectTermDenied(callable $action, string $message): void
{
    try {
        $action();
    } catch (DomainException $e) {
        return;
    }

    throw new RuntimeException($message);
}

$id = $service->createPlanned(
    '1', 12, 10, 1, 'Bij opdracht', '20000.00', '20'
);

if ($id < 1) {
    throw new RuntimeException('Termijn niet aangemaakt');
}

$terms = $service->listForContract('1', 12, 10);

if (count($terms) !== 1 || $terms[0]['status'] !== 'planned') {
    throw new RuntimeException('Termijn niet correct opgeslagen');
}

if (count($service->listForContract('2', 12, 10)) !== 1) {
    throw new RuntimeException('Bevoegd lezen mislukt');
}

expectTermDenied(
    fn() => $service->createPlanned(
        '2', 12, 10, 2, 'Onbevoegd', '100.00', '1'
    ),
    'Leesgebruiker kon termijn aanmaken'
);

expectTermDenied(
    fn() => $service->listForContract('3', 12, 10),
    'Gebruiker zonder Finance-rechten kon lezen'
);

expectTermDenied(
    fn() => $service->createPlanned(
        '1', 12, 11, 2, 'Verkeerd contract', '100.00'
    ),
    'Termijn op contract van ander project toegestaan'
);

expectTermDenied(
    fn() => $service->listForContract('1', 12, 11),
    'Contract van ander project kon worden gelezen'
);


// Contractsom mag niet worden overschreden.
expectTermDenied(
    fn() => $service->createPlanned(
        '1', 12, 10, 2, 'Te hoog bedrag', '90000.00', '80'
    ),
    'Contractsom kon worden overschreden'
);

// Percentage mag niet boven 100% uitkomen.
$service->createPlanned(
    '1', 12, 10, 2, 'Tweede termijn', '10000.00', '75'
);

expectTermDenied(
    fn() => $service->createPlanned(
        '1', 12, 10, 3, 'Te hoog percentage', '1000.00', '6'
    ),
    'Termijnpercentage kon boven 100% uitkomen'
);

// Afgesloten contract mag niet worden gewijzigd.
$db->exec("UPDATE office_project_contracts SET status = 'closed' WHERE id = 10");

expectTermDenied(
    fn() => $service->createPlanned(
        '1', 12, 10, 3, 'Afgesloten contract', '1000.00', '1'
    ),
    'Afgesloten contract kon worden gewijzigd'
);

echo "PASS: financiële termijncontroles\n";


// Contract 10: controleer goedgekeurde wijzigingen tegenover termijnen.
$db->exec("
    INSERT INTO office_project_change_orders
        (contract_id, change_number, description, change_type, amount, status)
    VALUES
        (10, 'TEST-MORE', 'Goedgekeurd meerwerk', 'more', 2500.00, 'approved'),
        (10, 'TEST-LESS', 'Goedgekeurd minderwerk', 'less', 750.00, 'approved'),
        (10, 'TEST-DRAFT', 'Niet goedgekeurd meerwerk', 'more', 50000.00, 'draft')
");

$contractRow = $db->query(
    "SELECT contract_amount FROM office_project_contracts WHERE id = 10"
)->fetchColumn();

$changeRows = $db->query(
    "SELECT change_type, amount FROM office_project_change_orders
     WHERE contract_id = 10 AND status = 'approved'"
)->fetchAll(PDO::FETCH_ASSOC);

$value = \Brebo\Office\Project\OfficeProjectContractValue::calculate(
    (string) $contractRow,
    $changeRows
);

if ($value['current'] !== '101750.00') {
    throw new RuntimeException(
        'Termijncontrole: actuele contractwaarde onjuist'
    );
}

echo "PASS: termijnschema actuele contractwaarde inclusief wijzigingen\n";




echo "PASS: termijnservice autorisatie\n";
