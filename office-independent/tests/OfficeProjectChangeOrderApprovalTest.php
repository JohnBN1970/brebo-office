<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Finance/OfficeFinanceAccess.php';
require_once __DIR__ . '/../src/Project/OfficeNativeProjectAccess.php';
require_once __DIR__ . '/../src/Project/OfficeProjectChangeOrderRepository.php';
require_once __DIR__ . '/../src/Project/OfficeProjectContractValue.php';
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
        contract_amount DECIMAL(15,2) NOT NULL,
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
        (10, 12, 100000.00, 'draft'),
        (11, 13, 50000.00, 'draft'),
        (12, 12, 10000.00, 'closed');
");

$service = new OfficeProjectChangeOrderService($db);

function approvalDenied(callable $action, string $message): void
{
    try {
        $action();
    } catch (DomainException $e) {
        return;
    }

    throw new RuntimeException($message);
}

$moreId = $service->createDraft(
    '1', 12, 10, 'MW-001',
    'Extra werkzaamheden', 'more', '2500.00'
);

$value = $service->approve('1', 12, 10, $moreId);

if ($value['current'] !== '102500.00') {
    throw new RuntimeException('Meerwerk niet correct verwerkt');
}

approvalDenied(
    fn() => $service->approve('1', 12, 10, $moreId),
    'Dubbele goedkeuring toegestaan'
);

$lessId = $service->createDraft(
    '1', 12, 10, 'MW-002',
    'Vervallen werkzaamheden', 'less', '750.00'
);

approvalDenied(
    fn() => $service->approve('2', 12, 10, $lessId),
    'Leesgebruiker kon goedkeuren'
);

approvalDenied(
    fn() => $service->approve('3', 12, 10, $lessId),
    'Gebruiker zonder Finance-rechten kon goedkeuren'
);

approvalDenied(
    fn() => $service->approve('1', 12, 11, $lessId),
    'Goedkeuring op ander project toegestaan'
);

$value = $service->approve('1', 12, 10, $lessId);

if ($value['current'] !== '101750.00') {
    throw new RuntimeException('Minderwerk niet correct verwerkt');
}

$excessId = $service->createDraft(
    '1', 12, 10, 'MW-003',
    'Te hoog minderwerk', 'less', '150000.00'
);

approvalDenied(
    fn() => $service->approve('1', 12, 10, $excessId),
    'Negatieve contractwaarde toegestaan'
);

$statement = $db->prepare(
    'SELECT status, approved_at
     FROM office_project_change_orders WHERE id = ?'
);
$statement->execute([$excessId]);
$rejected = $statement->fetch(PDO::FETCH_ASSOC);

if ($rejected['status'] !== 'draft' ||
    $rejected['approved_at'] !== null) {
    throw new RuntimeException('Afgewezen goedkeuring is gewijzigd');
}

if ($db->inTransaction()) {
    throw new RuntimeException('Transactie niet afgesloten');
}

echo "PASS: meer- en minderwerk goedkeuring\n";
