<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;

/** Resolves invoice-line match blockers to underlying building performances. */
final class InvoicePerformanceBlockerResolver {
  public function __construct(private readonly Connection $database) {}

  /** @return array<string,mixed> */
  public function resolve(int $invoiceLineId): array {
    $schema=$this->database->schema();
    if(!$schema->tableExists('brebo_finance_purchase_invoice_line')) return ['invoice_line_id'=>$invoiceLineId,'blocked'=>false,'reason'=>'invoice_line_table_missing','performances'=>[]];
    $qLine=$this->database->select('brebo_finance_purchase_invoice_line','il');$qLine->fields('il');
    if($schema->tableExists('brebo_finance_purchase_invoice')){$qLine->leftJoin('brebo_finance_purchase_invoice','i','i.id = il.invoice_id');foreach(['invoice_number','supplier_name','due_date','invoice_date'] as $field)if($schema->fieldExists('brebo_finance_purchase_invoice',$field))$qLine->addField('i',$field);}
    $line=$qLine->condition('il.id',$invoiceLineId)->execute()->fetchAssoc();
    if($line===false) return ['invoice_line_id'=>$invoiceLineId,'blocked'=>false,'reason'=>'invoice_line_missing','performances'=>[]];
    $commitmentLineId=(int)($line['commitment_line_id']??0);$variance=(string)($line['variance_code']??'');$matchStatus=(string)($line['match_status']??'unmatched');$varianceCodes=$variance!==''?explode(',',$variance):[];
    if($commitmentLineId<=0){$priority=$this->priority((float)($line['amount_ex_vat']??0),(int)($line['due_date']??0),$varianceCodes,[],time());return ['invoice_line_id'=>$invoiceLineId,'blocked'=>$matchStatus!=='matched','reason'=>'missing_order','variance_codes'=>$varianceCodes,'priority'=>$priority,'performances'=>[]]+$this->invoiceMeta($line);}
    if(!$schema->tableExists('brebo_finance_performance_receipt')) return ['invoice_line_id'=>$invoiceLineId,'blocked'=>$matchStatus!=='matched','reason'=>'performance_table_missing','variance_codes'=>$varianceCodes,'performances'=>[]]+$this->invoiceMeta($line);

    $q=$this->database->select('brebo_finance_performance_receipt','r')->fields('r',['id','status','description','amount_ex_vat','building_evidence_complete','quality_accepted','created_by','created','changed'])->condition('commitment_line_id',$commitmentLineId);
    if($schema->tableExists('brebo_finance_performance_location')){$q->leftJoin('brebo_finance_performance_location','l','l.receipt_id = r.id');$q->addField('l','building_nid');$q->addField('l','object_id');}
    if($schema->tableExists('brebo_building_object')&&$schema->tableExists('brebo_finance_performance_location')){$q->leftJoin('brebo_building_object','o','o.id = l.object_id');$q->addField('o','object_code');$q->addField('o','label','object_label');$q->addField('o','object_type');}
    $rows=array_map(static fn(object $r):array=>(array)$r,$q->orderBy('r.changed','DESC')->execute()->fetchAll());
    $verified=[];$blocked=[];$verifiedTotal=0.0;$blockedTotal=0.0;foreach($rows as $row){$ok=($row['status']??'')==='verified'&&(int)($row['building_evidence_complete']??0)===1&&(int)($row['quality_accepted']??0)===1;$amount=(float)($row['amount_ex_vat']??0);$item=$row+['verified_for_match'=>$ok];if($ok){$verified[]=$item;$verifiedTotal+=$amount;}else{$blocked[]=$item;$blockedTotal+=$amount;}}
    $invoiceAmount=(float)($line['amount_ex_vat']??0);$shortfall=max(0.0,$invoiceAmount-$verifiedTotal);$priority=$this->priority($shortfall,(int)($line['due_date']??0),$varianceCodes,$blocked,time());
    return ['invoice_line_id'=>$invoiceLineId,'commitment_line_id'=>$commitmentLineId,'match_status'=>$matchStatus,'variance_codes'=>$varianceCodes,'invoice_amount_ex_vat'=>$invoiceAmount,'verified_performance_ex_vat'=>$verifiedTotal,'unverified_performance_ex_vat'=>$blockedTotal,'verified_shortfall_ex_vat'=>$shortfall,'blocked'=>$matchStatus!=='matched','priority'=>$priority,'verified_performances'=>$verified,'blocking_performances'=>$blocked,'performances'=>$rows]+$this->invoiceMeta($line);
  }

  private function invoiceMeta(array $line):array{return ['invoice_id'=>(int)($line['invoice_id']??0),'invoice_number'=>(string)($line['invoice_number']??''),'supplier_name'=>(string)($line['supplier_name']??''),'due_date'=>(int)($line['due_date']??0),'invoice_date'=>(int)($line['invoice_date']??0)];}

  /** Explainable 0-100 priority: money 40, deadline 25, variance 20, evidence/quality 15. */
  private function priority(float $amount,int $dueDate,array $variances,array $blocked,int $now):array{
    $money=min(40,(int)round(log10(max(1,$amount)+1)*10));$deadline=0;$daysToDue=null;if($dueDate>0){$daysToDue=(int)floor(($dueDate-$now)/86400);$deadline=$daysToDue<0?25:($daysToDue<=3?22:($daysToDue<=7?17:($daysToDue<=14?10:4)));}
    $variance=0;$severe=['missing_order','missing_verified_performance','amount_above_verified_performance'];foreach($variances as $v){if(in_array($v,$severe,true))$variance=max($variance,20);elseif(in_array($v,['amount_above_order','unit_price_variance','vat_variance'],true))$variance=max($variance,12);else$variance=max($variance,6);}
    $quality=0;$missingEvidence=0;$qualityRejected=0;$oldestDays=0;foreach($blocked as $p){if((int)($p['building_evidence_complete']??0)!==1)$missingEvidence++;if((int)($p['quality_accepted']??0)!==1)$qualityRejected++;$created=(int)($p['created']??0);if($created>0)$oldestDays=max($oldestDays,(int)floor(($now-$created)/86400));}$quality=min(15,($missingEvidence>0?7:0)+($qualityRejected>0?6:0)+($oldestDays>=7?2:0));
    $score=min(100,$money+$deadline+$variance+$quality);$level=$score>=75?'critical':($score>=50?'high':($score>=25?'medium':'low'));
    return ['score'=>$score,'level'=>$level,'components'=>['financial_impact'=>$money,'deadline'=>$deadline,'variance_severity'=>$variance,'evidence_quality'=>$quality],'days_to_due'=>$daysToDue,'oldest_blocking_performance_days'=>$oldestDays,'signals'=>['missing_evidence_count'=>$missingEvidence,'quality_not_accepted_count'=>$qualityRejected]];
  }
}
