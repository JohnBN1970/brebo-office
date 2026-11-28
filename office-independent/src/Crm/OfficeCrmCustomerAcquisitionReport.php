<?php
declare(strict_types=1);
namespace Brebo\Office\Crm;
use PDO;

/** Recorded award history only. Caller must authorize company-wide reporting.
 * Coverage is explicit; incomplete legacy history must not be called complete.
 */
final class OfficeCrmCustomerAcquisitionReport {
    public static function forYear(PDO $db,int $year):array {
        if($year<2000||$year>2100)throw new \InvalidArgumentException('Invalid year');
        $q=$db->prepare("SELECT a.relation_kind,COUNT(*) AS customers
            FROM office_crm_award_events a
            WHERE a.awarded_at>=? AND a.awarded_at<?
            AND NOT EXISTS (SELECT 1 FROM office_crm_award_events b
                WHERE b.relation_id=a.relation_id AND
                (b.awarded_at<a.awarded_at OR (b.awarded_at=a.awarded_at AND b.opportunity_id<a.opportunity_id)))
            GROUP BY a.relation_kind");
        $q->execute([sprintf('%04d-01-01',$year),sprintf('%04d-01-01',$year+1)]);
        $counts=['b2b'=>0,'b2c'=>0,'unknown'=>0];
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){
            $kind=match($row['relation_kind']){'organization','company'=>'b2b','person'=>'b2c',default=>'unknown'};
            $counts[$kind]+=(int)$row['customers'];
        }
        $missing=(int)$db->query("SELECT COUNT(*) FROM office_crm_opportunities o
            WHERE o.stage='won' AND o.status IN ('closed','won')
            AND NOT EXISTS (SELECT 1 FROM office_crm_award_events a WHERE a.opportunity_id=o.id AND a.relation_id=o.relation_id)")->fetchColumn();
        return ['first_recorded_awards'=>$counts,'unrecorded_won_deals'=>$missing,
            'complete_for_known_won_deals'=>$missing===0];
    }
}
