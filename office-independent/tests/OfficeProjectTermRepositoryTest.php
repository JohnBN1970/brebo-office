<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Project/OfficeProjectTermRepository.php';

use Brebo\Office\Project\OfficeProjectTermRepository;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    fwrite(STDERR, "FAIL: SQLite ontbreekt\n");
    exit(1);
}

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$db->exec(
    'CREATE TABLE office_project_terms (
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
    )'
);

$repository = new OfficeProjectTermRepository($db);

$repository->create(10, 1, 'Bij opdracht', '20000.00', '20');
$repository->create(10, 2, 'Tijdens uitvoering', '75000.00', '75');
$repository->create(10, 3, 'Bij oplevering', '5000.00', '5');
$repository->create(11, 1, 'Ander contract', '1000.00', '100');

$terms = $repository->forContract(10);

if (count($terms) !== 3) {
    throw new RuntimeException('Onjuist aantal termijnen');
}

if (array_column($terms, 'term_number') !== [1, 2, 3]) {
    throw new RuntimeException('Termijnvolgorde onjuist');
}

if (array_column($terms, 'status') !== ['planned', 'planned', 'planned']) {
    throw new RuntimeException('Termijnstatus onjuist');
}

if (count($repository->forContract(11)) !== 1) {
    throw new RuntimeException('Contractisolatie mislukt');
}

foreach (['-1.00', '10.123', 'abc'] as $amount) {
    try {
        $repository->create(10, 4, 'Ongeldig bedrag', $amount);
    } catch (InvalidArgumentException $e) {
        continue;
    }

    throw new RuntimeException('Ongeldig bedrag geaccepteerd: ' . $amount);
}

foreach (['-1', '101', '25.12345', 'abc'] as $percentage) {
    try {
        $repository->create(10, 4, 'Ongeldig percentage', '100.00', $percentage);
    } catch (InvalidArgumentException $e) {
        continue;
    }

    throw new RuntimeException('Ongeldig percentage geaccepteerd: ' . $percentage);
}

echo "PASS: termijnrepository\n";
