<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;

/** Governs preventive improvements proposed from recurring risk patterns. */
final class RiskImprovementManager {
  public function __construct(private readonly Connection $database) {}

  public function ensureStorage(): void {
    $schema=$this->database->schema();
    if($schema->tableExists('brebo_risk_improvement'))return;
    $schema->createTable('brebo_risk_improvement',[
      'description'=>'Governed preventive improvements derived from recurring BREBO risks.',
      'fields'=>[
        'id'=>['type'=>'serial','not null'=>TRUE],
        'fingerprint'=>['type'=>'varchar_ascii','length'=>64,'not null'=>TRUE],
        'domain'=>['type'=>'varchar_ascii','length'=>128,'not null'=>TRUE],
        'cause'=>['type'=>'text','not null'=>TRUE],
        'proposal'=>['type'=>'text','size'=>'big','not null'=>TRUE],
        'status'=>['type'=>'varchar_ascii','length'=>32,'not null'=>TRUE,'default'=>'proposed'],
        'owner_uid'=>['type'=>'int','unsigned'=>TRUE,'not null'=>FALSE],
        'decision_note'=>['type'=>'text','size'=>'big','not null'=>FALSE],
        'measure_note'=>['type'=>'text','size'=>'big','not null'=>FALSE],
        'validation_target'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE,'default'=>3],
        'validated_cases'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE,'default'=>0],
        'created'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],
        'created_by'=>['type'=>'int','unsigned'=>TRUE,'not null'=>FALSE],
        'decided_at'=>['type'=>'int','unsigned'=>TRUE,'not null'=>FALSE],
        'closed_at'=>['type'=>'int','unsigned'=>TRUE,'not null'=>FALSE],
      ],
      'primary key'=>['id'],'unique keys'=>['fingerprint'=>['fingerprint']],'indexes'=>['status'=>['status'],'domain'=>['domain']],
    ]);
  }

  /** @param array<string,mixed> $suggestion */
  public function registerSuggestion(array $suggestion,AccountInterface $account): int {
    $this->ensureStorage();$fingerprint=hash('sha256',(string)$suggestion['domain'].'|'.(string)$suggestion['cause']);
    $existing=$this->database->select('brebo_risk_improvement','i')->fields('i',['id'])->condition('fingerprint',$fingerprint)->execute()->fetchField();
    if($existing)return(int)$existing;
    return(int)$this->database->insert('brebo_risk_improvement')->fields([
      'fingerprint'=>$fingerprint,'domain'=>$suggestion['domain'],'cause'=>$suggestion['cause'],'proposal'=>$suggestion['proposal'],
      'status'=>'proposed','validation_target'=>3,'validated_cases'=>0,'created'=>time(),'created_by'=>(int)$account->id(),
    ])->execute();
  }

  public function decide(int $id,bool $approve,string $decision,?int $ownerUid,AccountInterface $account): void {
    $this->ensureStorage();if(trim($decision)==='')throw new \RuntimeException('Leg de motivering van het besluit vast.');
    $this->database->update('brebo_risk_improvement')->fields(['status'=>$approve?'approved':'rejected','decision_note'=>trim($decision),'owner_uid'=>$ownerUid,'decided_at'=>time()])->condition('id',$id)->execute();
  }

  public function startValidation(int $id,string $measure): void {
    if(trim($measure)==='')throw new \RuntimeException('Leg de concrete preventieve maatregel vast voordat validatie start.');
    $this->database->update('brebo_risk_improvement')->fields(['status'=>'validating','measure_note'=>trim($measure),'validated_cases'=>0])->condition('id',$id)->condition('status','approved')->execute();
  }

  public function recordValidation(int $id,bool $successful): void {
    $row=$this->database->select('brebo_risk_improvement','i')->fields('i')->condition('id',$id)->execute()->fetchAssoc();
    if(!$row||$row['status']!=='validating')throw new \RuntimeException('Verbetermaatregel is niet in validatie.');
    if(!$successful){$this->database->update('brebo_risk_improvement')->fields(['status'=>'needs_revision'])->condition('id',$id)->execute();return;}
    $count=(int)$row['validated_cases']+1;$fields=['validated_cases'=>$count];
    if($count>=(int)$row['validation_target']){$fields['status']='effective';$fields['closed_at']=time();}
    $this->database->update('brebo_risk_improvement')->fields($fields)->condition('id',$id)->execute();
  }
}
