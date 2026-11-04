<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Finance/OfficePaymentReleaseReadRepository.php';

use Brebo\Office\Finance\OfficePaymentReleaseReadRepository;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
  fwrite(STDERR, "SKIP: pdo_sqlite unavailable\n");
  exit(0);
}
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE TABLE brebo_finance_purchase_invoice (
  id INTEGER PRIMARY KEY, project_nid INTEGER, invoice_number TEXT,
  status TEXT, match_status TEXT, amount_inc_vat TEXT,
  regular_account_amount TEXT, g_account_amount TEXT, currency TEXT
)');
$pdo->exec('CREATE TABLE brebo_finance_payment_release (
  id INTEGER PRIMARY KEY, invoice_id INTEGER, project_nid INTEGER,
  release_number TEXT, status TEXT, approved INTEGER, approved_by INTEGER,
  executed INTEGER, executed_by INTEGER, regular_account_amount TEXT,
  g_account_amount TEXT, total_amount TEXT, currency TEXT
)');
$pdo->exec("INSERT INTO brebo_finance_purchase_invoice VALUES
 (1,20,'TEST-1','approved','matched','121.0000','121.0000','0.0000','EUR')");
$pdo->exec("INSERT INTO brebo_finance_payment_release VALUES
 (1,1,20,'TEST-REL-1','approved',123,5,NULL,NULL,'121.0000','0.0000','121.0000','EUR')");
$repo = new OfficePaymentReleaseReadRepository($pdo, true);
$checks = 0;
$accept = static function () use ($repo, &$checks): void {
  $result = $repo->approved(1);
  if ((int)$result['release_id'] !== 1) throw new RuntimeException('Valid release rejected');
  $checks++;
};
$reject = static function () use ($repo, &$checks): void {
  try { $repo->approved(1); } catch (DomainException $e) { $checks++; return; }
  throw new RuntimeException('Unsafe release accepted');
};
$change = static function (string $table, string $column, string $value) use ($pdo): void {
  if (!in_array($table, ['brebo_finance_purchase_invoice','brebo_finance_payment_release'], true)
    || !preg_match('/^[a-z_]+$/D', $column)) throw new RuntimeException('Unsafe test column');
  $stmt = $pdo->prepare("UPDATE $table SET $column = ? WHERE id = 1");
  $stmt->execute([$value]);
};
$case = static function (string $table, string $column, string $value) use ($pdo, $change, $reject): void {
  $pdo->exec('SAVEPOINT testcase');
  try { $change($table,$column,$value); $reject(); }
  finally { $pdo->exec('ROLLBACK TO SAVEPOINT testcase'); $pdo->exec('RELEASE SAVEPOINT testcase'); }
};
$accept();
$case('brebo_finance_payment_release','status','requested');
$case('brebo_finance_payment_release','approved','0');
$case('brebo_finance_payment_release','approved_by','0');
$case('brebo_finance_payment_release','executed','99');
$case('brebo_finance_payment_release','project_nid','21');
$case('brebo_finance_payment_release','currency','USD');
$case('brebo_finance_payment_release','total_amount','121.0001');
$case('brebo_finance_payment_release','regular_account_amount','120.9999');
$case('brebo_finance_payment_release','g_account_amount','1.0000');
$case('brebo_finance_purchase_invoice','status','paid');
$case('brebo_finance_purchase_invoice','match_status','unmatched');
$case('brebo_finance_purchase_invoice','currency','USD');
$case('brebo_finance_purchase_invoice','amount_inc_vat','122.0000');
$case('brebo_finance_purchase_invoice','regular_account_amount','120.0000');
echo "OfficePaymentReleaseReadRepositoryTest OK ($checks checks)\n";
