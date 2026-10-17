<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Pure view model for Office's supplier-week review screen.
 * Does not approve hours, change invoices, or disclose worker location events.
 */
final class OnSiteSupplierWeekWorkbench {
  /**
   * @param array<string,mixed> $reconciliation Output of OnSiteSupplierWeekReconciliation
   * @return array<string,mixed>
   */
  public function build(array $reconciliation): array {
    $supplierId = $reconciliation['supplier_id'] ?? null;
    if (!is_int($supplierId) || $supplierId < 1) {
      throw new \InvalidArgumentException('Supplier ID required.');
    }
    $lines = [];
    $mismatchCount = 0;
    foreach (($reconciliation['differences'] ?? []) as $row) {
      $difference = $row['difference_minutes'] ?? null;
      if (!is_int($difference)) throw new \InvalidArgumentException('Invalid day difference.');
      if ($difference !== 0) $mismatchCount++;
      $lines[] = [
        'person_id'=>$row['person_id'],
        'project_id'=>$row['project_id'],
        'date'=>$row['date'],
        'onsite_minutes'=>$row['approved_minutes'],
        'supplier_minutes'=>$row['supplier_minutes'],
        'difference_minutes'=>$difference,
        'status'=>$difference === 0 ? 'hours_match' : 'hours_difference',
      ];
    }
    $issues = [];
    foreach (($reconciliation['import_issues'] ?? []) as $issue) {
      $issues[] = [
        'sheet'=>(string)($issue['sheet'] ?? ''),
        'cell'=>(string)($issue['cell'] ?? ''),
        'reason'=>(string)($issue['reason'] ?? 'unknown_issue'),
      ];
    }
    $requiresReview = $mismatchCount > 0 || $issues !== []
      || ($reconciliation['review_required'] ?? true) !== false;
    return [
      'supplier_id'=>$supplierId,
      'status'=>$requiresReview ? 'requires_review' : 'awaiting_explicit_approval',
      'day_lines'=>$lines,
      'issues'=>$issues,
      'difference_count'=>$mismatchCount,
      'issue_count'=>count($issues),
      'onsite_total_minutes'=>(int)($reconciliation['approved_minutes'] ?? 0),
      'supplier_total_minutes'=>(int)($reconciliation['supplier_minutes'] ?? 0),
      'can_approve'=>$requiresReview === false,
      'approved'=>false,
      'invoice_eligible'=>false,
    ];
  }
}
