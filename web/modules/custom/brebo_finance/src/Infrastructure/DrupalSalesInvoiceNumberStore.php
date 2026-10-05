<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\SalesInvoiceNumberStoreInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;

/** Drupal key-value adapter for invoice-number reservations. */
final class DrupalSalesInvoiceNumberStore implements SalesInvoiceNumberStoreInterface {

  private const COLLECTION = 'brebo_finance.sales_invoice_numbers';

  public function __construct(private readonly KeyValueFactoryInterface $keyValueFactory) {}

  public function has(string $key): bool {
    return $this->keyValueFactory->get(self::COLLECTION)->has($key);
  }

  public function get(string $key, mixed $default = NULL): mixed {
    return $this->keyValueFactory->get(self::COLLECTION)->get($key, $default);
  }

  public function set(string $key, mixed $value): void {
    $this->keyValueFactory->get(self::COLLECTION)->set($key, $value);
  }

}
