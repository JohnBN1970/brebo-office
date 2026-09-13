<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use RuntimeException;

/** Controls receivables escalation without creating a second invoice truth. */
final class ReceivablesDunningManager {

  private const STORE = 'brebo_finance.receivables_dunning';

  private const TERMINAL_INVOICE_STATUSES = ['paid', 'credited', 'cancelled'];

  private const STEPS = [
    'reminder' => 'herinnerd',
    'demand' => 'aangemaand',
    'final_notice' => 'laatste_sommatie',
    'collection_ready' => 'gereed_voor_incasso',
  ];

  public function __construct(
    private readonly Connection $database,
    private readonly KeyValueFactoryInterface $keyValueFactory,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /** @return array<string,mixed> */
  public function state(int $salesInvoiceId, ?\DateTimeImmutable $today = NULL): array {
    $invoice = $this->invoice($salesInvoiceId);
    $stored = $this->stored($salesInvoiceId);
    $today ??= new \DateTimeImmutable('today');

    $total = round((float) $invoice['amount_inc_vat'], 2);
    $paid = round((float) $invoice['paid_amount_inc_vat'], 2);
    $outstanding = max(0.0, round($total - $paid, 2));
    $invoiceStatus = (string) $invoice['status'];
    $isDisputed = $invoiceStatus === 'disputed' || trim((string) ($invoice['dispute_reason'] ?? '')) !== '';
    $hasArrangement = !empty($stored['payment_arrangement']);
    $manualHold = !empty($stored['hold']);

    if (in_array($invoiceStatus, self::TERMINAL_INVOICE_STATUSES, TRUE) || $outstanding <= 0.0) {
      $status = $invoiceStatus === 'credited' ? 'gecrediteerd' : ($invoiceStatus === 'cancelled' ? 'gesloten' : 'betaald');
      $blockedReason = NULL;
    }
    elseif ($isDisputed) {
      $status = 'betwist';
      $blockedReason = 'disputed';
    }
    elseif ($hasArrangement) {
      $status = 'regeling';
      $blockedReason = 'payment_arrangement';
    }
    elseif ($manualHold) {
      $status = 'hold';
      $blockedReason = 'manual_hold';
    }
    else {
      $status = (string) ($stored['status'] ?? 'open');
      $blockedReason = NULL;
      $due = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $invoice['due_date']);
      if ($due !== FALSE && $today > $due && $status === 'open') {
        $status = 'vervallen';
      }
    }

    return [
      'sales_invoice_id' => $salesInvoiceId,
      'invoice_number' => (string) $invoice['invoice_number'],
      'invoice_status' => $invoiceStatus,
      'due_date' => (string) $invoice['due_date'],
      'amount_inc_vat' => number_format($total, 2, '.', ''),
      'paid_amount_inc_vat' => number_format($paid, 2, '.', ''),
      'outstanding_amount_inc_vat' => number_format($outstanding, 2, '.', ''),
      'status' => $status,
      'blocked_reason' => $blockedReason,
      'hold' => $manualHold,
      'payment_arrangement' => $stored['payment_arrangement'] ?? NULL,
      'events' => array_values(is_array($stored['events'] ?? NULL) ? $stored['events'] : []),
    ];
  }

  public function setHold(int $salesInvoiceId, bool $hold, string $reason, int $actorUid): void {
    if ($hold && trim($reason) === '') {
      throw new \InvalidArgumentException('Een reden is verplicht bij een debiteuren-hold.');
    }
    $stored = $this->stored($salesInvoiceId);
    $stored['hold'] = $hold;
    $stored['hold_reason'] = $hold ? trim($reason) : '';
    $this->appendEvent($stored, $hold ? 'hold_set' : 'hold_cleared', ['reason' => trim($reason)], $actorUid);
    $this->save($salesInvoiceId, $stored);
  }

  public function setPaymentArrangement(int $salesInvoiceId, ?array $arrangement, int $actorUid): void {
    $stored = $this->stored($salesInvoiceId);
    if ($arrangement === NULL) {
      unset($stored['payment_arrangement']);
      $this->appendEvent($stored, 'payment_arrangement_cleared', [], $actorUid);
    }
    else {
      $stored['payment_arrangement'] = [
        'reference' => trim((string) ($arrangement['reference'] ?? '')),
        'agreed_at' => (string) ($arrangement['agreed_at'] ?? date('Y-m-d')),
        'next_due_date' => (string) ($arrangement['next_due_date'] ?? ''),
        'note' => trim((string) ($arrangement['note'] ?? '')),
      ];
      $this->appendEvent($stored, 'payment_arrangement_set', $stored['payment_arrangement'], $actorUid);
    }
    $this->save($salesInvoiceId, $stored);
  }

