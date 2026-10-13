<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Calculation/OfficeCalcRequestReplayGuard.php';

use Brebo\Office\Calculation\OfficeCalcRequestReplayGuard;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
  echo "SKIP: PDO SQLite not installed.\n";
  exit(0);
}
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE office_calc_request_nonces (request_id TEXT PRIMARY KEY, request_timestamp INTEGER NOT NULL)');
$guard = new OfficeCalcRequestReplayGuard($db);
$id = '12345678-1234-4234-8234-123456789abc';
if (!$guard->accept($id, 1791890000, 1791890000)) {
  throw new RuntimeException('First valid request rejected.');
}
if ($guard->accept(strtoupper($id), 1791890000, 1791890000)) {
  throw new RuntimeException('Replay accepted.');
}
if ($guard->accept('invalid', 1791890000, 1791890000)) {
  throw new RuntimeException('Malformed ID accepted.');
}
if ($guard->accept('12345678-1234-4234-8234-123456789abd', 1791890000, 1791891000)) {
  throw new RuntimeException('Expired request accepted.');
}
echo "Office Calc replay protection checks passed.\n";
