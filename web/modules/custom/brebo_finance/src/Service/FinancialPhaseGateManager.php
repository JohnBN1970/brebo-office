<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;
use InvalidArgumentException;
use UnexpectedValueException;

/** Enforces deterministic financial release gates per project phase. */
final class FinancialPhaseGateManager {

  private const GATES = ['procurement_release', 'execution_start', 'billing_release', 'payment_release', 'project_closeout'];

  private const GATE_HIGH_POLICY = [
    'procurement_release' => ['FIN-CONTRACT-OBLIGATION-OVERDUE', 'FIN-CASH-GACCOUNT-SHORTFALL', 'FIN-SCENARIO-CASH-DELAY'],
    'execution_start' => ['FIN-LABOUR-FORECAST-OVERRUN', 'FIN-CONTRACT-OBLIGATION-OVERDUE', 'FIN-CHANGE-COST-NOT-CONTROLLED', 'FIN-CHANGE-EXECUTION-AT-RISK'],
    'billing_release' => ['FIN-CONTRACT-OBLIGATION-OVERDUE', 'FIN-SALES-INVOICE-DISPUTED', 'FIN-BILLABLE-NOT-INVOICED', 'FIN-CHANGE-NOT-INVOICED'],
    'payment_release' => ['FIN-INVOICE-EXCEPTION', 'FIN-INVOICE-OVERDUE', 'FIN-GACCOUNT-EXPIRED', 'FIN-CASH-GACCOUNT-SHORTFALL', 'FIN-CONTRACT-OBLIGATION-OVERDUE', 'FIN-EUROTRACE-INVOICE-ABOVE-COMMITMENT', 'FIN-EUROTRACE-INVOICE-ABOVE-PERFORMANCE', 'FIN-EUROTRACE-NO-PERFORMANCE', 'FIN-EUROTRACE-RELEASE-ABOVE-INVOICE', 'FIN-EUROTRACE-RELEASE-ABOVE-PERFORMANCE', 'FIN-EUROTRACE-EXECUTED-ABOVE-RELEASE', 'FIN-EUROTRACE-INCOMPLETE'],
    'project_closeout' => ['FIN-INVOICE-EXCEPTION', 'FIN-INVOICE-OVERDUE', 'FIN-SALES-INVOICE-DISPUTED', 'FIN-BILLABLE-NOT-INVOICED', 'FIN-CHANGE-NOT-INVOICED', 'FIN-RECEIVABLE-OVERDUE', 'FIN-CONTRACT-OBLIGATION-OVERDUE', 'FIN-LABOUR-FORECAST-OVERRUN', 'FIN-EUROTRACE-INVOICE-ABOVE-COMMITMENT', 'FIN-EUROTRACE-INVOICE-ABOVE-PERFORMANCE', 'FIN-EUROTRACE-NO-PERFORMANCE', 'FIN-EUROTRACE-RELEASE-ABOVE-INVOICE', 'FIN-EUROTRACE-RELEASE-ABOVE-PERFORMANCE', 'FIN-EUROTRACE-EXECUTED-ABOVE-RELEASE', 'FIN-EUROTRACE-INCOMPLETE'],
  ];

  public function __construct(private readonly Connection $database) {}

  public function evaluate(int $projectNid, string $gate): array {
    $this->assertGate($gate);
    $this->ensureStorage();
    $blockers = $this->loadBlockingFindings($projectNid, $gate);
    $exception = $this->loadActiveException($projectNid, $gate);
    $effectiveBlockers = [];
    foreach ($blockers as $blocker) {
      if ($exception === NULL || !$this->exceptionCovers($exception, $blocker)) $effectiveBlockers[] = $blocker;
    }
    return ['project_nid'=>$projectNid,'gate'=>$gate,'released'=>$effectiveBlockers===[],'decision'=>$effectiveBlockers===[]?'released':'blocked','policy'=>['critical_findings'=>'always_block','high_control_codes'=>self::GATE_HIGH_POLICY[$gate]],'blocking_findings'=>array_values($effectiveBlockers),'exception'=>$exception,'ai_override_allowed'=>FALSE,'evaluated_at'=>time()];
  }

