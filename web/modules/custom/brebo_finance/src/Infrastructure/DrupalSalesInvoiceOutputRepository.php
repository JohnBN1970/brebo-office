<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\SalesInvoiceOutputRepositoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;

/** Drupal persistence adapter for sales-invoice output sources. */
final class DrupalSalesInvoiceOutputRepository implements SalesInvoiceOutputRepositoryInterface {

  public function __construct(
    private readonly Connection $database,
    private readonly KeyValueFactoryInterface $keyValueFactory,
  ) {}

  public function draft(int $draftId, bool $mustBeDraft): ?array {
    $query = $this->database->select('brebo_finance_sales_invoice_draft', 'd')
      ->fields('d')
      ->condition('id', $draftId);
    if ($mustBeDraft) {
      $query->condition('status', 'draft');
    }
    $row = $query->execute()->fetchAssoc();
    return $row === FALSE ? NULL : $row;
  }

  public function draftContext(int $draftId): array {
    $value = $this->keyValueFactory
      ->get('brebo_finance.sales_invoice_draft_context')
      ->get((string) $draftId, []);
    return is_array($value) ? $value : [];
  }

}
