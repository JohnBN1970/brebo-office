<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/src/Inzet/OnSiteSupplierWeekReviewSchema.php';

use Brebo\Office\Inzet\OnSiteSupplierWeekReviewSchema;

$statements = OnSiteSupplierWeekReviewSchema::statements();
if (count($statements) !== 3) throw new RuntimeException('Expected separate import and review tables.');
if (!str_contains($statements[0], 'PRIMARY KEY (project_id, supplier_id)')
  || !str_contains($statements[1], 'UNIQUE KEY uq_supplier_week_source')
  || !str_contains($statements[1], 'source_sha256')
  || !str_contains($statements[2], 'FOREIGN KEY (import_id)')
  || !str_contains($statements[2], 'reviewer_id')
  || !str_contains($statements[2], 'created_at')) {
  throw new RuntimeException('Missing supplier import fingerprint or append-only review trace.');
}
echo "OnSite supplier week review schema OK\n";
