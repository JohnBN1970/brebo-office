<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\InvoicePerformanceSourceRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseInvoicePerformanceSourceRepository implements InvoicePerformanceSourceRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function invoiceLineTableExists(): bool {
    return $this->database->schema()->tableExists('brebo_finance_purchase_invoice_line');
  }

  public function invoiceLine(int $invoiceLineId): ?array {
    $schema=$this->database->schema();
    $query=$this->database->select('brebo_finance_purchase_invoice_line','il');
    $query->fields('il');
    if($schema->tableExists('brebo_finance_purchase_invoice')){
      $query->leftJoin('brebo_finance_purchase_invoice','i','i.id = il.invoice_id');
      foreach(['invoice_number','supplier_name','due_date','invoice_date'] as $field){
        if($schema->fieldExists('brebo_finance_purchase_invoice',$field)) $query->addField('i',$field);
      }
    }
    $row=$query->condition('il.id',$invoiceLineId)->execute()->fetchAssoc();
    return $row===FALSE?NULL:$row;
  }

  public function performanceTableExists(): bool {
    return $this->database->schema()->tableExists('brebo_finance_performance_receipt');
  }

  public function performances(int $commitmentLineId): array {
    $schema=$this->database->schema();
    $query=$this->database->select('brebo_finance_performance_receipt','r')->fields('r',['id','status','description','amount_ex_vat','building_evidence_complete','quality_accepted','created_by','created','changed'])->condition('commitment_line_id',$commitmentLineId);
    if($schema->tableExists('brebo_finance_performance_location')){
      $query->leftJoin('brebo_finance_performance_location','l','l.receipt_id = r.id');
      $query->addField('l','building_nid');
      $query->addField('l','object_id');
    }
    if($schema->tableExists('brebo_building_object')&&$schema->tableExists('brebo_finance_performance_location')){
      $query->leftJoin('brebo_building_object','o','o.id = l.object_id');
      $query->addField('o','object_code');
      $query->addField('o','label','object_label');
      $query->addField('o','object_type');
    }
    return array_map(static fn(object $row):array=>(array)$row,$query->orderBy('r.changed','DESC')->execute()->fetchAll());
  }

}
