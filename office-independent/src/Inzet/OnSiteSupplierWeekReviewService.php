<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Explicit access gate and decision orchestration. The caller must obtain the
 * reviewer identity and supplier grants from trusted Office session/ACL data,
 * never from posted form fields.
 */
final class OnSiteSupplierWeekReviewService {
  public function __construct(private OnSiteSupplierWeekReviewStore $store) {}

  /**
   * @param array<string,mixed> $workbench Freshly computed from trusted import and OnSite records
   * @param list<int> $authorizedSupplierIds Server-side supplier grants for reviewer
   * @return array{decision_id:int,review_status:string,source_sha256:string}
   */
  public function submit(
    int $importId, array $workbench, int $reviewerId, array $authorizedSupplierIds,
    string $action, string $reason, string $sourceHash
  ): array {
    $supplierId = $workbench['supplier_id'] ?? null;
    if (!is_int($supplierId) || $supplierId < 1 || $reviewerId < 1
      || !in_array($supplierId, $authorizedSupplierIds, true)) {
      throw new \DomainException('Reviewer has no access to this supplier.');
    }
    $decision = (new OnSiteSupplierWeekReviewDecision())->decide(
      $workbench, $reviewerId, $action, $reason, $sourceHash
    );
    $id = $this->store->append($importId, $decision);
    return [
      'decision_id'=>$id,
      'review_status'=>$decision['review_status'],
      'source_sha256'=>$decision['source_sha256'],
    ];
  }
}
