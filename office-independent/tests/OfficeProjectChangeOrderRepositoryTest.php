<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Project/OfficeProjectChangeOrderRepository.php';

use Brebo\Office\Project\OfficeProjectChangeOrderRepository;

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$db->exec(
    'CREATE TABLE office_project_change_orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        contract_id INTEGER NOT NULL,
        change_number TEXT NOT NULL,
        description TEXT NOT NULL,
        change_type TEXT NOT NULL,
        amount DECIMAL(15,2) NOT NULL,
        status TEXT NOT NULL DEFAULT "draft",
        approved_at TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(contract_id, change_number)
    )'
);

$repository = new OfficeProjectChangeOrderRepository($db);

$moreId = $repository->createDraft(
    10, 'MW-001', 'Extra werkzaamheden', 'more', '2500.00'
);

$lessId = $repository->createDraft(
    10, 'MW-002', 'Vervallen werkzaamheden', 'less', '750.00'
);

$repository->createDraft(
    11, 'MW-001', 'Ander contract', 'more', '1000.00'
);

if ($moreId < 1 || $lessId < 1) {
    throw new RuntimeException('Wijzigingen niet aangemaakt');
}

$changes = $repository->forContract(10);

if (count($changes) !== 2) {
    throw new RuntimeException('Onjuist aantal wijzigingen');
}

if ($changes[0]['status'] !== 'draft') {
    throw new RuntimeException('Conceptstatus ontbreekt');
}

if (count($repository->forContract(11)) !== 1) {
    throw new RuntimeException('Contractisolatie mislukt');
}

if (count($repository->approvedForContract(10)) !== 0) {
    throw new RuntimeException('Concept telt onterecht mee');
}

// Simuleer een goedgekeurde wijziging in de testdatabase.
// De echte goedkeuringsworkflow wordt afzonderlijk gebouwd.
$db->exec(
    "UPDATE office_project_change_orders
     SET status = 'approved'
     WHERE id = " . (int) $moreId
);

$approved = $repository->approvedForContract(10);

if (count($approved) !== 1 ||
    $approved[0]['change_type'] !== 'more') {
    throw new RuntimeException('Goedgekeurde wijziging ontbreekt');
}

foreach ([
    ['more', '-10.00'],
    ['more', '0.00'],
    ['more', '10.123'],
    ['invalid', '10.00'],
] as [$type, $amount]) {
    try {
        $repository->createDraft(
            10, 'INVALID-' . $type . $amount,
            'Ongeldige wijziging',
            $type,
            $amount
        );
    } catch (InvalidArgumentException $e) {
        continue;
    }

    throw new RuntimeException('Ongeldige wijziging geaccepteerd');
}

echo "PASS: meer- en minderwerkrepository\n";
