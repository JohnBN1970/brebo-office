<?php

declare(strict_types=1);

namespace Brebo\Office\Demo;

use PDO;
use RuntimeException;

/**
 * Idempotent first stage of the Office-test acceptance scenario.
 * No project, building, operator or device identity is manufactured.
 * All writes are gated by the CLI runner and made within one transaction.
 */
final class OfficeDemoInspectionSeeder
{
    public function __construct(private PDO $db) {}

    public function seed(bool $apply): array
    {
        $required = [
            'office_projects' => ['id','code','title'],
            'office_buildings' => ['id','code','name'],
            'office_project_buildings' => ['project_id','building_id'],
            'office_operator_accounts' => ['id','active'],
            'office_project_memberships' => ['project_id','operator_id','permission'],
            'office_inspections' => ['id','building_id','project_id','title','inspection_date','created_by'],
            'office_inspection_findings' => ['id','inspection_id','code','observation','assessment_framework','technical_assessment','consequences','remedy'],
        ];
        $q = $this->db->prepare('SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()');
        $q->execute();
        $columns = [];
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $column) $columns[$column['TABLE_NAME']][] = $column['COLUMN_NAME'];
        foreach ($required as $table => $names) {
            if (array_diff($names, $columns[$table] ?? []) !== []) {
                throw new RuntimeException('BLOCKED_SCHEMA '.$table);
            }
        }

        $projectQuery = $this->db->prepare('SELECT id,title FROM office_projects WHERE code=?');
        $projectQuery->execute(['TEST-2026-001']);
        $projects = $projectQuery->fetchAll(PDO::FETCH_ASSOC);
        if (count($projects) !== 1 || (string)$projects[0]['title'] !== 'TEST - Renovatie 232 woningen') {
            throw new RuntimeException('BLOCKED_PROJECT_IDENTITY');
        }
        $projectId = (int)$projects[0]['id'];
        $buildingQuery = $this->db->prepare(
            'SELECT b.id, b.code, b.name FROM office_buildings b
             INNER JOIN office_project_buildings pb ON pb.building_id=b.id
             WHERE pb.project_id=? ORDER BY b.id'
        );
        $buildingQuery->execute([$projectId]);
        $buildings = $buildingQuery->fetchAll(PDO::FETCH_ASSOC);
        if (count($buildings) !== 1) throw new RuntimeException('BLOCKED_BUILDING_SELECTION');
        $buildingId = (int)$buildings[0]['id'];
        $creatorQuery = $this->db->prepare(
            "SELECT a.id FROM office_operator_accounts a
             INNER JOIN office_project_memberships m ON m.operator_id=a.id
             WHERE m.project_id=? AND m.permission='manage' AND a.active=1 ORDER BY a.id LIMIT 2"
        );
        $creatorQuery->execute([$projectId]);
        $creators = $creatorQuery->fetchAll(PDO::FETCH_COLUMN);
        if (count($creators) !== 1) throw new RuntimeException('BLOCKED_CREATOR_SELECTION');
        $creatorId = (int)$creators[0];
        if ($creatorId < 1) throw new RuntimeException('BLOCKED_CREATOR_SELECTION');

        $title = 'DEMO - Bouwkundige opname 232 woningen';
        $existing = $this->db->prepare(
            'SELECT id,building_id FROM office_inspections WHERE project_id=? AND title=?'
        );
        $existing->execute([$projectId,$title]);
        $inspections = $existing->fetchAll(PDO::FETCH_ASSOC);
        if (count($inspections) > 1 || (count($inspections) === 1 && (int)$inspections[0]['building_id'] !== $buildingId)) {
            throw new RuntimeException('BLOCKED_DUPLICATE_INSPECTION');
        }
        $findings = [
            ['DEMO-B01', 'Houtrot aan bestaande kozijnen', 'Inspectie bestaande houten kozijnen', 'Aantasting aan onderdorpels en stijlen', 'Risico op lekkage en verder verval', 'Kozijnen per woning opnemen en aangetaste onderdelen vervangen'],
            ['DEMO-B02', 'Onvoldoende kierdichting bij beglazing', 'Aansluitdetails beglazing en luchtdichtheid', 'Lokale tocht- en vochtdoorslag', 'Warmteverlies en comfortklachten', 'Glas- en kierdichtingen volgens opnamestaat vernieuwen'],
            ['DEMO-B03', 'Gebreken aan aansluitvoegen', 'Buitenaansluitingen gevel en kozijn', 'Verouderde of gescheurde kitvoegen', 'Vochtinfiltratie in aansluitconstructie', 'Voegen verwijderen en duurzaam opnieuw afdichten'],
        ];
        if (!$apply) return ['status'=>'DRY_RUN','project_id'=>$projectId,'building_id'=>$buildingId,'inspection_exists'=>count($inspections)===1,'findings_expected'=>count($findings)];
        $this->db->beginTransaction();
        try {
            if ($inspections === []) {
                $stmt=$this->db->prepare('INSERT INTO office_inspections (building_id,project_id,title,inspection_date,status,created_by) VALUES (?,?,?,?,?,?)');
                $stmt->execute([$buildingId,$projectId,$title,'2026-10-15','draft',$creatorId]);
                $inspectionId=(int)$this->db->lastInsertId();
            } else {
                $inspectionId=(int)$inspections[0]['id'];
            }
            $select=$this->db->prepare('SELECT id,observation FROM office_inspection_findings WHERE inspection_id=? AND code=?');
            $insert=$this->db->prepare(
                'INSERT INTO office_inspection_findings (inspection_id,code,observation,assessment_framework,technical_assessment,consequences,remedy) VALUES (?,?,?,?,?,?,?)'
            );
            $added=0;
            foreach($findings as [$code,$observation,$framework,$assessment,$consequences,$remedy]){
                $select->execute([$inspectionId,$code]);
                $rows=$select->fetchAll(PDO::FETCH_ASSOC);
                if(count($rows)>1 || (count($rows)===1 && (string)$rows[0]['observation']!==$observation)){
                    throw new RuntimeException('BLOCKED_FINDING_COLLISION '.$code);
                }
                if($rows===[]){
                    $insert->execute([$inspectionId,$code,$observation,$framework,$assessment,$consequences,$remedy]);
                    ++$added;
                }
            }
            $this->db->commit();
            return ['status'=>'APPLIED','project_id'=>$projectId,'building_id'=>$buildingId,'inspection_id'=>$inspectionId,'findings_added'=>$added];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
