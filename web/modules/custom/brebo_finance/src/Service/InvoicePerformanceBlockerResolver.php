<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;

/** Resolves invoice-line match blockers to the underlying building performances. */
final class InvoicePerformanceBlockerResolver {
  public function __construct(private readonly Connection $database) {}

  /** @return array<string,mixed> */
  public function resolve(int $invoiceLineId): array {
    $schema=$this->database->schema();
    if(!$schema->tableExists('brebo_finance_purchase_invoice_line')) return ['invoice_line_id'=>$invoiceLineId,'blocked'=>false,'reason'=>'invoice_line_table_missing','performances'=>[]];
    $line=$this->database->select('brebo_finance_purchase_invoice_line','il')->fields('il')->condition('id',$invoiceLineId)->execute()->fetchAssoc();
    if($line===false) return ['invoice_line_id'=>$invoiceLineId,'blocked'=>false,'reason'=>'invoice_line_missing','performances'=>[]];
    $commitmentLineId=(int)($line['commitment_line_id']??0);$variance=(string)($line['variance_code']??'');$matchStatus=(string)($line['match_status']??'unmatched');
    if($commitmentLineId<=0) return ['invoice_line_id'=>$invoiceLineId,'blocked'=>$matchStatus!=='matched','reason'=>'missing_order','variance_codes'=>$variance!==''?explode(',',$variance):[],'performances'=>[]];
    if(!$schema->tableExists('brebo_finance_performance_receipt')) return ['invoice_line_id'=>$invoiceLineId,'blocked'=>$matchStatus!=='matched','reason'=>'performance_table_missing','variance_codes'=>$variance!==''?explode(',',$variance):[],'performances'=>[]];

    $q=$this->database->select('brebo_finance_performance_receipt','r')->fields('r',['id','status','description','amount_ex_vat','building_evidence_complete','quality_accepted','created_by','changed'])->condition('commitment_line_id',$commitmentLineId);
    if($schema->tableExists('brebo_finance_performance_location')){$q->leftJoin('brebo_finance_performance_location','l','l.receipt_id = r.id');$q->addField('l','building_nid');$q->addField('l','object_id');}
    if($schema->tableExists('brebo_building_object')&&$schema->tableExists('brebo_finance_performance_location')){$q->leftJoin('brebo_building_object','o','o.id = l.object_id');$q->addField('o','object_code');$q->addField('o','label','object_label');$q->addField('o','object_type');}
    $rows=array_map(static fn(object $r):array=>(array)$r,$q->orderBy('r.changed','DESC')->execute()->fetchAll());
    $verified=[];$blocked=[];$verifiedTotal=0.0;$blockedTotal=0.0;foreach($rows as $row){$ok=($row['status']??'')==='verified'&&(int)($row['building_evidence_complete']??0)===1&&(int)($row['quality_accepted']??0)===1;$amount=(float)($row['amount_ex_vat']??0);$item=$row+['verified_for_match'=>$ok];if($ok){$verified[]=$item;$verifiedTotal+=$amount;}else{$blocked[]=$item;$blockedTotal+=$amount;}}
    $invoiceAmount=(float)($line['amount_ex_vat']??0);$shortfall=max(0.0,$invoiceAmount-$verifiedTotal);
    return ['invoice_line_id'=>$invoiceLineId,'commitment_line_id'=>$commitmentLineId,'match_status'=>$matchStatus,'variance_codes'=>$variance!==''?explode(',',$variance):[],'invoice_amount_ex_vat'=>$invoiceAmount,'verified_performance_ex_vat'=>$verifiedTotal,'unverified_performance_ex_vat'=>$blockedTotal,'verified_shortfall_ex_vat'=>$shortfall,'blocked'=>$matchStatus!=='matched','verified_performances'=>$verified,'blocking_performances'=>$blocked,'performances'=>$rows];
  }
}
