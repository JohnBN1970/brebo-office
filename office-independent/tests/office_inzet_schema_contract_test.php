<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetSchema.php';

use Brebo\Office\Inzet\OfficeInzetSchema;

$statements = OfficeInzetSchema::statements();
if (count($statements) !== 4) throw new RuntimeException('Unexpected Inzet table count.');
foreach ($statements as $statement) {
  if (!str_starts_with($statement, 'CREATE TABLE IF NOT EXISTS office_inzet_')) {
    throw new RuntimeException('Unsafe Inzet schema statement.');
  }
  if (preg_match('/\b(DROP|DELETE|TRUNCATE|ALTER|INSERT|UPDATE)\b/i', $statement)) {
    throw new RuntimeException('Destructive or mutating DDL detected.');
  }
}
echo "Office Inzet schema review contract OK\n";
