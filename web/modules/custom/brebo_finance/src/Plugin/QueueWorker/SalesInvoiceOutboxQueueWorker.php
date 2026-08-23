<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Plugin\QueueWorker;

use Drupal\brebo_finance\Service\BillingControlManager;
use Drupal\brebo_finance\Service\SalesInvoiceIntegrationClient;
use Drupal\Core\Database\Connection;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Dispatches immutable sales-invoice outbox commands to the integration API. */
#[QueueWorker(
  id: 'brebo_finance_sales_invoice_outbox',
  title: new TranslatableMarkup('BREBO Finance sales invoice outbox'),
  cron: ['time' => 60],
)]
final class SalesInvoiceOutboxQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    private readonly Connection $database,
    private readonly SalesInvoiceIntegrationClient $client,
    private readonly BillingControlManager $billingControlManager,
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
  }

  public static function create(ContainerInterface $container, array $configuration, $pluginId, $pluginDefinition): self {
    return new self(
      $configuration,
      $pluginId,
      $pluginDefinition,
      $container->get('database'),
      $container->get('brebo_finance.sales_invoice_integration_client'),
      $container->get('brebo_finance.billing_control_manager'),
    );
  }

  public function processItem($data): void {
    $outboxId = (int) ($data['outbox_id'] ?? 0);
    if ($outboxId <= 0) throw new \InvalidArgumentException('Sales invoice queue item has no valid outbox id.');

    $row = $this->database->select('brebo_finance_sales_invoice_outbox', 'o')->fields('o')->condition('id', $outboxId)->execute()->fetchAssoc();
    if ($row === FALSE || ($row['status'] ?? '') === 'completed') return;
    if (!in_array((string) ($row['status'] ?? ''), ['queued', 'error'], TRUE)) return;

    $payload = json_decode((string) $row['payload'], TRUE, 512, JSON_THROW_ON_ERROR);
    $invoice = $payload['invoice'] ?? NULL;
    if (!is_array($invoice)) throw new \RuntimeException('Sales invoice outbox payload has no invoice object.');

    $now = time();
    $this->database->update('brebo_finance_sales_invoice_outbox')->fields([
      'status' => 'processing',
      'attempt_count' => ((int) $row['attempt_count']) + 1,
      'last_attempt' => $now,
      'last_error' => NULL,
      'changed' => $now,
    ])->condition('id', $outboxId)->execute();

    try {
      $result = $this->client->dispatch((string) $row['idempotency_key'], $invoice);
      $providerInvoice = $result['sales_invoice'] ?? NULL;
      if (!is_array($providerInvoice) || empty($providerInvoice['id']) || empty($providerInvoice['invoice_id'])) {
        throw new \RuntimeException('Integration API response has no complete Moneybird sales invoice identity.');
      }

      $moneybirdId = (string) $providerInvoice['id'];
      $invoiceNumber = (string) $providerInvoice['invoice_id'];
      $requestId = (string) ($result['request_id'] ?? '');
      $completed = time();
      $mirrorLines = [];
      foreach ((array) ($invoice['lines'] ?? []) as $line) {
        if (!is_array($line)) continue;
        $mirrorLines[] = [
          'description' => (string) ($line['description'] ?? ''),
          'amount_ex_vat' => (string) ($line['amount_ex_vat'] ?? '0'),
          'vat_code' => (string) ($line['vat_code'] ?? 'NL_0'),
          'vat_rate' => (string) ($line['vat_rate'] ?? '0'),
          'source_ref' => sprintf('%s:%s', (string) ($line['source_type'] ?? 'draft'), (string) ($line['source_id'] ?? '0')),
        ];
      }
      $sourceHash = hash('sha256', json_encode([
        'moneybird' => $providerInvoice,
        'payload_hash' => (string) $row['payload_hash'],
      ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
      $providerState = strtolower((string) ($providerInvoice['state'] ?? 'open'));
      $mirrorStatus = match ($providerState) {
        'paid' => 'paid',
        'late', 'overdue' => 'overdue',
        'cancelled', 'canceled' => 'cancelled',
        default => 'sent',
      };

      $transaction = $this->database->startTransaction();
      try {
        $salesInvoiceId = $this->billingControlManager->synchronizeMoneybirdInvoice([
          'project_nid' => (int) $row['project_nid'],
          'moneybird_id' => $moneybirdId,
          'invoice_number' => $invoiceNumber,
          'invoice_date' => (string) ($providerInvoice['invoice_date'] ?? $invoice['invoice_date'] ?? ''),
          'due_date' => (string) ($providerInvoice['due_date'] ?? $invoice['due_date'] ?? ''),
          'status' => $mirrorStatus,
          'amount_ex_vat' => (string) ($invoice['amount_ex_vat'] ?? '0'),
          'vat_amount' => (string) ($invoice['vat_amount'] ?? '0'),
          'amount_inc_vat' => (string) ($invoice['amount_inc_vat'] ?? '0'),
          'paid_amount_inc_vat' => $mirrorStatus === 'paid' ? (string) ($invoice['amount_inc_vat'] ?? '0') : '0',
          'regular_account_amount' => (string) ($invoice['amount_inc_vat'] ?? '0'),
          'g_account_amount' => '0',
          'source_hash' => $sourceHash,
          'recorded_at' => $completed,
          'lines' => $mirrorLines,
        ], 0);

        $this->database->update('brebo_finance_sales_invoice_outbox')->fields([
          'status' => 'completed',
          'integration_request_id' => $requestId ?: NULL,
          'moneybird_id' => $moneybirdId,
          'completed' => $completed,
          'changed' => $completed,
        ])->condition('id', $outboxId)->execute();
        $this->database->update('brebo_finance_sales_invoice_draft')->fields([
          'status' => 'sent',
          'moneybird_id' => $moneybirdId,
          'sales_invoice_id' => $salesInvoiceId,
          'changed' => $completed,
        ])->condition('id', (int) $row['draft_id'])->execute();
      }
      catch (\Throwable $exception) {
        $transaction->rollBack();
        throw $exception;
      }
    }
    catch (\Throwable $exception) {
      $this->database->update('brebo_finance_sales_invoice_outbox')->fields([
        'status' => 'error',
        'last_error' => mb_substr($exception->getMessage(), 0, 4000),
        'changed' => time(),
      ])->condition('id', $outboxId)->execute();
      throw $exception;
    }
  }

}
