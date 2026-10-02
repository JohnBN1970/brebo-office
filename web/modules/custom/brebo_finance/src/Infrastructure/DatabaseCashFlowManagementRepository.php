<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\CashFlowManagementRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseCashFlowManagementRepository implements CashFlowManagementRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function salesInvoices(): array {
    if (!$this->database->schema()->tableExists('brebo_finance_sales_invoice')) {
      return [];
    }
    return array_values($this->database->select('brebo_finance_sales_invoice', 'i')
      ->fields('i', ['id','invoice_number','project_nid','due_date','status','amount_inc_vat','paid_amount_inc_vat'])
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC));
  }

  public function overdueSalesInvoices(string $asOfDate): array {
    if (!$this->database->schema()->tableExists('brebo_finance_sales_invoice')) {
      return [];
    }
    return array_values($this->database->select('brebo_finance_sales_invoice', 'i')
      ->fields('i', ['id','invoice_number','project_nid','due_date','status','amount_inc_vat','paid_amount_inc_vat'])
      ->condition('due_date', $asOfDate, '<')
      ->orderBy('due_date', 'ASC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC));
  }

  public function cashEventsUntil(string $endDate): array {
    if (!$this->database->schema()->tableExists('brebo_finance_cash_event')) {
      return [];
    }
    return array_values($this->database->select('brebo_finance_cash_event', 'e')
      ->fields('e', ['direction','amount_inc_vat','due_date','status','account_bucket','description','project_nid'])
      ->condition('status', ['confirmed','expected'], 'IN')
      ->condition('due_date', $endDate, '<=')
      ->orderBy('due_date')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC));
  }

}
