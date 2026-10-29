<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Finance/OfficeFinanceAccess.php';
require_once __DIR__ . '/../src/Project/OfficeNativeProjectAccess.php';
require_once __DIR__ . '/../src/Project/OfficeProjectTermRepository.php';
require_once __DIR__ . '/../src/Project/OfficeProjectChangeOrderRepository.php';
require_once __DIR__ . '/../src/Project/OfficeProjectContractValue.php';
require_once __DIR__ . '/../src/Project/OfficeProjectTermService.php';

use Brebo\Office\Project\OfficeProjectTermService;

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
    CREATE TABLE office_project_contracts (
        id INTEGER PRIMARY KEY,
        office_project_id INTEGER NOT NULL,
        contract_amount DECIMAL(15,2) NOT NULL,
        status TEXT NOT NULL
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
    CREATE TABLE office_project_change_orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        contract_id INTEGER NOT NULL,
        change_number TEXT NOT NULL,
        description TEXT NOT NULL,
        change_type TEXT NOT NULL,
        amount DECIMAL(15,2) NOT NULL,
        status TEXT NOT NULL,
        approved_at TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    );

    INSERT INTO office_projects VALUES (12, 'Testproject');
    INSERT INTO office_operator_accounts VALUES (1, 1);
    INSERT INTO office_project_memberships VALUES (1, 12, 'manage');
    INSERT INTO office_module_permissions VALUES (1, 'finance', 'read');

    INSERT INTO office_project_contracts VALUES
        (10, 12, 100000.00, 'draft'),
        (11, 12, 100000.00, 'draft'),
        (12, 12, 100000.00, 'draft');

    INSERT INTO office_project_change_orders
        (contract_id, change_number, description, change_type, amount, status)
    VALUES
        (10, 'M1', 'Meerwerk', 'more', 2500.00, 'approved'),
        (10, 'M2', 'Minderwerk', 'less', 750.00, 'approved'),
        (10, 'M3', 'Concept', 'more', 50000.00, 'draft'),
        (11, 'M4', 'Minderwerk', 'less', 20000.00, 'approved');
");

$service = new OfficeProjectTermService($db);

function expectBlocked(callable $action, string $message): void
{
    try {
        $action();
    } catch (DomainException $e) {
        return;
    }

    throw new RuntimeException($message);
}

// Contract 10: actuele waarde 101750.00.
$service->createPlanned(
    '1', 12, 10, 1, 'Eerste termijn', '100000.00'
);

$service->createPlanned(
    '1', 12, 10, 2, 'Tweede termijn', '1750.00'
);

expectBlocked(
    fn() => $service->createPlanned(
        '1', 12, 10, 3, 'Boven actuele waarde', '0.01'
    ),
    'Termijn boven actuele contractwaarde toegestaan'
);

// Contract 11: minderwerk verlaagt grens naar 80000.00.
expectBlocked(
    fn() => $service->createPlanned(
        '1', 12, 11, 1, 'Te hoge termijn', '80000.01'
    ),
    'Minderwerk verlaagt termijnlimiet niet'
);

$service->createPlanned(
    '1', 12, 11, 1, 'Toegestane termijn', '80000.00'
);

// Contract 12: geen goedgekeurde wijzigingen.
expectBlocked(
    fn() => $service->createPlanned(
        '1', 12, 12, 1, 'Boven oorspronkelijke som', '100000.01'
    ),
    'Termijn zonder meerwerk boven contractwaarde toegestaan'
);

echo "PASS: termijnregistratie actuele contractwaarde\n";
