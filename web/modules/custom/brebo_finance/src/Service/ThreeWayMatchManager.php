<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;
use UnexpectedValueException;

/** Matches supplier invoice lines to order and verified performance. */
final class ThreeWayMatchManager {
  public function __construct(private readonly Connection $database, private readonly VatCalculator $decimal, private readonly FinancialEuroTraceFindingSynchronizer $euroTraceSynchronizer) {}

  public function matchLine(int $invoiceLineId, int $userId): array {
    $query=$this->database->select('brebo_finance_purchase_invoice_line','il'); $query->join('brebo_finance_purchase_invoice','i','i.id = il.invoice_id'); $query->leftJoin('brebo_finance_commitment_line','cl','cl.id = il.commitment_line_id');
    $query->addField('i','project_nid'); $query->addField('i','id','invoice_id'); $query->fields('il'); $query->addField('cl','amount_ex_vat','ordered_amount_ex_vat'); $query->addField('cl','unit_price_ex_vat','ordered_unit_price_ex_vat'); $query->addField('cl','vat_code','ordered_vat_code'); $query->addField('cl','vat_rate','ordered_vat_rate');
    $line=$query->condition('il.id',$invoiceLineId)->execute()->fetchAssoc(); if($line===FALSE) throw new UnexpectedValueException('Purchase invoice line does not exist.');
    $variances=[];
    if(empty($line['commitment_line_id'])){$variances[]='missing_order';}
    else{$previouslyMatched=$this->previouslyMatchedAmount((int)$line['commitment_line_id'],$invoiceLineId);$verifiedPerformance=$this->verifiedPerformanceAmount((int)$line['commitment_line_id']);$availableOrder=$this->decimal->subtract((string)$line['ordered_amount_ex_vat'],$previouslyMatched);$availablePerformance=$this->decimal->subtract($verifiedPerformance,$previouslyMatched);
      if($this->decimal->compare((string)$line['amount_ex_vat'],$availableOrder)>0)$variances[]='amount_above_order';
      if($this->decimal->compare((string)$line['amount_ex_vat'],$availablePerformance)>0)$variances[]=$this->decimal->compare($verifiedPerformance,'0')===0?'missing_verified_performance':'amount_above_verified_performance';
      if($this->decimal->compare((string)$line['unit_price_ex_vat'],(string)$line['ordered_unit_price_ex_vat'])!==0)$variances[]='unit_price_variance';
      if((string)$line['vat_code']!==(string)$line['ordered_vat_code']||$this->decimal->compare((string)$line['vat_rate'],(string)$line['ordered_vat_rate'])!==0)$variances[]='vat_variance';}
    $status=$variances===[]?'matched':'exception'; $varianceAmount='0.0000'; if(isset($availableOrder)&&$this->decimal->compare((string)$line['amount_ex_vat'],$availableOrder)>0)$varianceAmount=$this->decimal->subtract((string)$line['amount_ex_vat'],$availableOrder);
    $now=time(); $this->database->update('brebo_finance_purchase_invoice_line')->fields(['match_status'=>$status,'variance_code'=>$variances!==[]?implode(',',$variances):NULL,'variance_amount_ex_vat'=>$varianceAmount,'changed'=>$now,'changed_by'=>$userId])->condition('id',$invoiceLineId)->execute();
    $this->refreshInvoiceMatchStatus((int)$line['invoice_id'],$now,$userId);
    $this->database->insert('brebo_finance_audit')->fields(['project_nid'=>(int)$line['project_nid'],'entity_type'=>'purchase_invoice_line','entity_id'=>$invoiceLineId,'action'=>'three_way_match','payload'=>json_encode(['status'=>$status,'variances'=>$variances,'variance_amount_ex_vat'=>$varianceAmount],JSON_THROW_ON_ERROR),'reason'=>$variances===[]?'Order, verified performance and invoice agree.':'Invoice line requires review before payment.','created'=>$now,'created_by'=>$userId])->execute();
    $this->euroTraceSynchronizer->sync('purchase_invoice',(int)$line['invoice_id'],$userId);
    return ['status'=>$status,'variances'=>$variances];
  }

  private function verifiedPerformanceAmount(int $commitmentLineId):string{$q=$this->database->select('brebo_finance_performance_receipt','r');$q->condition('commitment_line_id',$commitmentLineId)->condition('status','verified')->condition('building_evidence_complete',1)->condition('quality_accepted',1)->addExpression('COALESCE(SUM(amount_ex_vat), 0)','verified_total');return(string)$q->execute()->fetchField();}
  private function previouslyMatchedAmount(int $commitmentLineId,int $excludeLineId):string{$q=$this->database->select('brebo_finance_purchase_invoice_line','il');$q->condition('commitment_line_id',$commitmentLineId)->condition('id',$excludeLineId,'<>')->condition('match_status','matched')->addExpression('COALESCE(SUM(amount_ex_vat), 0)','matched_total');return(string)$q->execute()->fetchField();}
  private function refreshInvoiceMatchStatus(int $invoiceId,int $now,int $userId):void{$q=$this->database->select('brebo_finance_purchase_invoice_line','il');$q->condition('invoice_id',$invoiceId);$q->addExpression("SUM(CASE WHEN match_status = 'exception' THEN 1 ELSE 0 END)",'exceptions');$q->addExpression("SUM(CASE WHEN match_status = 'unmatched' THEN 1 ELSE 0 END)",'unmatched');$c=$q->execute()->fetchAssoc();$status=(int)$c['exceptions']>0?'exception':((int)$c['unmatched']>0?'unmatched':'matched');$this->database->update('brebo_finance_purchase_invoice')->fields(['match_status'=>$status,'changed'=>$now,'changed_by'=>$userId])->condition('id',$invoiceId)->execute();}
}