  public function nextStep(int $salesInvoiceId, ?\DateTimeImmutable $today = NULL): ?string {
    $today ??= new \DateTimeImmutable('today');
    $state = $this->state($salesInvoiceId, $today);
    if ($state['blocked_reason'] !== NULL || in_array($state['status'], ['betaald', 'gecrediteerd', 'gesloten'], TRUE)) {
      return NULL;
    }

    $due = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $state['due_date']);
    if ($due === FALSE || $today <= $due) {
      return NULL;
    }
    $daysOverdue = (int) $due->diff($today)->format('%a');
    $schedule = $this->schedule();
    $events = $state['events'];

    foreach (['reminder', 'demand', 'final_notice', 'collection_ready'] as $step) {
      if ($daysOverdue < $schedule[$step]) {
        return NULL;
      }
      if (!$this->hasStep($events, $step)) {
        return $step;
      }
    }
    return NULL;
  }

  public function recordStep(int $salesInvoiceId, string $step, array $snapshot, int $actorUid): void {
    if (!isset(self::STEPS[$step])) {
      throw new \InvalidArgumentException('Onbekende debiteurenstap: ' . $step);
    }
    $state = $this->state($salesInvoiceId);
    if ($state['blocked_reason'] !== NULL || in_array($state['status'], ['betaald', 'gecrediteerd', 'gesloten'], TRUE)) {
      throw new RuntimeException('Deze factuur mag niet worden geëscaleerd zolang de blokkade of eindstatus actief is.');
    }
    if ($this->hasStep($state['events'], $step)) {
      return;
    }

    $stored = $this->stored($salesInvoiceId);
    $stored['status'] = self::STEPS[$step];
    $this->appendEvent($stored, $step, [
      'invoice_number' => $state['invoice_number'],
      'due_date' => $state['due_date'],
      'outstanding_amount_inc_vat' => $state['outstanding_amount_inc_vat'],
      'snapshot' => $snapshot,
    ], $actorUid);
    $this->save($salesInvoiceId, $stored);
  }

  /** @return array{reminder:int,demand:int,final_notice:int,collection_ready:int} */
  public function schedule(): array {
    $config = $this->configFactory->get('brebo_finance.receivables');
    $read = static function (mixed $value, int $fallback): int {
      return is_numeric($value) ? max(0, (int) $value) : $fallback;
    };
    $schedule = [
      'reminder' => $read($config->get('dunning.reminder_after_days'), 3),
      'demand' => $read($config->get('dunning.demand_after_days'), 8),
      'final_notice' => $read($config->get('dunning.final_notice_after_days'), 15),
      'collection_ready' => $read($config->get('dunning.collection_ready_after_days'), 22),
    ];
    if (!($schedule['reminder'] <= $schedule['demand'] && $schedule['demand'] <= $schedule['final_notice'] && $schedule['final_notice'] <= $schedule['collection_ready'])) {
      throw new RuntimeException('Debiteurenschema moet oplopend zijn geconfigureerd.');
    }
    return $schedule;
  }

  /** @return array<string,mixed> */
  private function invoice(int $salesInvoiceId): array {
    if (!$this->database->schema()->tableExists('brebo_finance_sales_invoice')) {
      throw new RuntimeException('Verkoopfactuurspiegel ontbreekt. Voer database-updates uit.');
    }
    $row = $this->database->select('brebo_finance_sales_invoice', 'i')
      ->fields('i')
      ->condition('id', $salesInvoiceId)
      ->execute()
      ->fetchAssoc();
    if ($row === FALSE) {
      throw new \InvalidArgumentException('Verkoopfactuur niet gevonden.');
    }
    return $row;
  }

  /** @return array<string,mixed> */
  private function stored(int $salesInvoiceId): array {
    $value = $this->keyValueFactory->get(self::STORE)->get((string) $salesInvoiceId, []);
    return is_array($value) ? $value : [];
  }

  /** @param array<string,mixed> $stored */
  private function save(int $salesInvoiceId, array $stored): void {
    $stored['changed'] = time();
    $this->keyValueFactory->get(self::STORE)->set((string) $salesInvoiceId, $stored);
  }

  /** @param array<string,mixed> $stored */
  private function appendEvent(array &$stored, string $type, array $payload, int $actorUid): void {
    $events = is_array($stored['events'] ?? NULL) ? array_values($stored['events']) : [];
    $events[] = [
      'type' => $type,
      'at' => time(),
      'actor_uid' => $actorUid,
      'payload' => $payload,
      'hash' => hash('sha256', json_encode([$type, $payload, count($events)], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
    ];
    $stored['events'] = $events;
  }

  /** @param array<int,array<string,mixed>> $events */
  private function hasStep(array $events, string $step): bool {
    foreach ($events as $event) {
      if (($event['type'] ?? NULL) === $step) {
        return TRUE;
      }
    }
    return FALSE;
  }
}
