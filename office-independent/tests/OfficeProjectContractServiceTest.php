<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Finance/OfficeFinanceAccess.php';
require_once __DIR__ . '/../src/Project/OfficeNativeProjectAccess.php';
require_once __DIR__ . '/../src/Project/OfficeProjectContractRepository.php';
require_once __DIR__ . '/../src/Project/OfficeProjectContractService.php';

use Brebo\Office\Project\OfficeProjectContractService;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    fwrite(STDERR, "FAIL: SQLite PDO-driver ontbreekt\n");
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
    CREATE TABLE office_project_contracts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        office_project_id INTEGER NOT NULL,
        contract_number TEXT,
        client_name TEXT,
        contract_amount DECIMAL(15,2),
        currency TEXT,
        status TEXT,
        signed_at TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    );
");

$db->exec("
    INSERT INTO office_projects VALUES (12, 'Testproject');
    INSERT INTO office_operator_accounts VALUES (1, 1), (2, 1), (3, 1);
    INSERT INTO office_project_memberships VALUES
        (1, 12, 'manage'),
        (2, 12, 'read'),
        (3, 12, 'manage');
    INSERT INTO office_module_permissions VALUES
        (1, 'finance', 'read'),
        (2, 'finance', 'read');
");

$service = new OfficeProjectContractService($db);

function expectDenied(callable $action, string $message): void
{
    try {
        $action();
    } catch (DomainException $e) {
        return;
    }

    throw new RuntimeException($message);
}

$id = $service->createDraft('1', 12, 'C-001', 'Opdrachtgever', '100000.00');

if ($id < 1 || count($service->listForProject('1', 12)) !== 1) {
    throw new RuntimeException('Bevoegd contractbeheer mislukt');
}

if (count($service->listForProject('2', 12)) !== 1) {
    throw new RuntimeException('Bevoegd lezen mislukt');
}

expectDenied(
    fn() => $service->createDraft('2', 12, 'C-002', null, '100.00'),
    'Leesgebruiker kon contract aanmaken'
);

expectDenied(
    fn() => $service->listForProject('3', 12),
    'Gebruiker zonder Finance-rechten kon contract lezen'
);

expectDenied(
    fn() => $service->createDraft('3', 12, 'C-003', null, '100.00'),
    'Gebruiker zonder Finance-rechten kon contract aanmaken'
);

expectDenied(
    fn() => $service->listForProject('1', 99),
    'Gebruiker kon ander project lezen'
);

echo "PASS: contractservice autorisatie\n";
