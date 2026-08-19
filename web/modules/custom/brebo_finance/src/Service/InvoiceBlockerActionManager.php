<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;
use InvalidArgumentException;

final class InvoiceBlockerActionManager {
  public function __construct(private readonly Connection $database) {}

  public function save(int $invoiceLineId, int $projectNid, int $ownerUid, string $action, ?int $dueDate, string $status, int $userId): array {
    if ($invoiceLineId <= 0 || $projectNid <= 0 || $ownerUid <= 0 || trim($action) === '') throw new InvalidArgumentException('Factuurregel, project, eigenaar en actie zijn verplicht.');
    if (!in_array($status, ['open','in_progress','waiting','resolved','cancelled'], true)) throw new InvalidArgumentException('Ongeldige opvolgstatus.');
    $this->ensureStorage(); $now=time();
    $existing=$this->database->select('brebo_finance_invoice_blocker_action','a')->fields('a',['id'])->condition('invoice_line_id',$invoiceLineId)->condition('status',['open','in_progress','waiting'],'IN')->orderBy('changed','DESC')->range(0,1)->execute()->fetchField();
    $fields=['project_nid'=>$projectNid,'owner_uid'=>$ownerUid,'action'=>trim($action),'due_date'=>$dueDate,'status'=>$status,'changed'=>$now,'changed_by'=>$userId];
    if($existing){$this->database->update('brebo_finance_invoice_blocker_action')->fields($fields)->condition('id',(int)$existing)->execute();$id=(int)$existing;}else{$id=(int)$this->database->insert('brebo_finance_invoice_blocker_action')->fields(['invoice_line_id'=>$invoiceLineId,'created'=>$now,'created_by'=>$userId]+$fields)->execute();}
    return $this->get($id);
  }

  public function forProject(int $projectNid): array {$this->ensureStorage();$q=$this->database->select('brebo_finance_invoice_blocker_action','a')->fields('a')->condition('project_nid',$projectNid)->orderBy('status','ASC')->orderBy('due_date','ASC')->orderBy('changed','DESC');return array_map(static fn(object $r):array=>(array)$r,$q->execute()->fetchAll());}
  public function get(int $id): array {$this->ensureStorage();$r=$this->database->select('brebo_finance_invoice_blocker_action','a')->fields('a')->condition('id',$id)->execute()->fetchAssoc();return $r===false?[]:$r;}

  private function ensureStorage(): void {
    $s=$this->database->schema();if($s->tableExists('brebo_finance_invoice_blocker_action'))return;
    $s->createTable('brebo_finance_invoice_blocker_action',['fields'=>['id'=>['type'=>'serial','not null'=>true],'invoice_line_id'=>['type'=>'int','unsigned'=>true,'not null'=>true],'project_nid'=>['type'=>'int','unsigned'=>true,'not null'=>true],'owner_uid'=>['type'=>'int','unsigned'=>true,'not null'=>true],'action'=>['type'=>'text','size'=>'big','not null'=>true],'due_date'=>['type'=>'int','unsigned'=>true,'not null'=>false],'status'=>['type'=>'varchar','length'=>24,'not null'=>true],'created'=>['type'=>'int','unsigned'=>true,'not null'=>true],'created_by'=>['type'=>'int','unsigned'=>true,'not null'=>true],'changed'=>['type'=>'int','unsigned'=>true,'not null'=>true],'changed_by'=>['type'=>'int','unsigned'=>true,'not null'=>true]],'primary key'=>['id'],'indexes'=>['invoice_line'=>['invoice_line_id'],'project_status'=>['project_nid','status'],'owner_status'=>['owner_uid','status'],'due_date'=>['due_date']]]);
  }
}
