<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

use Drupal\Core\Database\Connection;

/** Stores, deduplicates and closes cross-domain operational escalations. */
final class RiskEscalationManager {
  public function __construct(private readonly Connection $database) {}

  /** @param array<string,mixed> $payload */
  public function escalate(string $domain,string $sourceReference,string $level,string $title,array $payload,array $audiences): int {
    $this->ensureStorage();
    $fingerprint=hash('sha256',$domain.'|'.$sourceReference.'|'.$level.'|'.$title.'|'.json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    $existing=$this->database->select('brebo_risk_escalation','e')->fields('e',['id'])->condition('fingerprint',$fingerprint)->condition('status',['open','assigned','in_progress'],'IN')->execute()->fetchField();
    if($existing)return(int)$existing;
    return(int)$this->database->insert('brebo_risk_escalation')->fields([
      'domain'=>$domain,'source_reference'=>$sourceReference,'level'=>$level,'title'=>$title,
      'payload_json'=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
      'audiences_json'=>json_encode(array_values(array_unique($audiences)),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
      'fingerprint'=>$fingerprint,'status'=>'open','created'=>time(),'changed'=>time(),
    ])->execute();
  }

  /** @return array<string,mixed>|null */
  public function find(int $id): ?array {
    $this->ensureStorage();$row=$this->database->select('brebo_risk_escalation','e')->fields('e')->condition('id',$id)->execute()->fetchAssoc();return$row?:NULL;
  }

  public function assign(int $id,int $ownerUid,?string $dueDate,string $decision): void {
    $this->ensureStorage();
    if($ownerUid<=0)throw new \InvalidArgumentException('Een verantwoordelijke is verplicht.');
    $this->database->update('brebo_risk_escalation')->fields([
      'owner_uid'=>$ownerUid,'due_date'=>$dueDate?:NULL,'decision_note'=>trim($decision)?:NULL,'status'=>'assigned','changed'=>time(),
    ])->condition('id',$id)->condition('status',['open','assigned','in_progress'],'IN')->execute();
  }

  public function start(int $id): void {
    $this->ensureStorage();$this->database->update('brebo_risk_escalation')->fields(['status'=>'in_progress','changed'=>time()])->condition('id',$id)->condition('status',['open','assigned'],'IN')->execute();
  }

  public function resolve(int $id,string $resolution,string $evidence,int $closedBy): void {
    $this->ensureStorage();
    if(trim($resolution)===''||trim($evidence)==='')throw new \InvalidArgumentException('Afhandeling en bewijs zijn verplicht om een escalatie te sluiten.');
    $this->database->update('brebo_risk_escalation')->fields([
      'status'=>'resolved','resolution_note'=>trim($resolution),'evidence_ref'=>trim($evidence),'closed_at'=>time(),'closed_by'=>$closedBy,'changed'=>time(),
    ])->condition('id',$id)->condition('status',['open','assigned','in_progress'],'IN')->execute();
  }

  private function ensureStorage(): void {
    $schema=$this->database->schema();
    if(!$schema->tableExists('brebo_risk_escalation')){
      $schema->createTable('brebo_risk_escalation',[
        'description'=>'Cross-domain BREBO Office operational risk escalations.',
        'fields'=>[
          'id'=>['type'=>'serial','not null'=>TRUE],'domain'=>['type'=>'varchar_ascii','length'=>64,'not null'=>TRUE],'source_reference'=>['type'=>'varchar','length'=>255,'not null'=>TRUE],
          'level'=>['type'=>'varchar_ascii','length'=>24,'not null'=>TRUE],'title'=>['type'=>'varchar','length'=>255,'not null'=>TRUE],'payload_json'=>['type'=>'text','size'=>'big','not null'=>FALSE],
          'audiences_json'=>['type'=>'text','not null'=>FALSE],'fingerprint'=>['type'=>'varchar_ascii','length'=>64,'not null'=>TRUE],'status'=>['type'=>'varchar_ascii','length'=>24,'not null'=>TRUE,'default'=>'open'],
          'created'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],'changed'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],
        ],'primary key'=>['id'],'unique keys'=>['fingerprint'=>['fingerprint']],'indexes'=>['status_level'=>['status','level'],'domain_source'=>['domain','source_reference']],
      ]);
    }
    $fields=[
      'owner_uid'=>['type'=>'int','unsigned'=>TRUE,'not null'=>FALSE],
      'due_date'=>['type'=>'varchar_ascii','length'=>10,'not null'=>FALSE],
      'decision_note'=>['type'=>'text','size'=>'big','not null'=>FALSE],
      'resolution_note'=>['type'=>'text','size'=>'big','not null'=>FALSE],
      'evidence_ref'=>['type'=>'varchar','length'=>1024,'not null'=>FALSE],
      'closed_at'=>['type'=>'int','unsigned'=>TRUE,'not null'=>FALSE],
      'closed_by'=>['type'=>'int','unsigned'=>TRUE,'not null'=>FALSE],
    ];
    foreach($fields as$name=>$definition)if(!$schema->fieldExists('brebo_risk_escalation',$name))$schema->addField('brebo_risk_escalation',$name,$definition);
    if(!$schema->indexExists('brebo_risk_escalation','owner_status'))$schema->addIndex('brebo_risk_escalation','owner_status',['owner_uid','status']);
    if(!$schema->indexExists('brebo_risk_escalation','due_status'))$schema->addIndex('brebo_risk_escalation','due_status',['due_date','status']);
  }
}