  public function requestException(int $projectNid, string $gate, array $findingIds, string $reason, string $controlMeasure, string $expiresAt, array $evidence, int $requesterUid): int {
    $this->assertGate($gate); $this->ensureStorage();
    if ($requesterUid<=0||trim($reason)===''||trim($controlMeasure)===''||$evidence===[]) throw new InvalidArgumentException('Gate exception requires a human requester, reason, control measure and evidence.');
    if (!$this->validFutureDateTime($expiresAt)) throw new InvalidArgumentException('Gate exception requires a future expiry in ISO-8601 format.');
    $findingIds=array_values(array_unique(array_map('intval',$findingIds))); if($findingIds===[]||in_array(0,$findingIds,TRUE)) throw new InvalidArgumentException('Gate exception must name the exact blocking findings.');
    $knownIds=array_map(static fn(array $row):int=>(int)$row['id'],$this->loadBlockingFindings($projectNid,$gate)); foreach($findingIds as $findingId) if(!in_array($findingId,$knownIds,TRUE)) throw new UnexpectedValueException('An exception can only cover an active finding that blocks this exact phase gate.');
    $now=time(); $payload=['finding_ids'=>$findingIds,'reason'=>trim($reason),'control_measure'=>trim($controlMeasure),'expires_at'=>$expiresAt,'evidence'=>$evidence,'gate_policy'=>self::GATE_HIGH_POLICY[$gate]]; $contentHash=$this->hash($payload);
    $id=(int)$this->database->insert('brebo_finance_phase_gate_exception')->fields(['project_nid'=>$projectNid,'gate'=>$gate,'status'=>'requested','finding_ids'=>json_encode($findingIds,JSON_THROW_ON_ERROR),'reason'=>trim($reason),'control_measure'=>trim($controlMeasure),'expires_at'=>(new \DateTimeImmutable($expiresAt))->getTimestamp(),'evidence'=>json_encode($evidence,JSON_THROW_ON_ERROR|JSON_PRESERVE_ZERO_FRACTION),'approval_note'=>NULL,'content_hash'=>$contentHash,'requested_by'=>$requesterUid,'approved_by'=>NULL,'created'=>$now,'changed'=>$now])->execute();
    $this->audit($projectNid,$id,'exception_requested',NULL,$contentHash,$payload,$requesterUid,$now); return $id;
  }

  public function decideException(int $exceptionId,string $decision,string $note,int $approverUid):void {
    $this->ensureStorage(); if(!in_array($decision,['approved','rejected'],TRUE)||$approverUid<=0||trim($note)==='') throw new InvalidArgumentException('Exception decision requires approved/rejected, a human approver and note.');
    $exception=$this->loadException($exceptionId); if($exception['status']!=='requested') throw new UnexpectedValueException('Only a requested gate exception can be decided.'); if((int)$exception['requested_by']===$approverUid) throw new UnexpectedValueException('A requester cannot approve their own gate exception.'); if((int)$exception['expires_at']<=time()) throw new UnexpectedValueException('An expired gate exception cannot be approved.');
    $beforeHash=(string)$exception['content_hash']; $now=time(); $payload=['decision'=>$decision,'note'=>trim($note),'previous_content_hash'=>$beforeHash]; $afterHash=$this->hash($payload+['exception_id'=>$exceptionId]);
    $this->database->update('brebo_finance_phase_gate_exception')->fields(['status'=>$decision,'approval_note'=>trim($note),'approved_by'=>$approverUid,'content_hash'=>$afterHash,'changed'=>$now])->condition('id',$exceptionId)->execute(); $this->audit((int)$exception['project_nid'],$exceptionId,'exception_'.$decision,$beforeHash,$afterHash,$payload,$approverUid,$now);
  }

  public function exceptionMetadata(int $exceptionId):array { $this->ensureStorage(); $e=$this->loadException($exceptionId); $ids=json_decode((string)$e['finding_ids'],TRUE,512,JSON_THROW_ON_ERROR); return ['id'=>(int)$e['id'],'project_nid'=>(int)$e['project_nid'],'gate'=>(string)$e['gate'],'status'=>(string)$e['status'],'finding_ids'=>array_values(array_map('intval',is_array($ids)?$ids:[])),'requested_by'=>(int)$e['requested_by'],'expires_at'=>(int)$e['expires_at']]; }
  public function requireRelease(int $projectNid,string $gate):void { $d=$this->evaluate($projectNid,$gate); if(!$d['released']){ $codes=array_values(array_unique(array_map(static fn(array $r):string=>(string)$r['control_code'],$d['blocking_findings']))); throw new UnexpectedValueException(sprintf('Financial phase gate %s is blocked for project %d by: %s.',$gate,$projectNid,implode(', ',$codes))); } }

