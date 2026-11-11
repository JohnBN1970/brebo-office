<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Finance/OfficeSupplierRecipientPolicy.php';
require_once __DIR__ . '/../src/Finance/OfficeSupplierRecipientResolver.php';
require_once __DIR__ . '/../../web/modules/custom/brebo_finance/src/Contract/PaymentRecipientGatewayInterface.php';
require_once __DIR__ . '/../../web/modules/custom/brebo_finance/src/Infrastructure/OfficePaymentRecipientGateway.php';
use Brebo\Office\Finance\OfficeSupplierRecipientResolver;
use Drupal\brebo_finance\Infrastructure\OfficePaymentRecipientGateway;
$account = [
 'relation_id'=>7,'account_type'=>'regular','iban'=>'NL91ABNA0417164300',
 'account_holder'=>'Test BV','status'=>'active','verification_status'=>'verified',
 'verified_at'=>1000,'verified_by'=>8,'verification_evidence_id'=>'proof1','changed_at'=>900
];
$resolver = new OfficeSupplierRecipientResolver(
 static fn(int $id): ?array => $id===17 ? ['relation_id'=>7,'source'=>'office','invoice_type'=>'purchase'] : null,
 static fn(int $relation): array => $relation===7 ? [$account] : []
);
$gateway = new OfficePaymentRecipientGateway($resolver, static fn(int $invoice, int $user): bool => $invoice===17 && $user===4, static fn(): int => 2000);
$snapshot = $gateway->snapshot(17,4);
if (!$gateway->unchanged(17,$snapshot['recipient_hash'],4)) throw new RuntimeException('Verified recipient rejected');
if ($gateway->unchanged(17,$snapshot['recipient_hash'],5)) throw new RuntimeException('Unauthorized revalidation accepted');
try { $gateway->snapshot(17,5); throw new RuntimeException('Unauthorized snapshot accepted'); }
catch (RuntimeException $e) { if ($e->getMessage()==='Unauthorized snapshot accepted') throw $e; }
if ($gateway->unchanged(18,$snapshot['recipient_hash'],4)) throw new RuntimeException('Wrong invoice accepted');
echo "OfficePaymentRecipientGateway regression checks passed\n";
