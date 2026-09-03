<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

/** Builds the daily operational payables queues from authoritative finance state. */
final class PayablesWorkQueueBuilder {

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly PurchaseInvoiceControlViewBuilder $controlViewBuilder,
  ) {}

  /** @return array<string,mixed> */
  public function build(AccountInterface $account): array {
    $queues = [
      'to_code' => [],
      'blocked' => [],
      'to_match' => [],
      'release_ready' => [],
      'to_approve' => [],
      'ready_to_pay' => [],
    ];

    if (!$this->database->schema()->tableExists('brebo_finance_purchase_invoice')) {
      return $this->result($queues);
    }

    $invoices = $this->database->select('brebo_finance_purchase_invoice', 'i')
      ->fields('i')
      ->condition('status', ['paid', 'cancelled'], 'NOT IN')
      ->orderBy('due_date')
      ->orderBy('id')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    foreach ($invoices as $invoice) {
      $projectNid = (int) ($invoice['project_nid'] ?? 0);
      $projectLabel = 'Niet gekoppeld';
      if ($projectNid > 0) {
        $project = $this->entityTypeManager->getStorage('node')->load($projectNid);
        if ($project === NULL || $project->bundle() !== 'brebo_project' || !$project->access('view', $account)) {
          continue;
        }
        $projectLabel = (string) $project->label();
      }

      $control = $this->controlViewBuilder->build((int) $invoice['id']);
      $lines = (array) ($control['lines'] ?? []);
      $summary = (array) ($control['summary'] ?? []);
      $release = $control['payment_release'] ?? NULL;
      $missingCommitment = $lines !== [] && array_filter($lines, static fn(array $line): bool => empty($line['commitment_line_id'])) !== [];
      $blocked = (int) ($summary['blocked_lines'] ?? 0) > 0 || (string) ($invoice['match_status'] ?? '') === 'exception';

      $queue = match (TRUE) {
        is_array($release) && (string) ($release['status'] ?? '') === 'pending_approval' => 'to_approve',
        is_array($release) && (string) ($release['status'] ?? '') === 'approved' => 'ready_to_pay',
        $projectNid <= 0 || $lines === [] || $missingCommitment => 'to_code',
        $blocked => 'blocked',
        (string) ($invoice['match_status'] ?? 'unmatched') !== 'matched' => 'to_match',
        default => 'release_ready',
      };

      $queues[$queue][] = $this->item($invoice, $projectLabel, $summary, $release);
    }

    foreach ($queues as &$items) {
      usort($items, static function (array $a, array $b): int {
        return ($a['priority_rank'] <=> $b['priority_rank'])
          ?: strcmp((string) $a['due_date'], (string) $b['due_date'])
          ?: ($a['invoice_id'] <=> $b['invoice_id']);
      });
    }
    unset($items);

    return $this->result($queues);
  }

  /** @param array<string,mixed> $invoice
   *  @param array<string,mixed> $summary
   *  @param array<string,mixed>|null $release
   *  @return array<string,mixed>
   */
  private function item(array $invoice, string $projectLabel, array $summary, ?array $release): array {
    $dueDate = (string) ($invoice['due_date'] ?? '');
    [$priority, $rank] = $this->priority($dueDate);
    return [
      'invoice_id' => (int) $invoice['id'],
      'invoice_number' => (string) ($invoice['invoice_number'] ?? ('#' . $invoice['id'])),
      'supplier_name' => (string) ($invoice['supplier_name'] ?? ''),
      'project_nid' => (int) ($invoice['project_nid'] ?? 0),
      'project_label' => $projectLabel,
      'invoice_date' => (string) ($invoice['invoice_date'] ?? ''),
      'due_date' => $dueDate,
      'amount_inc_vat' => (string) ($invoice['amount_inc_vat'] ?? '0'),
      'status' => (string) ($invoice['status'] ?? ''),
      'match_status' => (string) ($invoice['match_status'] ?? 'unmatched'),
      'line_count' => (int) ($summary['line_count'] ?? 0),
      'unmatched_lines' => (int) ($summary['unmatched_lines'] ?? 0),
      'blocked_lines' => (int) ($summary['blocked_lines'] ?? 0),
      'priority' => $priority,
      'priority_rank' => $rank,
      'payment_release_id' => is_array($release) ? (int) ($release['id'] ?? 0) : 0,
      'payment_release_status' => is_array($release) ? (string) ($release['status'] ?? '') : '',
    ];
  }

  /** @return array{0:string,1:int} */
  private function priority(string $dueDate): array {
    if ($dueDate === '') {
      return ['geen vervaldatum', 4];
    }
    $today = new \DateTimeImmutable('today');
    try {
      $due = new \DateTimeImmutable($dueDate);
    }
    catch (\Exception) {
      return ['ongeldige vervaldatum', 4];
    }
    $days = (int) $today->diff($due)->format('%r%a');
    return match (TRUE) {
      $days < 0 => ['vervallen', 0],
      $days === 0 => ['vandaag', 1],
      $days <= 7 => ['binnen 7 dagen', 2],
      default => ['later', 3],
    };
  }

  /** @param array<string,array<int,array<string,mixed>>> $queues
   *  @return array<string,mixed>
   */
  private function result(array $queues): array {
    $counts = [];
    $amounts = [];
    foreach ($queues as $name => $items) {
      $counts[$name] = count($items);
      $amounts[$name] = number_format(array_reduce($items, static fn(float $sum, array $item): float => $sum + (float) $item['amount_inc_vat'], 0.0), 2, '.', '');
    }
    return [
      'generated_at' => time(),
      'counts' => $counts,
      'amounts_inc_vat' => $amounts,
      'queues' => $queues,
      'basis' => 'Operational classification only. Matching, performance, phase-gate, G-account and payment decisions remain authoritative in the existing Finance managers.',
    ];
  }
}