  private function ensureStorage():void { $s=$this->database->schema(); if($s->tableExists('brebo_finance_phase_gate_exception'))return; $s->createTable('brebo_finance_phase_gate_exception',['description'=>'Temporary four-eyes exceptions for deterministic financial phase gates.','fields'=>['id'=>['type'=>'serial','not null'=>TRUE],'project_nid'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],'gate'=>['type'=>'varchar','length'=>32,'not null'=>TRUE],'status'=>['type'=>'varchar','length'=>24,'not null'=>TRUE,'default'=>'requested'],'finding_ids'=>['type'=>'text','size'=>'big','not null'=>TRUE],'reason'=>['type'=>'text','size'=>'big','not null'=>TRUE],'control_measure'=>['type'=>'text','size'=>'big','not null'=>TRUE],'expires_at'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],'evidence'=>['type'=>'text','size'=>'big','not null'=>TRUE],'approval_note'=>['type'=>'text','size'=>'big','not null'=>FALSE],'content_hash'=>['type'=>'varchar','length'=>64,'not null'=>TRUE],'requested_by'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],'approved_by'=>['type'=>'int','unsigned'=>TRUE,'not null'=>FALSE],'created'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],'changed'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE]],'primary key'=>['id'],'indexes'=>['project_gate_status'=>['project_nid','gate','status'],'expires_at'=>['expires_at'],'content_hash'=>['content_hash']]]); }
  private function loadBlockingFindings(int $projectNid,string $gate):array { $q=$this->database->select('brebo_finance_control_finding','f')->fields('f')->condition('project_nid',$projectNid)->condition('status',['resolved_verified','resolved_automatically'],'NOT IN'); $or=$q->orConditionGroup()->condition('severity','critical'); $or->condition($q->andConditionGroup()->condition('severity','high')->condition('control_code',self::GATE_HIGH_POLICY[$gate],'IN')); $q->condition($or); return $q->execute()->fetchAll(\PDO::FETCH_ASSOC); }
  private function loadActiveException(int $projectNid,string $gate):?array { $r=$this->database->select('brebo_finance_phase_gate_exception','e')->fields('e')->condition('project_nid',$projectNid)->condition('gate',$gate)->condition('status','approved')->condition('expires_at',time(),'>')->orderBy('changed','DESC')->range(0,1)->execute()->fetchAssoc(); return $r===FALSE?NULL:$r; }
  private function exceptionCovers(array $e,array $f):bool{$ids=json_decode((string)$e['finding_ids'],TRUE,512,JSON_THROW_ON_ERROR);return in_array((int)$f['id'],array_map('intval',$ids),TRUE);} private function loadException(int $id):array{$r=$this->database->select('brebo_finance_phase_gate_exception','e')->fields('e')->condition('id',$id)->execute()->fetchAssoc();if($r===FALSE)throw new UnexpectedValueException('Financial phase gate exception does not exist.');return $r;} private function assertGate(string $g):void{if(!in_array($g,self::GATES,TRUE))throw new InvalidArgumentException('Unknown financial phase gate.');} private function validFutureDateTime(string $v):bool{try{return(new \DateTimeImmutable($v))->getTimestamp()>time();}catch(\Exception){return FALSE;}} private function hash(array $p):string{ksort($p);return hash('sha256',json_encode($p,JSON_THROW_ON_ERROR|JSON_PRESERVE_ZERO_FRACTION));}
  private function audit(int $projectNid,int $exceptionId,string $action,?string $beforeHash,string $afterHash,array $payload,int $actorUid,int $now):void{$this->database->insert('brebo_finance_audit')->fields(['project_nid'=>$projectNid,'entity_type'=>'phase_gate_exception','entity_id'=>$exceptionId,'action'=>$action,'before_hash'=>$beforeHash,'after_hash'=>$afterHash,'payload'=>json_encode($payload,JSON_THROW_ON_ERROR|JSON_PRESERVE_ZERO_FRACTION),'reason'=>$payload['note']??$payload['reason']??NULL,'created'=>$now,'created_by'=>$actorUid])->execute();}
}
