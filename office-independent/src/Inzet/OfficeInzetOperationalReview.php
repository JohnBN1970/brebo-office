<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;

use Brebo\Office\Intake\WebsiteCrmOperatorContext;
use PDO;
use DomainException;
use InvalidArgumentException;
use LogicException;

require_once __DIR__.'/OperationalInzetStore.php';
require_once __DIR__.'/OfficeInzetIdentityMappingGuard.php';

/** Current Office authority guards an atomic decision in the separate operational store. */
final class OfficeInzetOperationalReview {
  public const PAGE_SIZE=25;
  private array $people;
  public function __construct(private PDO $office,private PDO $operational,array $verifiedPeople) {
    if ($office===$operational || !in_array($office->getAttribute(PDO::ATTR_DRIVER_NAME),['mysql','sqlite'],true)
      || $operational->getAttribute(PDO::ATTR_DRIVER_NAME)!=='sqlite') throw new InvalidArgumentException('Separate Office and operational stores required');
    $mappings=[];
    foreach ($verifiedPeople as $operator=>$person) $mappings[]=['source_system'=>'office_operator','source_person_id'=>(string)$operator,'office_person_id'=>$person];
    OfficeInzetIdentityMappingGuard::validate($mappings);
    $this->people=$verifiedPeople;
  }
  private function person(string $operator): int {
    if (!isset($this->people[$operator])) throw new DomainException('Verified employee mapping required');
    return $this->people[$operator];
  }
  private static function nativeId(mixed $value): int {
    if ((!is_string($value) && !is_int($value)) || !preg_match('/^[1-9][0-9]{0,18}$/D',(string)$value)
      || filter_var($value,FILTER_VALIDATE_INT)===false || (string)(int)$value!==(string)$value) {
      throw new LogicException('Verified native identifiers required');
    }
    return (int)$value;
  }
  private function lockSuffix(): string { return $this->office->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''; }
  private function managedProjects(WebsiteCrmOperatorContext $operator): array {
    $this->person($operator->operatorId);
    $q=$this->office->prepare("SELECT p.id,p.title FROM office_project_memberships m
      INNER JOIN office_projects p ON p.id=m.project_id
      INNER JOIN office_operator_accounts a ON a.id=m.operator_id AND a.active=1
      INNER JOIN office_operator_sessions s ON s.operator_id=a.id AND s.revoked_at IS NULL
      WHERE a.id=? AND s.token_hash=? AND s.expires_at>? AND m.permission='manage' ORDER BY p.id");
    $q->execute([$operator->operatorId,$operator->sessionId,gmdate('Y-m-d H:i:s')]);$projects=[];
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) $projects[self::nativeId($row['id'])]=(string)$row['title'];
    if ($projects===[]) throw new DomainException('Current project management permission required');
    return $projects;
  }
  private static function registration(PDO $db,string $id): array {
    $q=$db->prepare('SELECT r.*,p.project_id,p.person_id,p.work_date,p.planned_minutes,p.revision AS planning_revision,p.updated_at AS planning_updated_at
      FROM inzet_registration r INNER JOIN inzet_planning p ON p.id=r.planning_id WHERE r.id=?');
    $q->execute([$id]);$row=$q->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) throw new DomainException('Registration not available');
    return $row;
  }
  private function lineage(PDO $db,array $row): array {
    $date=\DateTimeImmutable::createFromFormat('!Y-m-d',(string)$row['work_date']);
    if (!$date || $date->format('Y-m-d')!==$row['work_date'] || trim((string)$row['work_description'])==='') throw new LogicException('Registration requires verification');
    foreach (['planned_minutes','registered_minutes'] as $field) {
      if (filter_var($row[$field],FILTER_VALIDATE_INT)===false || (int)$row[$field]<0 || (int)$row[$field]>1440) throw new LogicException('Invalid duration');
    }
    $q=$db->prepare("SELECT id,action,actor_id,reason,created_at FROM inzet_operational_audit WHERE entity_type='registration' AND entity_id=? ORDER BY id LIMIT 3");
    $q->execute([$row['id']]);$history=$q->fetchAll(PDO::FETCH_ASSOC);
    $expected=$row['state']==='submitted'?1:2;
    if (!in_array($row['state'],['submitted','approved','rejected','needs_correction'],true)
      || count($history)!==$expected || (int)$row['revision']!==$expected || ($history[0]['action']??null)!=='submitted') throw new LogicException('Audit lineage requires review');
    $submitter=(string)$history[0]['actor_id'];
    if ($this->person($submitter)!==self::nativeId($row['person_id'])) throw new LogicException('Registrant does not match planned employee');
    if ($expected===2 && ($history[1]['action']!==$row['state'] || trim((string)$history[1]['reason'])===''
      || $history[1]['actor_id']===$submitter || $this->person((string)$history[1]['actor_id'])===self::nativeId($row['person_id']))) {
      throw new LogicException('Decision lineage requires review');
    }
    return ['submitter'=>$submitter,'reason'=>$history[1]['reason']??'','audit_snapshot'=>hash('sha256',json_encode($history,JSON_THROW_ON_ERROR))];
  }
  private static function snapshot(array $row,string $audit): string {
    $data=[];
    foreach (['id','planning_id','project_id','person_id','work_date','planned_minutes','registered_minutes','work_description','state','revision','updated_at','planning_revision','planning_updated_at'] as $field) $data[$field]=(string)$row[$field];
    return hash('sha256',json_encode([$data,$audit],JSON_THROW_ON_ERROR));
  }
  private function employee(int $project,int $person,bool $lock=false): string {
    $q=$this->office->prepare('SELECT p.display_name FROM office_inzet_people p
      INNER JOIN office_inzet_project_team t ON t.person_id=p.id WHERE t.project_id=? AND p.id=? AND p.active=1'.$this->lockSuffixFor($lock));
    $q->execute([$project,$person]);$name=$q->fetchColumn();
    if (!is_string($name)) throw new DomainException('Active project employee required');
    return $name;
  }
  private function lockSuffixFor(bool $lock): string { return $lock?$this->lockSuffix():''; }
  /** @return array{rows:array,projects:array,has_more:bool,page:int,project:?int} */
  public function listForOperator(WebsiteCrmOperatorContext $operator,?int $project=null,int $page=1): array {
    if ($page<1 || $page>10000) throw new InvalidArgumentException('Invalid page');
    $projects=$this->managedProjects($operator);
    $activeReviewer=$this->office->prepare('SELECT id FROM office_inzet_people WHERE id=? AND active=1');
    $activeReviewer->execute([$this->person($operator->operatorId)]);
    if ($activeReviewer->fetchColumn()===false) throw new DomainException('Active reviewer identity required');
    if ($project!==null && !isset($projects[$project])) throw new DomainException('Project not authorized');
    $ids=$project===null?array_keys($projects):[$project];
    $sql='SELECT r.*,p.project_id,p.person_id,p.work_date,p.planned_minutes,p.revision AS planning_revision,p.updated_at AS planning_updated_at FROM inzet_registration r
      INNER JOIN inzet_planning p ON p.id=r.planning_id WHERE p.project_id IN ('.implode(',',array_fill(0,count($ids),'?')).')
      ORDER BY p.work_date DESC,r.id ASC LIMIT '.(self::PAGE_SIZE+1).' OFFSET '.(($page-1)*self::PAGE_SIZE);
    $q=$this->operational->prepare($sql);$q->execute(array_map('strval',$ids));$rows=$q->fetchAll(PDO::FETCH_ASSOC);
    $more=count($rows)>self::PAGE_SIZE;$rows=array_slice($rows,0,self::PAGE_SIZE);
    foreach ($rows as &$row) {
      $row['can_review']=false;$row['verified']=false;$row['blocked']='Registratie of identiteit moet eerst worden gecontroleerd.';
      $row['person_name']='Medewerker';$row['project_name']=$projects[(int)$row['project_id']]??'Project';
      try {
        $person=self::nativeId($row['person_id']);$nativeProject=self::nativeId($row['project_id']);
        $row['person_name']=$this->employee($nativeProject,$person);
        $lineage=$this->lineage($this->operational,$row);$row['verified']=true;$row['decision_reason']=$lineage['reason'];
        $row['snapshot']=self::snapshot($row,$lineage['audit_snapshot']);
        if ($lineage['submitter']===$operator->operatorId || $person===$this->person($operator->operatorId)) {
          $row['blocked']='Eigen registratie: een andere bevoegde gebruiker moet beoordelen.';
        } elseif ($row['state']!=='submitted') { $row['blocked']='Deze beoordeling is al vastgelegd.';
        } else { $row['can_review']=true;$row['blocked']=''; }
      } catch (DomainException|LogicException $unverified) { /* Present blocked data only within the authorized project. */ }
    }
    unset($row);
    return ['rows'=>$rows,'projects'=>$projects,'has_more'=>$more,'page'=>$page,'project'=>$project];
  }
  public function decide(WebsiteCrmOperatorContext $operator,string $id,int $revision,string $state,string $reason,string $expectedSnapshot): void {
    if ($id==='' || strlen($id)>128 || trim($id)!==$id || str_contains($id,"\0") || $revision<1
      || !in_array($state,['approved','rejected','needs_correction'],true)
      || strlen($reason)>8000 || preg_match('/^.{0,2000}$/usD',$reason)!==1 || str_contains($reason,"\0")
      || !preg_match('/^[a-f0-9]{64}$/D',$expectedSnapshot)) throw new InvalidArgumentException('Invalid operational decision');
    $reason=trim($reason);
    if ($state!=='approved' && $reason==='') throw new InvalidArgumentException('Explanation required');
    if ($this->office->inTransaction() || $this->operational->inTransaction()) throw new LogicException('Independent application transactions required');
    $reviewerPerson=$this->person($operator->operatorId);
    // Office row locks retain current session and permission until the SQLite commit.
    // Office itself receives no writes. Its read transaction is released afterwards.
    $this->office->beginTransaction();
    try {
      (new OperationalInzetStore($this->operational))->decide($id,$revision,$state,$operator->operatorId,
        $reason===''?'Uren gecontroleerd':$reason,function(PDO $db) use($operator,$id,$revision,$reason,$reviewerPerson,$expectedSnapshot):void {
          $row=self::registration($db,$id);$project=self::nativeId($row['project_id']);$person=self::nativeId($row['person_id']);
          $acl=$this->office->prepare("SELECT a.id,s.expires_at FROM office_project_memberships m
            INNER JOIN office_projects p ON p.id=m.project_id
            INNER JOIN office_operator_accounts a ON a.id=m.operator_id AND a.active=1
            INNER JOIN office_operator_sessions s ON s.operator_id=a.id AND s.revoked_at IS NULL
            WHERE m.project_id=? AND m.permission='manage' AND a.id=? AND s.token_hash=? AND s.expires_at>?".$this->lockSuffix());
          $acl->execute([$project,$operator->operatorId,$operator->sessionId,gmdate('Y-m-d H:i:s')]);
          $authority=$acl->fetch(PDO::FETCH_ASSOC);
          if (!is_array($authority) || !is_string($authority['id']) || !hash_equals($operator->operatorId,$authority['id'])) throw new DomainException('Current session and project management permission required');
          $this->employee($project,$person,true);
          $active=$this->office->prepare('SELECT id FROM office_inzet_people WHERE id=? AND active=1'.$this->lockSuffix());
          $active->execute([$reviewerPerson]);
          if ($active->fetchColumn()===false) throw new DomainException('Active reviewer identity required');
          $lineage=$this->lineage($db,$row);
          if (!hash_equals($expectedSnapshot,self::snapshot($row,$lineage['audit_snapshot']))) throw new LogicException('Registration or planning changed');
          if ($lineage['submitter']===$operator->operatorId || $reviewerPerson===$person) throw new DomainException('Self review is not allowed');
          if ($row['state']!=='submitted' || (int)$row['revision']!==$revision) throw new LogicException('Registration changed');
          if ((int)$row['planned_minutes']!==(int)$row['registered_minutes'] && $reason==='') throw new InvalidArgumentException('Explain the variance');
          if ((string)$authority['expires_at']<=gmdate('Y-m-d H:i:s')) throw new DomainException('Session expired while awaiting authorization');
        });
    } finally {
      if ($this->office->inTransaction()) $this->office->rollBack();
    }
  }
}
