<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Finance/OfficeBankTransactionImport.php';

use Brebo\Office\Finance\OfficeBankTransactionImport;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) throw new RuntimeException('pdo_sqlite required');
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_bank_transactions (deduplication_key TEXT PRIMARY KEY, provider TEXT NOT NULL, account_id TEXT NOT NULL, transaction_id TEXT NOT NULL, booking_date TEXT NOT NULL, currency TEXT NOT NULL, amount_minor INTEGER NOT NULL, description TEXT NOT NULL)');
$import = new OfficeBankTransactionImport($db);
$sample = ['provider'=>'test-bank','account_id'=>'acct-1','transaction_id'=>'t-1','booking_date'=>'2026-11-05','currency'=>'EUR','amount_minor'=>-10050,'description'=>'Invoice'];
$checks = 0;
$assert = static function(bool $ok, string $message) use (&$checks): void {
    if (!$ok) throw new RuntimeException($message);
    ++$checks;
};
$first = $import->import([$sample, $sample]);
$assert($first === ['inserted'=>1,'duplicates'=>1], 'Duplicates within batch ignored');
$assert($import->import([$sample]) === ['inserted'=>0,'duplicates'=>1], 'Repeat import idempotent');
$assert((int)$db->query('SELECT amount_minor FROM office_bank_transactions')->fetchColumn() === -10050, 'Signed amount preserved');
$other = $sample;
$other['transaction_id'] = 't-2';
$other['amount_minor'] = 10050;
$assert($import->import([$other]) === ['inserted'=>1,'duplicates'=>0], 'Distinct transaction imported');
$assert((int)$db->query('SELECT count(*) FROM office_bank_transactions')->fetchColumn() === 2, 'Exactly two stored');
$invalid = $sample;
$invalid['transaction_id'] = 't-3';
$invalid['amount_minor'] = '100';
try {
    $import->import([$sample, $invalid]);
    throw new RuntimeException('Invalid feed accepted');
} catch (DomainException $exception) {
    $assert(true, 'Malformed feed denied before transaction');
}
$assert((int)$db->query('SELECT count(*) FROM office_bank_transactions')->fetchColumn() === 2, 'Invalid feed cannot partially write');
$db->exec('CREATE TRIGGER block_bank_insert BEFORE INSERT ON office_bank_transactions WHEN NEW.transaction_id = "t-4" BEGIN SELECT RAISE(ABORT, "blocked"); END');
$blocked = $sample;
$blocked['transaction_id'] = 't-4';
$prior = $sample;
$prior['transaction_id'] = 't-5';
try {
    $import->import([$prior, $blocked]);
    throw new RuntimeException('Expected database failure');
} catch (PDOException $exception) {
    $assert(true, 'Database write failure propagates');
}
$assert((int)$db->query('SELECT count(*) FROM office_bank_transactions')->fetchColumn() === 2, 'Database failure rolls back entire feed');
echo "OfficeBankTransactionImportTest OK ($checks checks)\n";
