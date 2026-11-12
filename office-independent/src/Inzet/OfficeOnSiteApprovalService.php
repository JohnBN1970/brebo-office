<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use DateTimeImmutable;
use DateTimeZone;
use PDO;

require_once __DIR__ . '/OnSiteHoursReview.php';
require_once __DIR__ . '/OnSitePresenceIntervalProjector.php';
require_once __DIR__ . '/OfficeApprovedHoursDecisionRepository.php';
require_once __DIR__ . '/OfficeApprovedHoursEvidence.php';
require_once __DIR__ . '/OfficeApprovedHoursBooking.php';

/** Verify immutable inbox events and persist a manager decision and its booking atomically. */
final class OfficeOnSiteApprovalService
{
    public function __construct(private PDO $db) {}

    public function approve(int $projectId, string $inEventId, string $outEventId, string $reviewer): int
    {
        if ($projectId < 1 || $reviewer === '' || strlen($reviewer) > 128
            || $inEventId === '' || strlen($inEventId) > 100
            || $outEventId === '' || strlen($outEventId) > 100 || $inEventId === $outEventId) {
            throw new \InvalidArgumentException('Invalid OnSite approval request');
        }
        $access = $this->db->prepare("SELECT 1 FROM office_project_memberships m
            JOIN office_operator_accounts a ON a.id=m.operator_id
            WHERE m.project_id=? AND m.operator_id=? AND m.permission='manage' AND a.active=1 LIMIT 1");
        $access->execute([$projectId,$reviewer]);
        if (!$access->fetchColumn()) throw new \DomainException('Not authorized');
        $query=$this->db->prepare("SELECT event_id,person_id,project_id,zone_id,event_kind,occurred_at_utc
            FROM office_onsite_event_inbox WHERE project_id=? AND event_id IN (?,?)");
        $query->execute([$projectId,$inEventId,$outEventId]);
        $events=[];
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $event) $events[$event['event_id']]=$event;
        if (count($events)!==2 || ($events[$inEventId]['event_kind']??null)!=='in'
            || ($events[$outEventId]['event_kind']??null)!=='out'
            || $events[$inEventId]['person_id']!==$events[$outEventId]['person_id']
            || $events[$inEventId]['zone_id']!==$events[$outEventId]['zone_id']) {
            throw new \DomainException('OnSite event pair invalid');
        }
        $in=new DateTimeImmutable($events[$inEventId]['occurred_at_utc'],new DateTimeZone('UTC'));
        $out=new DateTimeImmutable($events[$outEventId]['occurred_at_utc'],new DateTimeZone('UTC'));
        $seconds=$out->getTimestamp()-$in->getTimestamp();
        // Reconstruct the complete OnSite sequence; arbitrary event pairs cannot be approved.
        $history=$this->db->prepare("SELECT event_id,person_id,project_id,zone_id,event_kind,occurred_at_utc
            FROM office_onsite_event_inbox WHERE project_id=? AND person_id=?
            ORDER BY occurred_at_utc DESC,event_id DESC LIMIT 501");
        $history->execute([$projectId,$personIdForHistory=$events[$inEventId]['person_id']]);
        $historyEvents=$history->fetchAll(PDO::FETCH_ASSOC);
        if (count($historyEvents)>500) throw new \DomainException('OnSite history window incomplete');
        $projected=(new OnSitePresenceIntervalProjector())->project($historyEvents);
        $matched=false;
        foreach ($projected['intervals'] as $interval) {
            if ($interval['arrival_event_id']===$inEventId && $interval['departure_event_id']===$outEventId
                && (int)$interval['elapsed_seconds']===$seconds) {
                $matched=true;
                break;
            }
        }
        if (!$matched) throw new \DomainException('OnSite event pair is not a verified interval');
        $decision=(new OnSiteHoursReview())->decide([
            'approval_status'=>'pending','in_event_id'=>$inEventId,'out_event_id'=>$outEventId,
            'duration_seconds'=>$seconds
        ],'approve');
        $reference=hash('sha256',$inEventId.'|'.$outEventId);
        $personId=filter_var($events[$inEventId]['person_id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if (!is_int($personId)) throw new \DomainException('Unmapped OnSite person');
        $this->db->beginTransaction();
        try {
        $insert=$this->db->prepare("INSERT INTO office_onsite_hours_decisions
            (decision_reference,in_event_id,out_event_id,project_id,person_id,work_date,status,
             reviewed_seconds,reviewer_id,decided_at)
            VALUES (?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP())");
        $insert->execute([$reference,$inEventId,$outEventId,$projectId,$personId,
            $in->format('Y-m-d'),$decision['status'],$decision['reviewed_seconds'],$reviewer]);
        $verified=(new OfficeApprovedHoursDecisionRepository($this->db))->verified($reference,$reviewer);
        $entry=OfficeApprovedHoursEvidence::fromVerifiedDecision($verified['session'],$verified['decision'],$reviewer);
        $bookingId=(new OfficeApprovedHoursBooking($this->db))->append($entry,$reviewer);
        $this->db->commit();
        return $bookingId;
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }
}
