<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;
use InvalidArgumentException;
use RuntimeException;
use UnexpectedValueException;

/** Reviews each evidence item before a performance receipt can be verified. */
final class PerformanceEvidenceReviewManager {
  public function __construct(private readonly Connection $database) {}

  public function review(int $receiptId, string $evidenceRef, string $decision, string $note, int $userId): void {
    if ($userId <= 0 || !in_array($decision, ['accepted','rejected'], true) || trim($note) === '') throw new InvalidArgumentException('Evidence review requires decision, note and human reviewer.');
    $receipt=$this->receipt($receiptId); if($receipt['status']!=='submitted') throw new UnexpectedValueException('Evidence can only be reviewed while performance is submitted.');
    if((int)$receipt['created_by']===$userId) throw new RuntimeException('The submitter may not review their own evidence.');
    $refs=json_decode((string)$receipt['evidence'],true,512,JSON_THROW_ON_ERROR); if(!is_array($refs)||!in_array($evidenceRef,$refs,true)) throw new UnexpectedValueException('Evidence reference is not part of this performance receipt.');
    $this->ensureStorage(); $now=time();
    $existing=$this->database->select('brebo_finance_performance_evidence_review','r')->fields('r',['id'])->condition('receipt_id',$receiptId)->condition('evidence_ref',$evidenceRef)->execute()->fetchField();
    $fields=['decision'=>$decision,'note'=>trim($note),'reviewed_by'=>$userId,'reviewed'=>$now,'changed'=>$now];
    if($existing){$this->database->update('brebo_finance_performance_evidence_review')->fields($fields)->condition('id',(int)$existing)->execute();}
    else{$this->database->insert('brebo_finance_performance_evidence_review')->fields(['receipt_id'=>$receiptId,'evidence_ref'=>$evidenceRef,'created'=>$now]+$fields)->execute();}
  }

  public function summary(int $receiptId): array {
    $receipt=$this->receipt($receiptId); $refs=json_decode((string)$receipt['evidence'],true,512,JSON_THROW_ON_ERROR); $refs=is_array($refs)?array_values($refs):[]; $this->ensureStorage();
    $rows=$this->database->select('brebo_finance_performance_evidence_review','r')->fields('r')->condition('receipt_id',$receiptId)->execute()->fetchAll(\PDO::FETCH_ASSOC); $by=[]; foreach($rows as $r)$by[(string)$r['evidence_ref']]=$r;
    $items=[];$accepted=0;$rejected=0;foreach($refs as $ref){$review=$by[(string)$ref]??null;$decision=$review['decision']??'pending';if($decision==='accepted')$accepted++;if($decision==='rejected')$rejected++;$items[]=['evidence_ref'=>$ref,'decision'=>$decision,'note'=>$review['note']??null,'reviewed_by'=>isset($review['reviewed_by'])?(int)$review['reviewed_by']:null,'reviewed'=>isset($review['reviewed'])?(int)$review['reviewed']:null];}
    return ['receipt_id'=>$receiptId,'total'=>count($refs),'accepted'=>$accepted,'rejected'=>$rejected,'pending'=>count($refs)-$accepted-$rejected,'all_accepted'=>count($refs)>0&&$accepted===count($refs),'items'=>$items];
  }

  private function receipt(int $id):array{$r=$this->database->select('brebo_finance_performance_receipt','p')->fields('p')->condition('id',$id)->execute()->fetchAssoc();if($r===false)throw new UnexpectedValueException('Performance receipt does not exist.');return$r;}
  private function ensureStorage():void{$s=$this->database->schema();if($s->tableExists('brebo_finance_performance_evidence_review'))return;$s->createTable('brebo_finance_performance_evidence_review',['fields'=>['id'=>['type'=>'serial','not null'=>true],'receipt_id'=>['type'=>'int','unsigned'=>true,'not null'=>true],'evidence_ref'=>['type'=>'varchar','length'=>255,'not null'=>true],'decision'=>['type'=>'varchar','length'=>16,'not null'=>true],'note'=>['type'=>'text','size'=>'big','not null'=>true],'reviewed_by'=>['type'=>'int','unsigned'=>true,'not null'=>true],'reviewed'=>['type'=>'int','unsigned'=>true,'not null'=>true],'created'=>['type'=>'int','unsigned'=>true,'not null'=>true],'changed'=>['type'=>'int','unsigned'=>true,'not null'=>true]],'primary key'=>['id'],'unique keys'=>['receipt_evidence'=>['receipt_id','evidence_ref']],'indexes'=>['receipt'=>['receipt_id'],'decision'=>['decision']]]);}
}
