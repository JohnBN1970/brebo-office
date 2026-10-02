<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\PerformanceEvidenceReviewRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabasePerformanceEvidenceReviewRepository implements PerformanceEvidenceReviewRepositoryInterface {
  public function __construct(private readonly Connection $database) {}
  public function receipt(int $id):?array{$r=$this->database->select('brebo_finance_performance_receipt','p')->fields('p')->condition('id',$id)->execute()->fetchAssoc();return $r===FALSE?NULL:$r;}
  public function saveReview(int $receiptId,string $evidenceRef,array $fields):void{$this->ensure();$id=$this->database->select('brebo_finance_performance_evidence_review','r')->fields('r',['id'])->condition('receipt_id',$receiptId)->condition('evidence_ref',$evidenceRef)->execute()->fetchField();if($id)$this->database->update('brebo_finance_performance_evidence_review')->fields($fields)->condition('id',(int)$id)->execute();else$this->database->insert('brebo_finance_performance_evidence_review')->fields(['receipt_id'=>$receiptId,'evidence_ref'=>$evidenceRef,'created'=>time()]+$fields)->execute();}
  public function reviews(int $receiptId):array{$this->ensure();return array_values($this->database->select('brebo_finance_performance_evidence_review','r')->fields('r')->condition('receipt_id',$receiptId)->execute()->fetchAll(\PDO::FETCH_ASSOC));}
  private function ensure():void{$s=$this->database->schema();if($s->tableExists('brebo_finance_performance_evidence_review'))return;$s->createTable('brebo_finance_performance_evidence_review',['fields'=>['id'=>['type'=>'serial','not null'=>TRUE],'receipt_id'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],'evidence_ref'=>['type'=>'varchar','length'=>255,'not null'=>TRUE],'decision'=>['type'=>'varchar','length'=>16,'not null'=>TRUE],'note'=>['type'=>'text','size'=>'big','not null'=>TRUE],'reviewed_by'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],'reviewed'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],'created'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],'changed'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE]],'primary key'=>['id'],'unique keys'=>['receipt_evidence'=>['receipt_id','evidence_ref']],'indexes'=>['receipt'=>['receipt_id'],'decision'=>['decision']]]);}
}
