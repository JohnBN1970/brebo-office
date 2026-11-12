<?php
declare(strict_types=1);
namespace Brebo\Office\Mail;

use PDO;

/** Read-only suggestions from a mail subject; only project-member-visible buildings/projects. */
final class OfficeMailBuildingProjectRecognition
{
    public function __construct(private readonly PDO $db) {}

    /** @return array{buildings:list<array<string,mixed>>,projects:list<array<string,mixed>>} */
    public function forSubject(string $subject, string $operatorId): array
    {
        $empty=['buildings'=>[],'projects'=>[]];
        if ($operatorId==='' || strlen($operatorId)>128 || strlen($subject)>20000) return $empty;
        // Only suggest when an explicit Dutch postcode is present; no speculative name matching.
        if (!preg_match_all('/(?<![A-Za-z0-9])([1-9][0-9]{3})\s*([A-Za-z]{2})(?![A-Za-z0-9])/', $subject, $matches, PREG_SET_ORDER)) return $empty;
        $postcodes=array_values(array_unique(array_map(static fn(array $m):string=>strtoupper($m[1].$m[2]),$matches)));
        // Too many distinct addresses to propose a safe automatic dossier match.
        if (count($postcodes)>10) return $empty;
        $placeholders=implode(',',array_fill(0,count($postcodes),'?'));
        $stmt=$this->db->prepare("SELECT DISTINCT b.id,b.name AS building_name,b.address_line AS street_address,b.postal_code,b.city
            FROM office_buildings b
            INNER JOIN office_project_buildings pb ON pb.building_id=b.id
            INNER JOIN office_project_memberships m ON m.project_id=pb.project_id
            INNER JOIN office_operator_accounts a ON a.id=m.operator_id AND a.active=1
            WHERE m.operator_id=? AND m.permission IN ('read','manage')
              AND UPPER(REPLACE(b.postal_code,' ','')) IN ($placeholders)
            ORDER BY b.id DESC LIMIT 20");
        $stmt->execute(array_merge([$operatorId],$postcodes));
        $buildings=$stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($buildings===[]) return $empty;
        $ids=array_map(static fn(array $r):int=>(int)$r['id'],$buildings);
        $holders=implode(',',array_fill(0,count($ids),'?'));
        $projects=$this->db->prepare("SELECT DISTINCT pb.project_id,pb.building_id
            FROM office_project_buildings pb
            INNER JOIN office_project_memberships m ON m.project_id=pb.project_id
            INNER JOIN office_operator_accounts a ON a.id=m.operator_id AND a.active=1
            WHERE m.operator_id=? AND m.permission IN ('read','manage')
              AND pb.building_id IN ($holders)
            ORDER BY pb.project_id DESC LIMIT 50");
        $projects->execute(array_merge([$operatorId],$ids));
        return ['buildings'=>$buildings,'projects'=>$projects->fetchAll(PDO::FETCH_ASSOC) ?: []];
    }
}
