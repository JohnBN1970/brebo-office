<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\brebo_finance\Contract\PerformanceEvidenceReviewRepositoryInterface;
use InvalidArgumentException;
use RuntimeException;
use UnexpectedValueException;

/** Reviews each evidence item before a performance receipt can be verified. */
final class PerformanceEvidenceReviewManager {
  public function __construct(private readonly PerformanceEvidenceReviewRepositoryInterface $repository, private readonly PerformanceLocationManager $locations) {}

  public function review(int $receiptId, string $evidenceRef, string $decision, string $note, int $userId): void {
    if($userId<=0||!in_array($decision,['accepted','rejected'],true)||trim($note)==='')throw new InvalidArgumentException('Evidence review requires decision, note and human reviewer.');
    $receipt=$this->receipt($receiptId);if($receipt['status']!=='submitted')throw new UnexpectedValueException('Evidence can only be reviewed while performance is submitted.');if((int)$receipt['created_by']===$userId)throw new RuntimeException('The submitter may not review their own evidence.');
    $items=$this->evidenceItems($receipt);$known=[];foreach($items as $item)$known[$this->key($item)]=$item;if(!isset($known[$evidenceRef]))throw new UnexpectedValueException('Evidence reference is not part of this performance receipt.');
    $now=time();$this->repository->saveReview($receiptId,$evidenceRef,['decision'=>$decision,'note'=>trim($note),'reviewed_by'=>$userId,'reviewed'=>$now,'changed'=>$now]);
  }

  public function summary(int $receiptId):array{
    $receipt=$this->receipt($receiptId);$items=$this->evidenceItems($receipt);$rows=$this->repository->reviews($receiptId);$by=[];foreach($rows as $r)$by[(string)$r['evidence_ref']]=$r;
    $out=[];$accepted=0;$rejected=0;foreach($items as $item){$key=$this->key($item);$review=$by[$key]??null;$decision=$review['decision']??'pending';if($decision==='accepted')$accepted++;if($decision==='rejected')$rejected++;$out[]=['evidence_ref'=>$key,'type'=>is_array($item)?($item['type']??null):null,'label'=>is_array($item)?($item['label']??null):null,'ref'=>is_array($item)?($item['ref']??null):$item,'decision'=>$decision,'note'=>$review['note']??null,'reviewed_by'=>isset($review['reviewed_by'])?(int)$review['reviewed_by']:null,'reviewed'=>isset($review['reviewed'])?(int)$review['reviewed']:null];}
    $location=$this->locations->forReceipt($receiptId);
    return['receipt_id'=>$receiptId,'performance'=>['description'=>(string)($receipt['description']??''),'amount_ex_vat'=>(string)($receipt['amount_ex_vat']??'0'),'status'=>(string)$receipt['status']],'location'=>$location,'total'=>count($items),'accepted'=>$accepted,'rejected'=>$rejected,'pending'=>count($items)-$accepted-$rejected,'all_accepted'=>count($items)>0&&$accepted===count($items),'items'=>$out];
  }

  private function evidenceItems(array $receipt):array{$x=json_decode((string)$receipt['evidence'],true,512,JSON_THROW_ON_ERROR);return is_array($x)?array_values($x):[];}
  private function key(mixed $item):string{if(is_array($item))return json_encode($item,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);return(string)$item;}
  private function receipt(int $id):array{$r=$this->repository->receipt($id);if($r===NULL)throw new UnexpectedValueException('Performance receipt does not exist.');return$r;}
}
