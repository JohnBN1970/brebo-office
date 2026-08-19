<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\Core\Database\Connection;
use InvalidArgumentException;

/** Owns calculator assignment, deadline and workflow state for calculations. */
final class CalculationWorkAssignmentManager {
  public function __construct(private readonly Connection $database) {}

  public function save(int $calculationId, int $ownerUid, ?int $dueDate, string $status, string $action, int $userId): array {
    if($calculationId<=0||$ownerUid<=0||trim($action)==='') throw new InvalidArgumentException('Calculatie, verantwoordelijke en actie zijn verplicht.');
    if(!in_array($status,['open','in_progress','waiting','ready','resolved','cancelled'],true)) throw new InvalidArgumentException('Ongeldige calculatiestatus.');
    $this->ensureStorage();$now=time();
    $existing=$this->database->select('brebo_calculation_work_assignment','a')->fields('a',['id'])->condition('calculation_id',$calculationId)->condition('status',['open','in_progress','waiting','ready'],'IN')->orderBy('changed','DESC')->range(0,1)->execute()->fetchField();
    $fields=['owner_uid'=>$ownerUid,'due_date'=>$dueDate,'status'=>$status,'action'=>trim($action),'changed'=>$now,'changed_by'=>$userId];
    if($existing){$id=(int)$existing;$this->database->update('brebo_calculation_work_assignment')->fields($fields)->condition('id',$id)->execute();}
    else{$id=(int)$this->database->insert('brebo_calculation_work_assignment')->fields(['calculation_id'=>$calculationId,'created'=>$now,'created_by'=>$userId]+$fields)->execute();}
    return $this->get($id);
  }

  public function forOwner(int $uid): array {
    $this->ensureStorage();$today=(new \DateTimeImmutable('today'))->getTimestamp();$tomorrow=(new \DateTimeImmutable('tomorrow'))->getTimestamp();
    $q=$this->database->select('brebo_calculation_work_assignment','a')->fields('a')->condition('owner_uid',$uid)->condition('status',['open','in_progress','waiting','ready'],'IN')->orderBy('due_date','ASC')->orderBy('changed','DESC');
    $items=array_map(static fn(object $r):array=>(array)$r,$q->execute()->fetchAll());
    foreach($items as &$item){$due=$item['due_date']===null?null:(int)$item['due_date'];$item['urgency']=$due===null?'no_deadline':($due<$today?'overdue':($due<$tomorrow?'today':'upcoming'));$item['days_overdue']=$due!==null&&$due<$today?(int)floor(($today-$due)/86400):0;}unset($item);
    return $items;
  }

  public function get(int $id): array {$this->ensureStorage();$r=$this->database->select('brebo_calculation_work_assignment','a')->fields('a')->condition('id',$id)->execute()->fetchAssoc();return $r===false?[]:$r;}

  private function ensureStorage(): void {
    $s=$this->database->schema();if($s->tableExists('brebo_calculation_work_assignment'))return;
    $s->createTable('brebo_calculation_work_assignment',['fields'=>['id'=>['type'=>'serial','not null'=>true],'calculation_id'=>['type'=>'int','unsigned'=>true,'not null'=>true],'owner_uid'=>['type'=>'int','unsigned'=>true,'not null'=>true],'due_date'=>['type'=>'int','unsigned'=>true,'not null'=>false],'status'=>['type'=>'varchar','length'=>24,'not null'=>true],'action'=>['type'=>'text','size'=>'big','not null'=>true],'created'=>['type'=>'int','unsigned'=>true,'not null'=>true],'created_by'=>['type'=>'int','unsigned'=>true,'not null'=>true],'changed'=>['type'=>'int','unsigned'=>true,'not null'=>true],'changed_by'=>['type'=>'int','unsigned'=>true,'not null'=>true]],'primary key'=>['id'],'indexes'=>['calculation_status'=>['calculation_id','status'],'owner_status'=>['owner_uid','status'],'due_date'=>['due_date']]]);
  }
}
