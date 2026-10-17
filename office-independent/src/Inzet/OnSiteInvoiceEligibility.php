<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Determines whether a reviewed subcontractor session may enter invoice
 * matching. This does not create a payable, approve an invoice, or withhold
 * payment: disputes and alternative evidence require manual handling.
 */
final class OnSiteInvoiceEligibility {
  /**
   * @param array<string,mixed> $session
   * @param array<string,mixed> $review
   * @return array{eligible:bool,reason:string,seconds:int}
   */
  public function evaluate(array $session, array $review): array {
    if (empty($session['in_event_id']) || empty($session['out_event_id'])
      || !isset($session['project_id'], $session['person_id'])) {
      return ['eligible'=>false,'reason'=>'incomplete_clock_evidence','seconds'=>0];
    }
    if (($review['status'] ?? null) !== 'approved') {
      return ['eligible'=>false,'reason'=>'hours_not_approved','seconds'=>0];
    }
    $seconds = $review['reviewed_seconds'] ?? null;
    if (!is_int($seconds) || $seconds < 0 || $seconds > 86400) {
      return ['eligible'=>false,'reason'=>'invalid_reviewed_duration','seconds'=>0];
    }
    return ['eligible'=>true,'reason'=>'approved_for_invoice_matching','seconds'=>$seconds];
  }
}
