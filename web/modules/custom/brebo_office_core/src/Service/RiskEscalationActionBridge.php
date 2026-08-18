<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/** Connects automatic escalations to canonical BREBO action nodes. */
final class RiskEscalationActionBridge {
  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public function ensureAction(int $escalationId, AccountInterface $actor): int {
    $escalation=$this->database->select('brebo_risk_escalation','e')->fields('e')->condition('id',$escalationId)->execute()->fetchAssoc();
    if(!$escalation) throw new \InvalidArgumentException('Escalatie bestaat niet.');
    if(!empty($escalation['action_nid'])) return (int)$escalation['action_nid'];
    $payload=json_decode((string)($escalation['payload_json']??''),TRUE)?:[];
    $owner=(int)($escalation['owner_uid']??0);
    if($owner<=0) throw new \RuntimeException('Wijs eerst een verantwoordelijke toe.');

    $storage=$this->entityTypeManager->getStorage('node');
    $action=$storage->create(['type'=>'brebo_action','title'=>'Escalatie: '.$escalation['title']]);
    $this->setIfPresent($action,'field_brebo_project_ref',(int)($payload['project_nid']??0)?:NULL);
    $this->setIfPresent($action,'field_brebo_action_description',(string)($escalation['resolution']?:$escalation['title']));
    $this->setIfPresent($action,'field_brebo_priority',$escalation['level']==='kritiek'?'Kritisch':'Hoog');
    $this->setIfPresent($action,'field_brebo_due_date',$escalation['due_date']?:NULL);
    $this->setIfPresent($action,'field_brebo_responsible_user',$owner);
    $this->setIfPresent($action,'field_brebo_action_status','Open');
    $this->setIfPresent($action,'field_brebo_action_result','Automatisch aangemaakt vanuit Control Room escalatie #'.$escalationId.'.');
    $action->setOwnerId((int)$actor->id());
    $action->save();

    $this->database->update('brebo_risk_escalation')->fields(['action_nid'=>(int)$action->id(),'status'=>'in_progress','changed'=>time()])->condition('id',$escalationId)->execute();
    return (int)$action->id();
  }

  public function syncFromAction(NodeInterface $action): void {
    if($action->bundle()!=='brebo_action') return;
    $rows=$this->database->select('brebo_risk_escalation','e')->fields('e')->condition('action_nid',(int)$action->id())->condition('status',['closed'],'NOT IN')->execute()->fetchAllAssoc('id',\PDO::FETCH_ASSOC);
    if(!$rows) return;
    $status=$action->hasField('field_brebo_action_status')&&!$action->get('field_brebo_action_status')->isEmpty()?(string)$action->get('field_brebo_action_status')->value:'';
    $result=$action->hasField('field_brebo_action_result')&&!$action->get('field_brebo_action_result')->isEmpty()?trim((string)$action->get('field_brebo_action_result')->value):'';
    if(mb_strtolower($status)!=='gereed'||$result==='') return;
    foreach($rows as$row){$this->database->update('brebo_risk_escalation')->fields(['status'=>'closed','evidence'=>$result,'closed_at'=>time(),'changed'=>time()])->condition('id',(int)$row['id'])->execute();}
  }

  private function setIfPresent(NodeInterface $node,string $field,mixed $value): void {
    if($value!==NULL&&$node->hasField($field))$node->set($field,$value);
  }
}
