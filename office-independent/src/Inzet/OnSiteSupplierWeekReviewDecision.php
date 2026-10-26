<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Validates an explicit review decision before a persistence layer records it.
 * This class does not mutate the database or mark invoices payable.
 */
final class OnSiteSupplierWeekReviewDecision {
  /**
   * @param array<string,mixed> $workbench
   * @return array<string,mixed>
   */
  public function decide(array $workbench, int $reviewerId, string $action, string $reason, string $sourceHash): array {
    if ($reviewerId < 1 || (int)($workbench['supplier_id'] ?? 0) < 1) {
      throw new \InvalidArgumentException('Reviewer and supplier required.');
    }
    if (!in_array($action, ['approve','reject','request_correction'], true)) {
      throw new \InvalidArgumentException('Unknown review action.');
    }
    $reason = trim($reason);
    if ($action !== 'approve' && $reason === '') {
      throw new \InvalidArgumentException('Rejection or correction requires explanation.');
    }
    if ($action === 'approve' && ($workbench['can_approve'] ?? false) !== true) {
      throw new \DomainException('Unresolved hours or notes prevent approval.');
    }
    if (!preg_match('/^[a-f0-9]{64}$/D', $sourceHash)) {
      throw new \InvalidArgumentException('Original XLSX SHA-256 hash required.');
    }
    return [
      'supplier_id'=>(int)$workbench['supplier_id'],
      'reviewer_id'=>$reviewerId,
      'action'=>$action,
      'reason'=>$reason,
      'source_sha256'=>$sourceHash,
      'review_status'=>match ($action) {
        'approve'=>'approved',
        'reject'=>'rejected',
        default=>'correction_requested',
      },
      'recorded'=>false,
      'invoice_eligible'=>false,
    ];
  }
}
