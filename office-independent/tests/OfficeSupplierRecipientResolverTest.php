<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Finance/OfficeSupplierRecipientPolicy.php';
require_once __DIR__ . '/../src/Finance/OfficeSupplierRecipientResolver.php';
use Brebo\Office\Finance\OfficeSupplierRecipientResolver;
function assertFinance(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$account = [
    'relation_id'=>7, 'account_type'=>'regular', 'iban'=>'NL91ABNA0417164300',
    'account_holder'=>'Leverancier BV', 'status'=>'active', 'verification_status'=>'verified',
    'verified_at'=>1000, 'verified_by'=>8, 'verification_evidence_id'=>'ev1', 'changed_at'=>900,
];
$invoice = ['relation_id'=>7, 'source'=>'office', 'invoice_type'=>'purchase'];
$accounts = [$account];
$resolver = new OfficeSupplierRecipientResolver(
    static fn(int $id): ?array => $id === 17 ? $invoice : null,
    static fn(int $id): array => $accounts
);
$snapshot = $resolver->snapshot(17, 2000);
assertFinance($resolver->unchanged(17, $snapshot['recipient_hash'], 2000), 'Verified Office recipient must match');
assertFinance(!$resolver->unchanged(17, str_repeat('a',64), 2000), 'Incorrect hash must fail');
$missing = new OfficeSupplierRecipientResolver(static fn(int $id): ?array => null, static fn(int $id): array => []);
assertFinance(!$missing->unchanged(17, $snapshot['recipient_hash'], 2000), 'Missing invoice must fail');
$foreign = new OfficeSupplierRecipientResolver(static fn(int $id): array => ['relation_id'=>7,'source'=>'moneybird','invoice_type'=>'purchase'], static fn(int $id): array => [$account]);
assertFinance(!$foreign->unchanged(17, $snapshot['recipient_hash'], 2000), 'Moneybird invoice must fail');
$wrong = array_replace($account, ['relation_id'=>8]);
$wrongRelation = new OfficeSupplierRecipientResolver(static fn(int $id): array => $invoice, static fn(int $id): array => [$wrong]);
assertFinance(!$wrongRelation->unchanged(17, $snapshot['recipient_hash'], 2000), 'Other supplier account must fail');
$duplicate = new OfficeSupplierRecipientResolver(static fn(int $id): array => $invoice, static fn(int $id): array => [$account,$account]);
assertFinance(!$duplicate->unchanged(17, $snapshot['recipient_hash'], 2000), 'Ambiguous active accounts must fail');
$changed = array_replace($account, ['iban'=>'NL02ABNA0123456789','changed_at'=>2001]);
$changedResolver = new OfficeSupplierRecipientResolver(static fn(int $id): array => $invoice, static fn(int $id): array => [$changed]);
assertFinance(!$changedResolver->unchanged(17, $snapshot['recipient_hash'], 2000), 'Changed account must fail');
echo "OfficeSupplierRecipientResolver regression checks passed\n";
