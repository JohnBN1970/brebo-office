<?php
declare(strict_types=1);

// Runs with an isolated in-memory database; no staging or production data is read.
require_once dirname(__DIR__) . '/src/Calculation/OfficeCalcActiveOfficeSession.php';

use Brebo\Office\Calculation\OfficeCalcActiveOfficeSession;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    fwrite(STDERR, "SKIP: pdo_sqlite required for session integration tests\n");
    exit(2);
}
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_operator_accounts (id TEXT PRIMARY KEY, active INTEGER NOT NULL)');
$db->exec('CREATE TABLE office_operator_sessions (token_hash TEXT PRIMARY KEY, operator_id TEXT NOT NULL, expires_at TEXT NOT NULL, revoked_at TEXT NULL)');
$db->exec("INSERT INTO office_operator_accounts(id,active) VALUES ('7',1),('8',0)");
$now = 1762500000;
$hash = str_repeat('a', 64);
$future = gmdate('Y-m-d H:i:s', $now + 300);
$past = gmdate('Y-m-d H:i:s', $now - 300);
$insert = $db->prepare('INSERT INTO office_operator_sessions(token_hash,operator_id,expires_at,revoked_at) VALUES (?,?,?,?)');
$insert->execute([$hash, '7', $future, null]);
$validator = new OfficeCalcActiveOfficeSession($db);
$cases = [
    ['active owner', $hash, 7, $now, true],
    ['wrong owner', $hash, 8, $now, false],
    ['missing session', str_repeat('b',64), 7, $now, false],
    ['invalid hash', 'bad', 7, $now, false],
    ['invalid actor', $hash, 0, $now, false],
    ['invalid time', $hash, 7, 0, false],
    ['expired by clock', $hash, 7, $now + 301, false],
];
foreach ($cases as [$label,$token,$actor,$time,$expected]) {
    if ($validator->isActive($token,$actor,$time) !== $expected) {
        fwrite(STDERR, "FAIL: $label\n");
        exit(1);
    }
}
$db->exec("UPDATE office_operator_sessions SET revoked_at = '$past' WHERE token_hash = '$hash'");
if ($validator->isActive($hash,7,$now)) { fwrite(STDERR,"FAIL: revoked session\n");exit(1); }
$db->exec("UPDATE office_operator_sessions SET revoked_at = NULL WHERE token_hash = '$hash'");
$db->exec("UPDATE office_operator_accounts SET active = 0 WHERE id = '7'");
if ($validator->isActive($hash,7,$now)) { fwrite(STDERR,"FAIL: inactive account\n");exit(1); }
$db->exec('DROP TABLE office_operator_sessions');
if ($validator->isActive($hash,7,$now)) { fwrite(STDERR,"FAIL: schema unavailable\n");exit(1); }
echo "CALC_ACTIVE_SESSION_TESTS=PASS (10 cases)\n";
