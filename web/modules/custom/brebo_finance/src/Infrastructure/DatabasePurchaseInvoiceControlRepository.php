<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\PurchaseInvoiceControlRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabasePurchaseInvoiceControlRepository implements PurchaseInvoiceControlRepositoryInterface {
  public function __construct(private readonly Connection $database) {}

  public function available(): bool { return $this->database->schema()->tableExists('brebo_finance_purchase_invoice'); }

  public function invoice(int $invoiceId): ?array {
    $row=$this->database->select('brebo_finance_purchase_invoice','i')->fields('i')->condition('id',$invoiceId)->execute()->fetchAssoc();
    return $row===FALSE?NULL:$row;
  }

  public function lines(int $invoiceId): array {
    $schema=$this->database->schema();
    if(!$schema->tableExists('brebo_finance_purchase_invoice_line')) return [];
    $query=$this->database->select('brebo_finance_purchase_invoice_line','il');$query->fields('il');
    if($schema->tableExists('brebo_finance_commitment_line')){
      $query->leftJoin('brebo_finance_commitment_line','cl','cl.id = il.commitment_line_id');
      foreach(['line_number','description','amount_ex_vat','unit_price_ex_vat','vat_code','vat_rate'] as $field) if($schema->fieldExists('brebo_finance_commitment_line',$field)) $query->addField('cl',$field,'commitment_'.$field);
      if($schema->tableExists('brebo_finance_commitment')){
        $query->leftJoin('brebo_finance_commitment','c','c.id = cl.commitment_id');
        foreach(['id','commitment_number','supplier_name','status','amount_ex_vat'] as $field) if($schema->fieldExists('brebo_finance_commitment',$field)) $query->addField('c',$field,'commitment_header_'.$field);
      }
    }
    return array_values($query->condition('il.invoice_id',$invoiceId)->orderBy('il.line_number')->execute()->fetchAll(\PDO::FETCH_ASSOC));
  }

  public function latestPaymentRelease(int $invoiceId): ?array {
    if(!$this->database->schema()->tableExists('brebo_finance_payment_release')) return NULL;
    $row=$this->database->select('brebo_finance_payment_release','p')->fields('p')->condition('invoice_id',$invoiceId)->orderBy('id','DESC')->range(0,1)->execute()->fetchAssoc();
    return $row===FALSE?NULL:$row;
  }

  public function latestGAccountInstruction(int $invoiceId): ?array {
    if(!$this->database->schema()->tableExists('brebo_finance_g_account_instruction')) return NULL;
    $row=$this->database->select('brebo_finance_g_account_instruction','g')->fields('g')->condition('source_type','purchase_invoice')->condition('source_id',$invoiceId)->condition('direction','outgoing')->orderBy('id','DESC')->range(0,1)->execute()->fetchAssoc();
    return $row===FALSE?NULL:$row;
  }
}
