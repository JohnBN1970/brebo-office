<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\CollectionInvoiceRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseCollectionInvoiceRepository implements CollectionInvoiceRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function get(int $invoiceId): ?array {
    $row=$this->database->select('brebo_finance_sales_invoice','i')
      ->fields('i',['id','invoice_number','project_nid','invoice_date','due_date','status','amount_inc_vat','paid_amount_inc_vat'])
      ->condition('id',$invoiceId)
      ->execute()
      ->fetchAssoc();
    return $row===FALSE?NULL:$row;
  }

}
