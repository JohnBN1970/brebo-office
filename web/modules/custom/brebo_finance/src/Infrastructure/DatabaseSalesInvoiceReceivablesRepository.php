<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\SalesInvoiceReceivablesRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseSalesInvoiceReceivablesRepository implements SalesInvoiceReceivablesRepositoryInterface {
  public function __construct(private readonly Connection $database) {}
  public function byMoneybirdId(string $moneybirdId):?array{$r=$this->database->select('brebo_finance_sales_invoice','i')->fields('i')->condition('moneybird_id',$moneybirdId)->execute()->fetchAssoc();return $r===FALSE?NULL:$r;}
}
