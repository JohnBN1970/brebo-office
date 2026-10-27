<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Finance/OfficeOutgoingInvoiceDraft.php';

use Brebo\Office\Finance\OfficeOutgoingInvoiceDraft;

function expectInvalid(array $data): void {
  try {
    OfficeOutgoingInvoiceDraft::validate($data);
  } catch (InvalidArgumentException $e) {
    return;
  }
  throw new RuntimeException('Expected validation failure');
}

$valid = [
  'customer_name' => 'Example BV',
  'office_project_id' => 7,
  'lines' => [
    ['description'=>'Montage', 'quantity'=>'2.000', 'unit_price'=>'50.00', 'vat_regime_id'=>'standard'],
  ],
];
$draft = OfficeOutgoingInvoiceDraft::validate($valid);
if ($draft['status'] !== 'draft' || $draft['office_project_id'] !== 7) throw new RuntimeException('Invalid normalization');
$missingVat = $valid;
unset($missingVat['lines'][0]['vat_regime_id']);
expectInvalid($missingVat);
$invalidQuantity = $valid;
$invalidQuantity['lines'][0]['quantity'] = '0';
expectInvalid($invalidQuantity);
echo "Outgoing invoice draft validation OK\n";
