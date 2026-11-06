<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Calculation/OfficeCalcRequestReplayRepository.php';

use Brebo\Office\Calculation\OfficeCalcRequestReplayRepository;

$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE office_calc_signed_request_ids (request_id TEXT PRIMARY KEY, expires_at TEXT NOT NULL)');
$repository = new OfficeCalcRequestReplayRepository($db);
$id = '123e4567-e89b-42d3-a456-426614174000';
if (!$repository->reserve($id, 1793960000)) {
    throw new RuntimeException('First signed request was rejected');
}
if ($repository->reserve($id, 1793960001)) {
    throw new RuntimeException('Replayed signed request was accepted');
}
if ($repository->reserve('invalid', 1793960000)) {
    throw new RuntimeException('Invalid request ID was accepted');
}
if ($repository->reserve('123e4567-e89b-42d3-a456-426614174001', 0)) {
    throw new RuntimeException('Invalid request time was accepted');
}
$db->exec('DROP TABLE office_calc_signed_request_ids');
try {
    $repository->reserve('123e4567-e89b-42d3-a456-426614174002', 1793960000);
    throw new RuntimeException('Missing replay table failed open');
} catch (PDOException $expected) {
}
echo "CALC_SIGNED_REQUEST_REPLAY=PASS\n";
