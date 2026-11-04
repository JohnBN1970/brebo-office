<?php
declare(strict_types=1);

/**
 * Static contract checks for the additive Finance batch foundation migration.
 * No database connection, no writes, and no bank activation.
 */
$sql = file_get_contents(dirname(__DIR__) . '/sql/office-finance-payment-batch-foundation.sql');
if (!is_string($sql)) {
  throw new RuntimeException('Finance foundation migration missing');
}
$tables = [
  'brebo_finance_payment_batch'=>[
    'id','batch_number','status','execution_date','currency','item_count',
    'control_sum','payload_hash','controller_verdict','controller_payload',
    'reviewed','reviewed_by','release_note','released','released_by',
    'sealed_hash','created','created_by','changed','changed_by',
  ],
  'brebo_finance_payment_batch_item'=>[
    'id','batch_id','position','project_nid','release_id','invoice_id',
    'instruction_type','amount','currency','creditor_name','creditor_iban',
    'creditor_bic','recipient_hash','end_to_end_id','remittance_information',
    'status','created','created_by',
  ],
  'brebo_finance_audit'=>[
    'id','project_nid','entity_type','entity_id','action','before_hash',
    'after_hash','payload','reason','created','created_by',
  ],
];
foreach ($tables as $table=>$columns) {
  if (!preg_match('/CREATE TABLE IF NOT EXISTS\\s+' . preg_quote($table, '/') . '\\s*\\((.*?)\\) ENGINE=InnoDB/s', $sql, $matches)) {
    throw new RuntimeException('Missing InnoDB table: ' . $table);
  }
  foreach ($columns as $column) {
    if (!preg_match('/^\\s*' . preg_quote($column, '/') . '\\s+[A-Z]/mi', $matches[1])) {
      throw new RuntimeException('Missing column: ' . $table . '.' . $column);
    }
  }
}
foreach ([
  'UNIQUE KEY uq_brebo_finance_payment_batch_number (batch_number)',
  'UNIQUE KEY uq_brebo_finance_payment_batch_position (batch_id, position)',
  'UNIQUE KEY uq_brebo_finance_payment_batch_end_to_end (batch_id, end_to_end_id)',
  'FOREIGN KEY (batch_id) REFERENCES brebo_finance_payment_batch (id)',
] as $constraint) {
  if (!str_contains($sql, $constraint)) {
    throw new RuntimeException('Missing constraint: ' . $constraint);
  }
}
if (preg_match('/\\b(DROP|TRUNCATE|DELETE|UPDATE|INSERT|REPLACE|ALTER)\\b/i', preg_replace('/^--.*$/m', '', $sql))) {
  throw new RuntimeException('Migration must contain only additive CREATE statements');
}
echo "OfficeFinancePaymentBatchFoundationSqlTest OK\\n";
