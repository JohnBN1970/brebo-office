<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\SalesInvoiceNumberLockInterface;
use Drupal\Core\Lock\LockBackendInterface;

/** Drupal lock adapter for invoice-number concurrency. */
final class DrupalSalesInvoiceNumberLock implements SalesInvoiceNumberLockInterface {

  private const LOCK = 'brebo_finance.sales_invoice_numbers';

  public function __construct(private readonly LockBackendInterface $lock) {}

  public function acquire(float $timeout = 10.0): bool {
    return $this->lock->acquire(self::LOCK, $timeout);
  }

  public function release(): void {
    $this->lock->release(self::LOCK);
  }

}
