<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;
use PDO;
/** Bounded project workbench: only a current active manager can read evidence. */
final class OnSiteSessionReviewReader {
  public function __construct(private PDO $db) {}
  public function forManager(string $operatorId,string $projectId): array {
    if ($operatorId===''||strlen($operatorId)>128||!preg_match('/^[1-9][0-9]{0,18}$/D',$projectId)) throw new \InvalidArgumentException('invalid_hours_review_context');
    $auth=$this->db->prepare("SELECT a.id FROM office_operator_accounts a JOIN office_project_memberships m ON m.operator_id=a.id WHERE a.id=? AND a.active=1 AND m.project_id=? AND m.permission='manage' LIMIT 1");
    $auth->execute([$operatorId,$projectId]);
    if($auth->fetchColumn()===false) throw new \RuntimeException('hours_review_access_denied');
    $q=$this->db->prepare('SELECT e.event_id,e.person_id,e.project_id,e.zone_id,e.event_kind,e.occurred_at_utc,p.display_name FROM office_onsite_event_inbox e JOIN office_inzet_people p ON p.id=e.person_id WHERE e.project_id=? ORDER BY e.occurred_at_utc,e.event_id LIMIT 1001');
    $q->execute([$projectId]); $events=$q->fetchAll(PDO::FETCH_ASSOC);
    if(count($events)>1000) throw new \RuntimeException('hours_review_window_incomplete');
    $names=[]; foreach($events as $e)$names[(string)$e['person_id']]=$e['display_name'];
    $result=(new OnSitePresenceIntervalProjector())->project($events);
    $ambiguous=[]; foreach($result['exceptions'] as $exception)foreach(['event_id','open_event_id'] as $key)if(isset($exception[$key]))$ambiguous[$exception[$key]]=true;
    $sessions=[];
    foreach($result['intervals'] as $interval) {
      if(isset($ambiguous[$interval['arrival_event_id']])||$interval['elapsed_seconds']>86400)continue;
      $existing=$this->db->prepare('SELECT approval_status,reviewed_seconds,reviewer_operator_id,reviewed_at,reason,revision FROM office_onsite_hours_reviews WHERE in_event_id=? AND out_event_id=?');
      $existing->execute([$interval['arrival_event_id'],$interval['departure_event_id']]); $review=$existing->fetch(PDO::FETCH_ASSOC);
      $sessions[]=$interval+['display_name'=>$names[$interval['person_id']]??$interval['person_id'],'in_event_id'=>$interval['arrival_event_id'],'out_event_id'=>$interval['departure_event_id'],'duration_seconds'=>$interval['elapsed_seconds']]+($review?:['approval_status'=>'pending','revision'=>0]);
    }
    return ['sessions'=>$sessions,'active'=>$result['active'],'exceptions'=>$result['exceptions']];
  }
}
