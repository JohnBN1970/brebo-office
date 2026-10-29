<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Project/OfficeProjectContractRepository.php';

use Brebo\Office\Project\OfficeProjectContractRepository;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo "SKIP: SQLite PDO-driver ontbreekt\n";
    exit(0);
}

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$db->exec(
    'CREATE TABLE office_project_contracts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        office_project_id INTEGER NOT NULL,
        contract_number TEXT,
        client_name TEXT,
        contract_amount DECIMAL(15,2) NOT NULL,
        currency TEXT NOT NULL,
        status TEXT NOT NULL,
        signed_at TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )'
);

$repository = new OfficeProjectContractRepository($db);

$id = $repository->create(12, 'C-001', 'Test opdrachtgever', '125000.00');

assert($id > 0);

$contracts = $repository->forProject(12);

if (count($contracts) !== 1) {
    throw new RuntimeException('Contract niet gevonden');
}

if ($contracts[0]['contract_number'] !== 'C-001') {
    throw new RuntimeException('Contractnummer onjuist');
}

if ($contracts[0]['status'] !== 'draft') {
    throw new RuntimeException('Contractstatus onjuist');
}

if ($repository->forProject(13) !== []) {
    throw new RuntimeException('Projectisolatie mislukt');
}

foreach (['-10.00', '12.345', 'abc'] as $invalid) {
    try {
        $repository->create(12, null, null, $invalid);
        throw new RuntimeException('Ongeldig bedrag geaccepteerd');
    } catch (InvalidArgumentException $e) {
        // Correct afgewezen.
    }
}

echo "PASS: contractrepository\n";
