<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\FinancialProjectLedgerRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseFinancialProjectLedgerRepository implements FinancialProjectLedgerRepositoryInterface {
  public function __construct(private readonly Connection $database) {}

  public function projectLedger(int $projectId): array {
    return [
      'commitments'=>$this->rows('brebo_finance_commitment',$projectId,['id','commitment_number','supplier_name','status','amount_ex_vat','vat_amount','amount_inc_vat','created','changed']),
      'commitment_lines'=>$this->commitmentLines($projectId),
      'performance_receipts'=>$this->performanceReceipts($projectId),
      'purchase_invoices'=>$this->rows('brebo_finance_purchase_invoice',$projectId,['id','supplier_name','invoice_number','invoice_date','due_date','status','match_status','amount_ex_vat','vat_amount','amount_inc_vat','created','changed']),
      'purchase_invoice_lines'=>$this->purchaseInvoiceLines($projectId),
      'change_orders'=>$this->rows('brebo_finance_change_order',$projectId,['id','change_number','change_type','title','cause','consequence','status','sales_amount_ex_vat','cost_amount_ex_vat','created','changed']),
      'failure_costs'=>$this->rows('brebo_finance_failure_cost',$projectId,['id','failure_number','category','title','cause','consequence','preventive_measure','status','gross_failure_cost_ex_vat','recoverable_amount_ex_vat','net_failure_cost_ex_vat','due_date','created','changed']),
      'payment_releases'=>$this->rows('brebo_finance_payment_release',$projectId,['id','invoice_id','status','payment_amount','g_account_amount','blocked_amount','reason','created','changed']),
      'billing'=>$this->rows('brebo_finance_billing',$projectId,['id','invoice_number','status','amount_ex_vat','vat_amount','amount_inc_vat','due_date','created','changed']),
      'audit'=>$this->audit($projectId),
    ];
  }

  private function performanceReceipts(int $projectId):array{$s=$this->database->schema();if(!$s->tableExists('brebo_finance_performance_receipt'))return[];$wanted=['id','commitment_line_id','status','description','amount_ex_vat','building_evidence_complete','quality_accepted','evidence','verification_note','verified','verified_by','created','created_by','changed'];$fields=array_values(array_filter($wanted,static fn(string $f):bool=>$s->fieldExists('brebo_finance_performance_receipt',$f)));$q=$this->database->select('brebo_finance_performance_receipt','r')->fields('r',$fields)->condition('r.project_nid',$projectId);if($s->tableExists('brebo_finance_performance_location')){$q->leftJoin('brebo_finance_performance_location','l','l.receipt_id = r.id');$q->addField('l','building_nid');$q->addField('l','object_id');}if($s->tableExists('brebo_building_object')&&$s->tableExists('brebo_finance_performance_location')){$q->leftJoin('brebo_building_object','o','o.id = l.object_id');$q->addField('o','object_code');$q->addField('o','label','object_label');$q->addField('o','object_type');}$q->orderBy('r.changed','DESC');return array_map(static fn(object $r):array=>(array)$r,$q->execute()->fetchAll());}
  private function purchaseInvoiceLines(int $projectId):array{$s=$this->database->schema();if(!$s->tableExists('brebo_finance_purchase_invoice_line')||!$s->tableExists('brebo_finance_purchase_invoice'))return[];$q=$this->database->select('brebo_finance_purchase_invoice_line','il');$q->join('brebo_finance_purchase_invoice','i','i.id = il.invoice_id');$q->addField('il','id');$q->addField('il','invoice_id');foreach(['commitment_line_id','description','quantity','unit_price_ex_vat','amount_ex_vat','vat_code','vat_rate','match_status','variance_code','variance_amount_ex_vat','changed'] as $f)if($s->fieldExists('brebo_finance_purchase_invoice_line',$f))$q->addField('il',$f);foreach(['invoice_number','supplier_name'] as $f)if($s->fieldExists('brebo_finance_purchase_invoice',$f))$q->addField('i',$f);if($s->tableExists('brebo_finance_commitment_line')){$q->leftJoin('brebo_finance_commitment_line','cl','cl.id = il.commitment_line_id');foreach(['description','amount_ex_vat'] as $f)if($s->fieldExists('brebo_finance_commitment_line',$f))$q->addField('cl',$f,'commitment_'.$f);}$q->condition('i.project_nid',$projectId);if($s->fieldExists('brebo_finance_purchase_invoice_line','changed'))$q->orderBy('il.changed','DESC');else$q->orderBy('il.id','DESC');return array_map(static fn(object $r):array=>(array)$r,$q->execute()->fetchAll());}
  private function commitmentLines(int $projectId):array{$s=$this->database->schema();if(!$s->tableExists('brebo_finance_commitment_line')||!$s->tableExists('brebo_finance_commitment'))return[];$q=$this->database->select('brebo_finance_commitment_line','cl');$q->join('brebo_finance_commitment','c','c.id = cl.commitment_id');$q->addField('cl','id');$q->addField('cl','commitment_id');$q->addField('c','commitment_number');$q->addField('c','supplier_name');foreach(['description','amount_ex_vat','unit_price_ex_vat','quantity'] as $f)if($s->fieldExists('brebo_finance_commitment_line',$f))$q->addField('cl',$f);$q->condition('c.project_nid',$projectId)->orderBy('cl.id','ASC');return array_map(static fn(object $r):array=>(array)$r,$q->execute()->fetchAll());}
  private function rows(string $table,int $projectId,array $wanted):array{$s=$this->database->schema();if(!$s->tableExists($table))return[];$fields=array_values(array_filter($wanted,static fn(string $f):bool=>$s->fieldExists($table,$f)));if($fields===[]||!$s->fieldExists($table,'project_nid'))return[];$q=$this->database->select($table,'x')->fields('x',$fields)->condition('project_nid',$projectId);if($s->fieldExists($table,'changed'))$q->orderBy('changed','DESC');elseif($s->fieldExists($table,'created'))$q->orderBy('created','DESC');return array_map(static fn(object $r):array=>(array)$r,$q->execute()->fetchAll());}
  private function audit(int $projectId):array{if(!$this->database->schema()->tableExists('brebo_finance_audit'))return[];return array_map(static fn(object $r):array=>(array)$r,$this->database->select('brebo_finance_audit','a')->fields('a')->condition('project_nid',$projectId)->orderBy('created','DESC')->range(0,100)->execute()->fetchAll());}
}
