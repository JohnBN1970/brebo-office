<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\OriginalInvoiceSourceRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseOriginalInvoiceSourceRepository implements OriginalInvoiceSourceRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function available(): bool {
    return $this->database->schema()->tableExists('brebo_finance_audit');
  }

  public function payloads(int $invoiceId): array {
    return array_values(array_map('strval', $this->database->select('brebo_finance_audit','a')
      ->fields('a',['payload'])
      ->condition('entity_type','purchase_invoice')
      ->condition('entity_id',$invoiceId)
      ->condition('action','source_neutral_invoice_received')
      ->orderBy('created','DESC')
      ->execute()
      ->fetchCol()));
  }

}
