<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\FinancialNotificationOutboxRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseFinancialNotificationOutboxRepository implements FinancialNotificationOutboxRepositoryInterface {
  public function __construct(private readonly Connection $database) {}
  public function ensureStorage():void{$s=$this->database->schema();if($s->tableExists('brebo_finance_notification_outbox'))return;$s->createTable('brebo_finance_notification_outbox',['description'=>'Durable notification outbox for BREBO Finance decisions.','fields'=>['id'=>['type'=>'serial','not null'=>TRUE],'project_nid'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],'exception_id'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],'attention'=>['type'=>'varchar','length'=>32,'not null'=>TRUE],'audience'=>['type'=>'varchar','length'=>32,'not null'=>TRUE],'recipient_uid'=>['type'=>'int','unsigned'=>TRUE,'not null'=>FALSE],'recipient_mail'=>['type'=>'varchar','length'=>254,'not null'=>FALSE],'channel'=>['type'=>'varchar','length'=>32,'not null'=>TRUE],'status'=>['type'=>'varchar','length'=>24,'not null'=>TRUE],'attempts'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE,'default'=>0],'dedupe_key'=>['type'=>'varchar','length'=>64,'not null'=>TRUE],'payload'=>['type'=>'text','size'=>'big','not null'=>TRUE],'last_error'=>['type'=>'text','size'=>'big','not null'=>FALSE],'created'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],'changed'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE]],'primary key'=>['id'],'unique keys'=>['dedupe_key'=>['dedupe_key']],'indexes'=>['status_changed'=>['status','changed'],'recipient_status'=>['recipient_uid','status'],'project_exception'=>['project_nid','exception_id']]]);}
  public function existsByDedupeKey(string $key):bool{return $this->database->select('brebo_finance_notification_outbox','o')->fields('o',['id'])->condition('dedupe_key',$key)->execute()->fetchField()!==FALSE;}
  public function create(array $fields):int{return (int)$this->database->insert('brebo_finance_notification_outbox')->fields($fields)->execute();}
  private function attempts(int $id):int{$v=$this->database->select('brebo_finance_notification_outbox','o')->fields('o',['attempts'])->condition('id',$id)->execute()->fetchField();return $v===FALSE?0:(int)$v;}
  public function markReady(int $id,int $changed):void{$this->database->update('brebo_finance_notification_outbox')->fields(['status'=>'ready','attempts'=>$this->attempts($id)+1,'last_error'=>NULL,'changed'=>$changed])->condition('id',$id)->condition('status',['queued','retry'],'IN')->execute();}
  public function markRetry(int $id,string $error,int $changed):void{$this->database->update('brebo_finance_notification_outbox')->fields(['status'=>'retry','attempts'=>$this->attempts($id)+1,'last_error'=>mb_substr($error,0,2000),'changed'=>$changed])->condition('id',$id)->execute();}
  public function forUser(int $uid,bool $unreadOnly):array{$q=$this->database->select('brebo_finance_notification_outbox','o')->fields('o')->condition('recipient_uid',$uid)->condition('channel','in_app')->condition('status',$unreadOnly?['ready']:['ready','read'],'IN')->orderBy('created','DESC');return array_values($q->execute()->fetchAll(\PDO::FETCH_ASSOC));}
  public function markReadForUser(int $id,int $uid,int $changed):bool{return $this->database->update('brebo_finance_notification_outbox')->fields(['status'=>'read','changed'=>$changed])->condition('id',$id)->condition('recipient_uid',$uid)->condition('channel','in_app')->condition('status','ready')->execute()>0;}
  public function unreadCount(int $uid):int{return (int)$this->database->select('brebo_finance_notification_outbox','o')->condition('recipient_uid',$uid)->condition('channel','in_app')->condition('status','ready')->countQuery()->execute()->fetchField();}
  public function load(int $id):?array{$r=$this->database->select('brebo_finance_notification_outbox','o')->fields('o')->condition('id',$id)->execute()->fetchAssoc();return $r===FALSE?NULL:$r;}
}
