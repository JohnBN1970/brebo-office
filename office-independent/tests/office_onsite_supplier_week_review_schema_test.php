<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/src/Inzet/OnSiteSupplierWeekReviewSchema.php';

use Brebo\Office\Inzet\OnSiteSupplierWeekReviewSchema;

$statements = OnSiteSupplierWeekReviewSchema::statements();
if (count($statements) !== 2) throw new RuntimeException('Expected separate import and review tables.');
if (!str_contains($statements[0], 'UNIQUE KEY uq_supplier_week_source')
  || !str_contains($statements[0], 'source_sha256')
  || !str_contains($statements[1], 'FOREIGN KEY (import_id)')
  || !str_contains($statements[1], 'reviewer_id')
  || !str_contains($statements[1], 'created_at')) {
  throw new RuntimeException('Missing supplier import fingerprint or append-only review trace.');
}
echo "OnSite supplier week review schema OK\n";
