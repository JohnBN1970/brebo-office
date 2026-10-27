<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Finance/OfficeInvoiceDirection.php';
require_once dirname(__DIR__) . '/src/Finance/OfficeIncomingInvoiceQueueAdapter.php';
require_once dirname(__DIR__) . '/src/Finance/OfficeFinanceView.php';

use Brebo\Office\Finance\OfficeIncomingInvoiceQueueAdapter;

$data = ['queues'=>[
  'to_code'=>[['invoice_id'=>7,'invoice_number'=>'INK-7','supplier_name'=>'A','project_label'=>'P1','amount_inc_vat'=>120]],
  'blocked'=>[['invoice_id'=>7,'invoice_number'=>'INK-7','supplier_name'=>'A','project_label'=>'P1','amount_inc_vat'=>120]],
  'to_match'=>[['invoice_id'=>8,'invoice_number'=>'INK-8','supplier_name'=>'B','project_label'=>'P2','amount_inc_vat'=>90]],
]];
$rows = OfficeIncomingInvoiceQueueAdapter::fromFinanceRead($data);
if (count($rows) !== 2) throw new RuntimeException('Queue adapter must deduplicate by invoice ID');
if ($rows[0]['direction'] !== 'incoming' || $rows[0]['number'] !== 'INK-7') throw new RuntimeException('Wrong incoming mapping');
if ($rows[1]['status'] !== 'Te matchen') throw new RuntimeException('Wrong queue status');
if (OfficeIncomingInvoiceQueueAdapter::fromFinanceRead(['queues'=>[]]) !== []) throw new RuntimeException('Empty queue must be empty');
echo "OfficeIncomingInvoiceQueueAdapterTest OK\n";
