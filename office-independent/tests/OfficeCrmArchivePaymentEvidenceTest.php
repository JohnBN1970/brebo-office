<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Crm/OfficeCrmArchivePaymentEvidence.php';
use Brebo\Office\Crm\OfficeCrmArchivePaymentEvidence;
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE office_finance_sales_invoice (relation_id INTEGER, invoice_number TEXT, status TEXT, gross_cents INTEGER, paid_cents INTEGER)');
$reader=new OfficeCrmArchivePaymentEvidence($db);
$insert=$db->prepare('INSERT INTO office_finance_sales_invoice VALUES (?,?,?,?,?)');
$expect=static function(bool $expected,string $case) use ($reader): void {
    if ($reader->allInvoicesPaid(1)!==$expected) throw new RuntimeException($case);
};
$expect(false,'No invoices');
$insert->execute([2,'OTHER','sent',100,0]);
$expect(false,'Unrelated invoice');
$insert->execute([1,'A','sent',100,100]);
$expect(true,'Paid invoice');
foreach ([['B','sent',200,100],['B','draft',200,200],['','sent',200,200],['B','reconciliation_required',200,200],['B','sent',200,201]] as $row) {
    $insert->execute([1,...$row]);
    $expect(false,'Unsettled or invalid invoice');
    $db->exec('DELETE FROM office_finance_sales_invoice WHERE relation_id=1 AND invoice_number<>\'A\'');
    $expect(true,'Paid baseline restored');
}
echo "Payment evidence integration passed\n";
