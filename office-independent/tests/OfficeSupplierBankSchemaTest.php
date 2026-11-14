<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Finance/OfficeSupplierBankSchema.php';
use Brebo\Office\Finance\OfficeSupplierBankSchema;
$statements = OfficeSupplierBankSchema::statements();
if (count($statements) !== 3) throw new RuntimeException('Expected exactly three additive statements');
foreach ($statements as $statement) {
 if (!str_starts_with($statement, 'CREATE TABLE IF NOT EXISTS office_')) throw new RuntimeException('Unsafe schema statement');
 if (preg_match('/\\b(?:DROP|TRUNCATE|DELETE|INSERT|ALTER)\\b/i', $statement) || str_contains($statement, ';')) throw new RuntimeException('Unexpected destructive SQL');
}
if (!str_contains($statements[0], 'verified_fingerprint CHAR(64)')) throw new RuntimeException('Missing verified fingerprint');
if (!str_contains($statements[0], "DEFAULT 'pending'")) throw new RuntimeException('Verification must default pending');
if (!str_contains($statements[0], "DEFAULT 'inactive'")) throw new RuntimeException('Bank account must default inactive');
echo "OfficeSupplierBankSchema static checks passed\n";
