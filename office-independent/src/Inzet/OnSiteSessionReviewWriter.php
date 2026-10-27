<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;
use PDO;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;

/** Immutable, authenticated Office review ledger. No DDL or financial booking. */
final class OnSiteSessionReviewWriter {
  public function __construct(private PDO $db, private ?\Closure $clock=null) {}

  public function record(string $personId,string $inId,string $outId,string $decision,
    ?int $seconds,string $operatorId,?string $reason=null,?string $projectId=null,
    ?string $requestKey=null): array {
    $uuid='/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/Di';
    if (!preg_match('/^[1-9][0-9]{0,18}$/D',$personId)
      || !is_string($projectId)||!preg_match('/^[1-9][0-9]{0,18}$/D',$projectId)
      || !preg_match($uuid,$inId)||!preg_match($uuid,$outId)||strcasecmp($inId,$outId)===0
      || $operatorId===''||strlen($operatorId)>128
      || !in_array($decision,['approved','rejected'],true)||($seconds!==null&&($seconds<0||$seconds>86400))) {
      throw new InvalidArgumentException('invalid_hours_review');
    }
    $inId=strtolower($inId); $outId=strtolower($outId); $reason=trim($reason??'');
    if (strlen($reason)>2000||($decision==='rejected'&&$reason==='')) throw new InvalidArgumentException('invalid_review_reason');
    $fingerprint=hash('sha256',json_encode([$personId,$projectId,$inId,$outId,$decision,$seconds,$operatorId,$reason],JSON_THROW_ON_ERROR));
    $requestKey??=$fingerprint;
    if (!preg_match('/^[a-f0-9]{64}$/D',$requestKey)) throw new InvalidArgumentException('invalid_review_request_key');
    if ($this->db->inTransaction()) throw new RuntimeException('nested_review_transaction');
    $lock=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
    $this->db->beginTransaction();
    try {
      // Authority is locked before evidence or a retry receipt is returned.
      $auth=$this->db->prepare("SELECT a.id FROM office_operator_accounts a JOIN office_project_memberships m ON m.operator_id=a.id JOIN office_projects p ON p.id=m.project_id WHERE a.id=? AND a.active=1 AND m.project_id=? AND m.permission='manage' LIMIT 1".$lock);
      $auth->execute([$operatorId,$projectId]);
      if ($auth->fetchColumn()===false) throw new RuntimeException('hours_review_access_denied');
      $person=$this->db->prepare('SELECT id FROM office_inzet_people WHERE id=? AND active=1'.$lock);
      $person->execute([$personId]);
      if ($person->fetchColumn()===false) throw new RuntimeException('hours_review_person_unavailable');
      $receipt=$this->db->prepare('SELECT id,request_fingerprint,revision FROM office_onsite_hours_reviews WHERE request_key=?'.$lock);
      $receipt->execute([$requestKey]); $existing=$receipt->fetch(PDO::FETCH_ASSOC);
      if (is_array($existing)) {
        if (!hash_equals($existing['request_fingerprint'],$fingerprint)) throw new RuntimeException('hours_review_request_conflict');
        $this->db->commit(); return ['id'=>(string)$existing['id'],'revision'=>(int)$existing['revision'],'replayed'=>true];
      }
      $query=$this->db->prepare('SELECT event_id,person_id,project_id,zone_id,event_kind,occurred_at_utc FROM office_onsite_event_inbox WHERE event_id IN (?,?) ORDER BY event_id'.$lock);
      $query->execute([$inId,$outId]); $events=[];
      foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) $events[strtolower((string)$row['event_id'])]=$row;
      $start=$events[$inId]??null; $end=$events[$outId]??null;
      if (!$start||!$end||(string)$start['person_id']!==$personId||(string)$end['person_id']!==$personId
        ||(string)$start['project_id']!==$projectId||(string)$end['project_id']!==$projectId
        ||(string)$start['zone_id']!==(string)$end['zone_id']||$start['event_kind']!=='in'||$end['event_kind']!=='out') {
        throw new RuntimeException('hours_review_evidence_mismatch');
      }
      $parse=static function(string $value): int {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?Z?$/D',$value)) throw new RuntimeException('invalid_review_evidence_time');
        $date=new DateTimeImmutable($value,new DateTimeZone('UTC'));
        if ($date->format('Y-m-d\TH:i:s')!==str_replace(' ','T',substr($value,0,19))) throw new RuntimeException('invalid_review_evidence_time');
        return $date->getTimestamp();
      };
      $started=$parse($start['occurred_at_utc']); $ended=$parse($end['occurred_at_utc']);
      $now=$this->clock===null?new DateTimeImmutable('now',new DateTimeZone('UTC')):($this->clock)();
      if (!$now instanceof DateTimeImmutable||$ended<=$started||$ended-$started>86400||$ended>$now->getTimestamp()) throw new RuntimeException('invalid_completed_session');
      // Reject arbitrary IN/OUT combinations, duplicate arrivals and other-zone overlap.
      $window=$this->db->prepare('SELECT event_id FROM office_onsite_event_inbox WHERE person_id=? AND project_id=? AND occurred_at_utc>=? AND occurred_at_utc<=? ORDER BY occurred_at_utc,event_id LIMIT 3'.$lock);
      $window->execute([$personId,$projectId,$start['occurred_at_utc'],$end['occurred_at_utc']]);
      if (count($window->fetchAll(PDO::FETCH_COLUMN))!==2) throw new RuntimeException('ambiguous_review_evidence');
      $prior=$this->db->prepare('SELECT id FROM office_onsite_hours_reviews WHERE in_event_id IN (?,?) OR out_event_id IN (?,?)'.$lock);
      $prior->execute([$inId,$outId,$inId,$outId]);
      if ($prior->fetchColumn()!==false) throw new RuntimeException('hours_session_already_reviewed');
      $result=(new OnSiteHoursReview())->decide(['approval_status'=>'pending','in_event_id'=>$inId,'out_event_id'=>$outId,'duration_seconds'=>$ended-$started],$decision==='approved'?'approve':'reject',$seconds,$reason);
      $stamp=$now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
      $insert=$this->db->prepare('INSERT INTO office_onsite_hours_reviews (person_id,project_id,zone_id,in_event_id,out_event_id,approval_status,reviewed_seconds,reviewer_operator_id,approved_at,reviewed_at,reason,revision,request_key,request_fingerprint) VALUES (?,?,?,?,?,?,?,?,?,?,?,1,?,?)');
      $insert->execute([$personId,$projectId,(string)$start['zone_id'],$inId,$outId,$result['status'],$result['reviewed_seconds'],$operatorId,$decision==='approved'?$stamp:null,$stamp,$reason===''?null:$reason,$requestKey,$fingerprint]);
      $id=$this->db->lastInsertId(); $this->db->commit();
      return ['id'=>(string)$id,'revision'=>1,'replayed'=>false];
    } catch (\Throwable $error) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $error; }
  }
}
