<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\FinancialDecisionEscalationRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseFinancialDecisionEscalationRepository implements FinancialDecisionEscalationRepositoryInterface {
  public function __construct(private readonly Connection $database) {}
  private function ensure():void{$s=$this->database->schema();if($s->tableExists('brebo_finance_decision_escalation'))return;$s->createTable('brebo_finance_decision_escalation',['fields'=>['exception_id'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],'project_nid'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],'attention'=>['type'=>'varchar','length'=>32,'not null'=>TRUE],'escalation_level'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE,'default'=>0],'due_at'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],'changed'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE]],'primary key'=>['exception_id'],'indexes'=>['project_attention'=>['project_nid','attention'],'due_at'=>['due_at']]]);}
  public function state(int $id):?array{$this->ensure();$r=$this->database->select('brebo_finance_decision_escalation','e')->fields('e')->condition('exception_id',$id)->execute()->fetchAssoc();return$r===FALSE?NULL:$r;}
  public function store(int $id,int $p,string $a,int $l,int $d,int $n):void{$this->ensure();$this->database->merge('brebo_finance_decision_escalation')->key(['exception_id'=>$id])->fields(['project_nid'=>$p,'attention'=>$a,'escalation_level'=>$l,'due_at'=>$d,'changed'=>$n])->execute();}
  public function audit(array $fields):void{$this->database->insert('brebo_finance_audit')->fields($fields)->execute();}
}
