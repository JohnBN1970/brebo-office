<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\brebo_finance\Contract\ThreeWayMatchRepositoryInterface;
use UnexpectedValueException;

/** Matches supplier invoice lines to order and verified performance. */
final class ThreeWayMatchManager {
  public function __construct(private readonly ThreeWayMatchRepositoryInterface $repository, private readonly VatCalculator $decimal, private readonly FinancialEuroTraceFindingSynchronizer $euroTraceSynchronizer) {}

  public function matchLine(int $invoiceLineId, int $userId): array {
    $line=$this->repository->invoiceLineContext($invoiceLineId); if($line===NULL) throw new UnexpectedValueException('Purchase invoice line does not exist.');
    $variances=[];
    if(empty($line['commitment_line_id'])){$variances[]='missing_order';}
    else{$previouslyMatched=$this->repository->previouslyMatchedAmount((int)$line['commitment_line_id'],$invoiceLineId);$verifiedPerformance=$this->repository->verifiedPerformanceAmount((int)$line['commitment_line_id']);$availableOrder=$this->decimal->subtract((string)$line['ordered_amount_ex_vat'],$previouslyMatched);$availablePerformance=$this->decimal->subtract($verifiedPerformance,$previouslyMatched);
      if($this->decimal->compare((string)$line['amount_ex_vat'],$availableOrder)>0)$variances[]='amount_above_order';
      if($this->decimal->compare((string)$line['amount_ex_vat'],$availablePerformance)>0)$variances[]=$this->decimal->compare($verifiedPerformance,'0')===0?'missing_verified_performance':'amount_above_verified_performance';
      if($this->decimal->compare((string)$line['unit_price_ex_vat'],(string)$line['ordered_unit_price_ex_vat'])!==0)$variances[]='unit_price_variance';
      if((string)$line['vat_code']!==(string)$line['ordered_vat_code']||$this->decimal->compare((string)$line['vat_rate'],(string)$line['ordered_vat_rate'])!==0)$variances[]='vat_variance';}
    $status=$variances===[]?'matched':'exception'; $varianceAmount='0.0000'; if(isset($availableOrder)&&$this->decimal->compare((string)$line['amount_ex_vat'],$availableOrder)>0)$varianceAmount=$this->decimal->subtract((string)$line['amount_ex_vat'],$availableOrder);
    $now=time(); $this->repository->updateInvoiceLine($invoiceLineId,['match_status'=>$status,'variance_code'=>$variances!==[]?implode(',',$variances):NULL,'variance_amount_ex_vat'=>$varianceAmount,'changed'=>$now,'changed_by'=>$userId]);
    $counts=$this->repository->invoiceMatchCounts((int)$line['invoice_id']); $invoiceStatus=$counts['exceptions']>0?'exception':($counts['unmatched']>0?'unmatched':'matched'); $this->repository->updateInvoice((int)$line['invoice_id'],['match_status'=>$invoiceStatus,'changed'=>$now,'changed_by'=>$userId]);
    $this->repository->insertAudit(['project_nid'=>(int)$line['project_nid'],'entity_type'=>'purchase_invoice_line','entity_id'=>$invoiceLineId,'action'=>'three_way_match','payload'=>json_encode(['status'=>$status,'variances'=>$variances,'variance_amount_ex_vat'=>$varianceAmount],JSON_THROW_ON_ERROR),'reason'=>$variances===[]?'Order, verified performance and invoice agree.':'Invoice line requires review before payment.','created'=>$now,'created_by'=>$userId]);
    $this->euroTraceSynchronizer->sync('purchase_invoice',(int)$line['invoice_id'],$userId);
    return ['status'=>$status,'variances'=>$variances];
  }


}