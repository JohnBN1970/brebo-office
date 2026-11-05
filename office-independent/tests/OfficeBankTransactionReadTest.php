<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Finance/OfficeBankTransactionRead.php';
use Brebo\Office\Finance\OfficeBankTransactionRead;
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_bank_transactions (provider TEXT, account_id TEXT, transaction_id TEXT, booking_date TEXT, currency TEXT, amount_minor INTEGER, description TEXT)');
$insert = $db->prepare('INSERT INTO office_bank_transactions VALUES (?, ?, ?, ?, ?, ?, ?)');
$insert->execute(['abnamro','NL01TEST','t1','2026-11-01','EUR',100,'First']);
$insert->execute(['abnamro','NL01TEST','t2','2026-11-02','EUR',-200,'Second']);
$insert->execute(['abnamro','NL02TEST','t3','2026-11-03','EUR',300,'Other account']);
$read = new OfficeBankTransactionRead($db);
$rows = $read->forAccount('abnamro','NL01TEST');
if (count($rows) !== 2 || $rows[0]['transaction_id'] !== 't2' || $rows[1]['transaction_id'] !== 't1') throw new RuntimeException('Account scope or ordering failed');
if (count($read->forAccount('abnamro','NL01TEST',1)) !== 1) throw new RuntimeException('Limit failed');
try { $read->forAccount('abnamro','NL01TEST',501); throw new RuntimeException('Limit accepted'); }
catch (InvalidArgumentException $e) {}
echo "OfficeBankTransactionReadTest OK\n";
