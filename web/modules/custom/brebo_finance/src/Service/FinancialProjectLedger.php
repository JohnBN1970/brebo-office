<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\brebo_finance\Contract\FinancialProjectLedgerRepositoryInterface;

/** Read-only project ledger for financial drill-down and audit navigation. */
final class FinancialProjectLedger {
  public function __construct(private readonly FinancialProjectLedgerRepositoryInterface $repository, private readonly InvoicePerformanceBlockerResolver $invoiceBlockers) {}

  /** @return array<string, mixed> */
  public function build(int $projectNid): array {
    $ledger=$this->repository->projectLedger($projectNid);
    $invoiceLines=$ledger['purchase_invoice_lines'] ?? [];
    return [
      'project_nid'=>$projectNid,
      'commitments'=>$ledger['commitments'] ?? [],
      'commitment_lines'=>$ledger['commitment_lines'] ?? [],
      'performance_receipts'=>$ledger['performance_receipts'] ?? [],
      'purchase_invoices'=>$ledger['purchase_invoices'] ?? [],
      'purchase_invoice_lines'=>$invoiceLines,
      'invoice_performance_blockers'=>$this->invoicePerformanceBlockers($invoiceLines),
      'change_orders'=>$ledger['change_orders'] ?? [],
      'failure_costs'=>$ledger['failure_costs'] ?? [],
      'payment_releases'=>$ledger['payment_releases'] ?? [],
      'billing'=>$ledger['billing'] ?? [],
      'audit'=>$ledger['audit'] ?? [],
      'basis'=>'Read-only ledger from registered BREBO Finance records. Missing tables return an empty section; values are not inferred.',
    ];
  }

  private function invoicePerformanceBlockers(array $lines): array {
    $out=[];foreach($lines as $line){$id=(int)($line['id']??0);if($id<=0)continue;$analysis=$this->invoiceBlockers->resolve($id);if(($analysis['blocked']??false)||($analysis['verified_shortfall_ex_vat']??0)>0)$out[]=$analysis;}
    usort($out,static function(array $a,array $b):int{$scoreA=(int)($a['priority']['score']??0);$scoreB=(int)($b['priority']['score']??0);if($scoreA!==$scoreB)return $scoreB<=>$scoreA;return (float)($b['verified_shortfall_ex_vat']??0)<=>(float)($a['verified_shortfall_ex_vat']??0);});return$out;
  }
}
