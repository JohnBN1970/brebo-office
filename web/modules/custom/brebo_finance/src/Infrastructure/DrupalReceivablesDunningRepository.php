<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\ReceivablesDunningRepositoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use RuntimeException;

/** Drupal storage adapter for receivables dunning. */
final class DrupalReceivablesDunningRepository implements ReceivablesDunningRepositoryInterface {

  private const STORE = 'brebo_finance.receivables_dunning';

  public function __construct(
    private readonly Connection $database,
    private readonly KeyValueFactoryInterface $keyValueFactory,
  ) {}

  public function salesInvoice(int $salesInvoiceId): ?array {
    if (!$this->database->schema()->tableExists('brebo_finance_sales_invoice')) {
      throw new RuntimeException('Verkoopfactuurspiegel ontbreekt. Voer database-updates uit.');
    }
    $row = $this->database->select('brebo_finance_sales_invoice', 'i')
      ->fields('i')->condition('id', $salesInvoiceId)->execute()->fetchAssoc();
    return $row === FALSE ? NULL : $row;
  }

  public function state(int $salesInvoiceId): array {
    $value = $this->keyValueFactory->get(self::STORE)->get((string) $salesInvoiceId, []);
    return is_array($value) ? $value : [];
  }

  public function saveState(int $salesInvoiceId, array $state): void {
    $this->keyValueFactory->get(self::STORE)->set((string) $salesInvoiceId, $state);
  }

}
