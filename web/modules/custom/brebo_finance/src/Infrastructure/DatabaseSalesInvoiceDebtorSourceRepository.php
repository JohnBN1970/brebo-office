<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\SalesInvoiceDebtorSourceRepositoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;

/** Drupal database/key-value adapter for finalized sales-invoice debtor linkage. */
final class DatabaseSalesInvoiceDebtorSourceRepository implements SalesInvoiceDebtorSourceRepositoryInterface {

  public function __construct(
    private readonly Connection $database,
    private readonly KeyValueFactoryInterface $keyValueFactory,
  ) {}

  public function source(int $salesInvoiceId): ?array {
    $invoice = $this->database->select('brebo_finance_sales_invoice', 'i')
      ->fields('i', ['invoice_number'])
      ->condition('id', $salesInvoiceId)
      ->execute()
      ->fetchAssoc();
    $invoiceNumber = $invoice === FALSE ? '' : trim((string) $invoice['invoice_number']);
    if ($invoiceNumber === '') {
      return NULL;
    }

    $outbox = $this->database->select('brebo_finance_sales_invoice_outbox', 'o')
      ->fields('o', ['draft_id', 'payload'])
      ->condition('command_type', 'sales_invoice.register')
      ->orderBy('created', 'DESC')
      ->execute();

    $draftId = 0;
    foreach ($outbox as $row) {
      $payload = json_decode((string) $row->payload, TRUE);
      if (is_array($payload) && (string) ($payload['source']['invoice_number'] ?? '') === $invoiceNumber) {
        $draftId = (int) $row->draft_id;
        break;
      }
    }
    if ($draftId <= 0) {
      return ['invoice_number' => $invoiceNumber, 'draft_id' => 0, 'organization_id' => 0];
    }

    $context = $this->keyValueFactory->get('brebo_finance.sales_invoice_draft_context')->get((string) $draftId, []);
    return [
      'invoice_number' => $invoiceNumber,
      'draft_id' => $draftId,
      'organization_id' => is_array($context) ? (int) ($context['customer_organization_nid'] ?? 0) : 0,
    ];
  }

}
