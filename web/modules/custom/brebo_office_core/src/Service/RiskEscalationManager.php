<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

use Drupal\Core\Database\Connection;

/** Stores and deduplicates cross-domain operational risk escalations. */
final class RiskEscalationManager {
  public function __construct(private readonly Connection $database) {}

  /** @param array<string,mixed> $payload */
  public function escalate(string $domain,string $sourceReference,string $level,string $title,array $payload,array $audiences): int {
    $this->ensureStorage();
    $fingerprint=hash('sha256',$domain.'|'.$sourceReference.'|'.$level.'|'.$title.'|'.json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    $existing=$this->database->select('brebo_risk_escalation','e')->fields('e',['id'])->condition('fingerprint',$fingerprint)->condition('status','open')->execute()->fetchField();
    if($existing) return (int)$existing;
    return (int)$this->database->insert('brebo_risk_escalation')->fields([
      'domain'=>$domain,'source_reference'=>$sourceReference,'level'=>$level,'title'=>$title,
      'payload_json'=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
      'audiences_json'=>json_encode(array_values(array_unique($audiences)),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
      'fingerprint'=>$fingerprint,'status'=>'open','created'=>time(),'changed'=>time(),
    ])->execute();
  }

  private function ensureStorage(): void {
    $schema=$this->database->schema();
    if($schema->tableExists('brebo_risk_escalation')) return;
    $schema->createTable('brebo_risk_escalation',[
      'description'=>'Cross-domain BREBO Office operational risk escalations.',
      'fields'=>[
        'id'=>['type'=>'serial','not null'=>TRUE],
        'domain'=>['type'=>'varchar_ascii','length'=>64,'not null'=>TRUE],
        'source_reference'=>['type'=>'varchar','length'=>255,'not null'=>TRUE],
        'level'=>['type'=>'varchar_ascii','length'=>24,'not null'=>TRUE],
        'title'=>['type'=>'varchar','length'=>255,'not null'=>TRUE],
        'payload_json'=>['type'=>'text','size'=>'big','not null'=>FALSE],
        'audiences_json'=>['type'=>'text','not null'=>FALSE],
        'fingerprint'=>['type'=>'varchar_ascii','length'=>64,'not null'=>TRUE],
        'status'=>['type'=>'varchar_ascii','length'=>24,'not null'=>TRUE,'default'=>'open'],
        'created'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],
        'changed'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],
      ],
      'primary key'=>['id'],
      'unique keys'=>['fingerprint'=>['fingerprint']],
      'indexes'=>['status_level'=>['status','level'],'domain_source'=>['domain','source_reference']],
    ]);
  }
}
