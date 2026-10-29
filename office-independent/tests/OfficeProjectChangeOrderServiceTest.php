<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Finance/OfficeFinanceAccess.php';
require_once __DIR__ . '/../src/Project/OfficeNativeProjectAccess.php';
require_once __DIR__ . '/../src/Project/OfficeProjectChangeOrderRepository.php';
require_once __DIR__ . '/../src/Project/OfficeProjectChangeOrderService.php';

use Brebo\Office\Project\OfficeProjectChangeOrderService;

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
        status TEXT NOT NULL
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

    INSERT INTO office_projects VALUES
        (12, 'Project A'), (13, 'Project B');

    INSERT INTO office_operator_accounts VALUES
        (1, 1), (2, 1), (3, 1);

    INSERT INTO office_project_memberships VALUES
        (1, 12, 'manage'),
        (2, 12, 'read'),
        (3, 12, 'manage');

    INSERT INTO office_module_permissions VALUES
        (1, 'finance', 'read'),
        (2, 'finance', 'read');

    INSERT INTO office_project_contracts VALUES
        (10, 12, 'draft'),
        (11, 13, 'draft'),
        (12, 12, 'closed');
");

$service = new OfficeProjectChangeOrderService($db);

function expectChangeDenied(callable $action, string $message): void
{
    try {
        $action();
    } catch (DomainException $e) {
        return;
    }

    throw new RuntimeException($message);
}

$id = $service->createDraft(
    '1', 12, 10, 'MW-001',
    'Extra werkzaamheden', 'more', '2500.00'
);

if ($id < 1) {
    throw new RuntimeException('Conceptwijziging niet aangemaakt');
}

$changes = $service->listForContract('1', 12, 10);

if (count($changes) !== 1 || $changes[0]['status'] !== 'draft') {
    throw new RuntimeException('Conceptwijziging niet correct opgeslagen');
}

if (count($service->listForContract('2', 12, 10)) !== 1) {
    throw new RuntimeException('Leesrechten werken niet');
}

expectChangeDenied(
    fn() => $service->createDraft(
        '2', 12, 10, 'MW-002', 'Onbevoegd', 'more', '100.00'
    ),
    'Leesgebruiker kon wijziging aanmaken'
);

expectChangeDenied(
    fn() => $service->listForContract('3', 12, 10),
    'Gebruiker zonder Finance-rechten kon lezen'
);

expectChangeDenied(
    fn() => $service->listForContract('1', 12, 11),
    'Contract van ander project kon worden gelezen'
);

expectChangeDenied(
    fn() => $service->createDraft(
        '1', 12, 12, 'MW-003',
        'Afgesloten contract', 'less', '100.00'
    ),
    'Afgesloten contract kon worden gewijzigd'
);

echo "PASS: meer- en minderwerkservice autorisatie\n";
