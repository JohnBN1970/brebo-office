<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;

/** Read-only project ledger for financial drill-down and audit navigation. */
final class FinancialProjectLedger {
  public function __construct(private readonly Connection $database, private readonly InvoicePerformanceBlockerResolver $invoiceBlockers) {}

  /** @return array<string, mixed> */
  public function build(int $projectNid): array {
    $invoiceLines=$this->purchaseInvoiceLines($projectNid);
    return [
      'project_nid' => $projectNid,
      'commitments' => $this->rows('brebo_finance_commitment', $projectNid, ['id','commitment_number','supplier_name','status','amount_ex_vat','vat_amount','amount_inc_vat','created','changed']),
      'commitment_lines' => $this->commitmentLines($projectNid),
      'performance_receipts' => $this->performanceReceipts($projectNid),
      'purchase_invoices' => $this->purchaseInvoices($projectNid),
      'purchase_invoice_lines' => $invoiceLines,
      'invoice_performance_blockers' => $this->invoicePerformanceBlockers($invoiceLines),
      'change_orders' => $this->rows('brebo_finance_change_order', $projectNid, ['id','change_number','change_type','title','cause','consequence','status','sales_amount_ex_vat','cost_amount_ex_vat','created','changed']),
      'failure_costs' => $this->rows('brebo_finance_failure_cost', $projectNid, ['id','failure_number','category','title','cause','consequence','preventive_measure','status','gross_failure_cost_ex_vat','recoverable_amount_ex_vat','net_failure_cost_ex_vat','due_date','created','changed']),
      'payment_releases' => $this->rows('brebo_finance_payment_release', $projectNid, ['id','invoice_id','status','payment_amount','g_account_amount','blocked_amount','reason','created','changed']),
      'billing' => $this->rows('brebo_finance_billing', $projectNid, ['id','invoice_number','status','amount_ex_vat','vat_amount','amount_inc_vat','due_date','created','changed']),
      'audit' => $this->audit($projectNid),
      'basis' => 'Read-only ledger from registered BREBO Finance records. Missing tables return an empty section; values are not inferred.',
    ];
  }

  private function invoicePerformanceBlockers(array $lines): array {
    $out=[];foreach($lines as $line){$id=(int)($line['id']??0);if($id<=0)continue;$analysis=$this->invoiceBlockers->resolve($id);if(($analysis['blocked']??false)||($analysis['verified_shortfall_ex_vat']??0)>0)$out[]=$analysis;}return$out;
  }

  private function performanceReceipts(int $projectNid): array {
    $schema=$this->database->schema(); if(!$schema->tableExists('brebo_finance_performance_receipt')) return [];
    $wanted=['id','commitment_line_id','status','description','amount_ex_vat','building_evidence_complete','quality_accepted','evidence','verification_note','verified','verified_by','created','created_by','changed'];
    $fields=array_values(array_filter($wanted,static fn(string $f):bool=>$schema->fieldExists('brebo_finance_performance_receipt',$f)));$q=$this->database->select('brebo_finance_performance_receipt','r')->fields('r',$fields)->condition('r.project_nid',$projectNid);
    if($schema->tableExists('brebo_finance_performance_location')){$q->leftJoin('brebo_finance_performance_location','l','l.receipt_id = r.id');$q->addField('l','building_nid');$q->addField('l','object_id');}
    if($schema->tableExists('brebo_building_object')&&$schema->tableExists('brebo_finance_performance_location')){$q->leftJoin('brebo_building_object','o','o.id = l.object_id');$q->addField('o','object_code');$q->addField('o','label','object_label');$q->addField('o','object_type');}
    $q->orderBy('r.changed','DESC');return array_map(static fn(object $row):array=>(array)$row,$q->execute()->fetchAll());
  }

  private function purchaseInvoices(int $projectNid): array {return $this->rows('brebo_finance_purchase_invoice',$projectNid,['id','supplier_name','invoice_number','invoice_date','due_date','status','match_status','amount_ex_vat','vat_amount','amount_inc_vat','created','changed']);}

  private function purchaseInvoiceLines(int $projectNid): array {
    $schema=$this->database->schema(); if(!$schema->tableExists('brebo_finance_purchase_invoice_line')||!$schema->tableExists('brebo_finance_purchase_invoice')) return [];
    $q=$this->database->select('brebo_finance_purchase_invoice_line','il');$q->join('brebo_finance_purchase_invoice','i','i.id = il.invoice_id');$q->addField('il','id');$q->addField('il','invoice_id');
    foreach(['commitment_line_id','description','quantity','unit_price_ex_vat','amount_ex_vat','vat_code','vat_rate','match_status','variance_code','variance_amount_ex_vat','changed'] as $field) if($schema->fieldExists('brebo_finance_purchase_invoice_line',$field))$q->addField('il',$field);
    foreach(['invoice_number','supplier_name'] as $field) if($schema->fieldExists('brebo_finance_purchase_invoice',$field))$q->addField('i',$field);
    if($schema->tableExists('brebo_finance_commitment_line')){$q->leftJoin('brebo_finance_commitment_line','cl','cl.id = il.commitment_line_id');foreach(['description','amount_ex_vat'] as $field)if($schema->fieldExists('brebo_finance_commitment_line',$field))$q->addField('cl',$field,'commitment_'.$field);}
    $q->condition('i.project_nid',$projectNid);if($schema->fieldExists('brebo_finance_purchase_invoice_line','changed'))$q->orderBy('il.changed','DESC');else$q->orderBy('il.id','DESC');return array_map(static fn(object $r):array=>(array)$r,$q->execute()->fetchAll());
  }

  private function commitmentLines(int $projectNid): array {
    $schema=$this->database->schema(); if(!$schema->tableExists('brebo_finance_commitment_line')||!$schema->tableExists('brebo_finance_commitment')) return [];
    $q=$this->database->select('brebo_finance_commitment_line','cl'); $q->join('brebo_finance_commitment','c','c.id = cl.commitment_id');$q->addField('cl','id');$q->addField('cl','commitment_id');$q->addField('c','commitment_number');$q->addField('c','supplier_name');foreach(['description','amount_ex_vat','unit_price_ex_vat','quantity'] as $field)if($schema->fieldExists('brebo_finance_commitment_line',$field))$q->addField('cl',$field);$q->condition('c.project_nid',$projectNid)->orderBy('cl.id','ASC');return array_map(static fn(object $r):array=>(array)$r,$q->execute()->fetchAll());
  }

  private function rows(string $table,int $projectNid,array $wanted):array{$schema=$this->database->schema();if(!$schema->tableExists($table))return[];$fields=array_values(array_filter($wanted,static fn(string $field):bool=>$schema->fieldExists($table,$field)));if($fields===[]||!$schema->fieldExists($table,'project_nid'))return[];$query=$this->database->select($table,'x')->fields('x',$fields)->condition('project_nid',$projectNid);if($schema->fieldExists($table,'changed'))$query->orderBy('changed','DESC');elseif($schema->fieldExists($table,'created'))$query->orderBy('created','DESC');return array_map(static fn(object $row):array=>(array)$row,$query->execute()->fetchAll());}
  private function audit(int $projectNid):array{if(!$this->database->schema()->tableExists('brebo_finance_audit'))return[];$query=$this->database->select('brebo_finance_audit','a')->fields('a')->condition('project_nid',$projectNid)->orderBy('created','DESC')->range(0,100);return array_map(static fn(object $row):array=>(array)$row,$query->execute()->fetchAll());}
}
