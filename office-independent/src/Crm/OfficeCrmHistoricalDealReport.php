<?php
declare(strict_types=1);
namespace Brebo\Office\Crm;
use PDO;

/** Read-only report for a caller authorized for company-wide CRM reporting.
 * Cohort is deal creation date, not award date. Current outcomes are not
 * reconstructed historical snapshots. Distinct winning relations are not
 * necessarily newly acquired customers.
 */
final class OfficeCrmHistoricalDealReport {
    public static function forCreationYear(PDO $db,int $year): array {
        if($year<2000||$year>2100)throw new \InvalidArgumentException('Invalid reporting year');
        $q=$db->prepare("SELECT COUNT(*) AS deals,
            COALESCE(SUM(CASE WHEN stage='won' AND status IN ('closed','won') THEN 1 ELSE 0 END),0) AS won,
            COALESCE(SUM(CASE WHEN stage='lost' AND status='closed' THEN 1 ELSE 0 END),0) AS lost,
            COUNT(DISTINCT CASE WHEN stage='won' AND status IN ('closed','won') THEN relation_id END) AS winning_relations
            FROM office_crm_opportunities WHERE created_at>=? AND created_at<?");
        $q->execute([sprintf('%04d-01-01',$year),sprintf('%04d-01-01',$year+1)]);
        $r=$q->fetch(PDO::FETCH_ASSOC);
        if(!$r)throw new \RuntimeException('CRM report unavailable');
        $won=(int)$r['won'];$lost=(int)$r['lost'];
        return ['deals'=>(int)$r['deals'],'won'=>$won,'lost'=>$lost,
            'winning_relations'=>(int)$r['winning_relations'],
            'conversion_percent'=>$won+$lost>0?round(100*$won/($won+$lost),2):null];
    }
}
