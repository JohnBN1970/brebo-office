<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Service;

use Drupal\Core\Database\Connection;

/**
 * Determines whether a supplier invoice may be released for payment.
 */
final class ContractPaymentGate {

  public function __construct(
    private readonly Connection $database,
    private readonly ContractMonitoringService $monitoring,
  ) {}

  /** @return array<string, mixed> */
  public function assess(int $awardId, int $invoiceId, bool $milestoneReached, bool $performanceAccepted, bool $qualityApproved, bool $gAccountCorrect, ?int $now = NULL): array {
    $now ??= time();
    $award = $this->database->select('brebo_procurement_award', 'a')->fields('a')->condition('id', $awardId)->execute()->fetchAssoc();
    if (!$award) {
      throw new \InvalidArgumentException('Onbekende opdrachtverstrekking.');
    }
    $invoice = $this->database->select('brebo_supplier_invoice', 'i')->fields('i')->condition('id', $invoiceId)->execute()->fetchAssoc();
    if (!$invoice) {
      throw new \InvalidArgumentException('Onbekende leveranciersfactuur.');
    }
    if ((int) $invoice['project_nid'] !== (int) $award['project_nid']) {
      return $this->blocked('invoice_project_mismatch', 'Factuur hoort niet bij het project van deze opdracht.');
    }

    $contract = $this->monitoring->status($awardId, $now);
    $checks = [
      'milestone_reached' => $milestoneReached,
      'performance_accepted' => $performanceAccepted,
      'quality_approved' => $qualityApproved,
      'required_documents_current' => $contract['blocking_overdue'] === [],
      'no_critical_deviations' => $contract['critical_deviations'] === [],
      'invoice_approved' => (string) $invoice['approval_status'] === 'approved',
      'three_way_match' => (string) $invoice['match_status'] === 'matched',
      'g_account_correct' => $gAccountCorrect,
    ];

    $failed = array_keys(array_filter($checks, static fn(bool $ok): bool => !$ok));
    if ($failed !== []) {
      return [
        'payable' => FALSE,
        'status' => 'blocked',
        'failed_checks' => $failed,
        'checks' => $checks,
        'message' => 'Betaling geblokkeerd: niet alle contractuele en financiële controles zijn groen.',
      ];
    }

    return [
      'payable' => TRUE,
      'status' => 'released_for_payment',
      'failed_checks' => [],
      'checks' => $checks,
      'invoice_id' => $invoiceId,
      'award_id' => $awardId,
      'message' => 'Termijn, prestatie, kwaliteit, documenten, afwijkingen, 3-way match en G-rekening zijn akkoord. Factuur mag naar betaling.',
    ];
  }

  /** @return array<string, mixed> */
  private function blocked(string $code, string $message): array {
    return ['payable' => FALSE, 'status' => 'blocked', 'failed_checks' => [$code], 'checks' => [], 'message' => $message];
  }
}
