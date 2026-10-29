<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Finance/OfficeFinanceAccess.php';
require_once __DIR__ . '/../src/Project/OfficeProjectContractRepository.php';
require_once __DIR__ . '/../src/Project/OfficeProjectChangeOrderRepository.php';
require_once __DIR__ . '/../src/Project/OfficeProjectTermRepository.php';
require_once __DIR__ . '/../src/Project/OfficeProjectContractValue.php';
require_once __DIR__ . '/../src/Project/OfficeProjectFinanceOverview.php';

use Brebo\Office\Project\OfficeProjectFinanceOverview;

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
    CREATE TABLE office_module_permissions (
        operator_id INTEGER,
        module TEXT,
        permission TEXT
    );
    CREATE TABLE office_project_memberships (
        operator_id INTEGER,
        project_id INTEGER,
        permission TEXT
    );
    CREATE TABLE office_project_contracts (
        id INTEGER PRIMARY KEY,
        office_project_id INTEGER,
        contract_number TEXT,
        client_name TEXT,
        contract_amount DECIMAL(15,2),
        currency TEXT NOT NULL DEFAULT 'EUR',
        status TEXT,
        signed_at TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE office_project_change_orders (
        id INTEGER PRIMARY KEY,
        contract_id INTEGER,
        change_number TEXT,
        description TEXT,
        change_type TEXT,
        amount DECIMAL(15,2),
        status TEXT,
        approved_at TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE office_project_terms (
        id INTEGER PRIMARY KEY,
        contract_id INTEGER,
        term_number INTEGER,
        description TEXT,
        percentage DECIMAL(7,4),
        amount DECIMAL(15,2),
        status TEXT,
        due_date TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    );

    INSERT INTO office_projects VALUES (12, 'Project A');
    INSERT INTO office_projects VALUES (13, 'Project B');

    INSERT INTO office_operator_accounts VALUES (1, 1);
    INSERT INTO office_operator_accounts VALUES (2, 1);
    INSERT INTO office_operator_accounts VALUES (3, 1);

    INSERT INTO office_module_permissions VALUES (1, 'finance', 'read');
    INSERT INTO office_module_permissions VALUES (2, 'finance', 'read');

    INSERT INTO office_project_memberships VALUES (1, 12, 'manage');
    INSERT INTO office_project_memberships VALUES (2, 13, 'read');
    INSERT INTO office_project_memberships VALUES (3, 12, 'read');

    INSERT INTO office_project_contracts
        (id, office_project_id, contract_number, client_name, contract_amount, status)
    VALUES (10, 12, 'C-10', 'Testklant', 100000.00, 'draft');

    INSERT INTO office_project_change_orders
        (id, contract_id, change_number, description, change_type, amount, status)
    VALUES
        (1, 10, 'M-1', 'Meerwerk', 'more', 2500.00, 'approved'),
        (2, 10, 'M-2', 'Minderwerk', 'less', 750.00, 'approved'),
        (3, 10, 'M-3', 'Concept', 'more', 50000.00, 'draft');

    INSERT INTO office_project_terms
        (id, contract_id, term_number, description, amount, status)
    VALUES (1, 10, 1, 'Eerste termijn', 50000.00, 'planned');
");

$overview = new OfficeProjectFinanceOverview($db);

$result = $overview->forProject('1', 12);

if (count($result) !== 1) {
    throw new RuntimeException('Contract ontbreekt');
}

if ($result[0]['value']['current'] !== '101750.00') {
    throw new RuntimeException('Actuele contractwaarde onjuist');
}

if (count($result[0]['changes']) !== 3) {
    throw new RuntimeException('Wijzigingen ontbreken');
}

if (count($result[0]['terms']) !== 1) {
    throw new RuntimeException('Termijn ontbreekt');
}

echo "PASS: financieel overzicht en actuele contractwaarde\n";

foreach ([
    ['2', 12, 'Geen projecttoegang'],
    ['3', 12, 'Geen Finance-rechten'],
    ['1', 13, 'Ander project'],
] as [$operatorId, $projectId, $reason]) {
    try {
        $overview->forProject($operatorId, $projectId);
    } catch (DomainException $e) {
        continue;
    }

    throw new RuntimeException('Ongeautoriseerde toegang: ' . $reason);
}

echo "PASS: financiële autorisatie per medewerker en project\n";
