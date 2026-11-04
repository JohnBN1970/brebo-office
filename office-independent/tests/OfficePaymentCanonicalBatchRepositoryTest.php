<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Finance/OfficePaymentCanonicalSeal.php';
require_once dirname(__DIR__) . '/src/Finance/OfficePaymentTrustedBatchResolver.php';
require_once dirname(__DIR__) . '/src/Finance/OfficePaymentCanonicalBatchRepository.php';
use Brebo\Office\Finance\OfficePaymentCanonicalBatchRepository;
if (!extension_loaded('pdo_sqlite')) throw new RuntimeException('pdo_sqlite required');
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE brebo_finance_payment_batch (id INTEGER PRIMARY KEY, status TEXT, batch_number TEXT, execution_date TEXT, currency TEXT, sealed_hash TEXT, item_count INTEGER, created_by INTEGER, released_by INTEGER, controller_verdict TEXT, control_sum TEXT)');
$db->exec('CREATE TABLE brebo_finance_payment_batch_item (id INTEGER PRIMARY KEY, batch_id INTEGER, position INTEGER, release_id INTEGER, invoice_id INTEGER, instruction_type TEXT, amount TEXT, currency TEXT, creditor_name TEXT, creditor_iban TEXT, creditor_bic TEXT, recipient_hash TEXT, end_to_end_id TEXT, remittance_information TEXT, status TEXT)');
$item = ['release_id'=>5,'invoice_id'=>9,'instruction_type'=>'regular','amount'=>'50.00',
  'currency'=>'EUR','creditor_name'=>'Supplier','creditor_iban'=>'NL91ABNA0417164300',
  'creditor_bic'=>null,'recipient_hash'=>'recipient','end_to_end_id'=>'invoice-9',
  'remittance_information'=>'Invoice 9'];
$payload = ['batch_number'=>'BRB-27','execution_date'=>'2026-11-10','currency'=>'EUR','items'=>[$item]];
$seal = hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));
$insert = $db->prepare('INSERT INTO brebo_finance_payment_batch VALUES (27,?,?,?,?,?,?,?,?,?,?)');
$insert->execute(['released','BRB-27','2026-11-10','EUR',$seal,1,3,4,'green','50.00']);
$insert = $db->prepare('INSERT INTO brebo_finance_payment_batch_item VALUES (1,27,1,?,?,?,?,?,?,?,?,?,?,?,?)');
$insert->execute(array_values($item));
try { (new OfficePaymentCanonicalBatchRepository($db))->load(27); throw new RuntimeException('Unverified schema accepted'); }
catch (DomainException $expected) {}
$repo = new OfficePaymentCanonicalBatchRepository($db, true);
if ($repo->resolver()->resolve(27)['sealed_hash'] !== $seal) throw new RuntimeException('Valid batch rejected');
$db->exec("UPDATE brebo_finance_payment_batch_item SET creditor_iban = 'NL02ABNA0123456789' WHERE id = 1");
try { $repo->load(27); throw new RuntimeException('Modified recipient accepted'); }
catch (DomainException $expected) {}
if ($db->inTransaction()) throw new RuntimeException('Read transaction leaked');
echo "OfficePaymentCanonicalBatchRepositoryTest OK\n";
