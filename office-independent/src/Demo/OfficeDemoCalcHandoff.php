<?php

declare(strict_types=1);

namespace Brebo\Office\Demo;

use PDO;
use RuntimeException;

/**
 * Read-only canonical handoff contract. No guessed calculation IDs, prices,
 * or writing into a parallel calculation store.
 */
final class OfficeDemoCalcHandoff
{
    public function __construct(private PDO $db) {}

    public function forProject(string $projectCode): array
    {
        if ($projectCode !== 'TEST-2026-001') throw new RuntimeException('BLOCKED_NOT_DEMO_PROJECT');
        $stmt=$this->db->prepare('SELECT id FROM office_projects WHERE code=?');
        $stmt->execute([$projectCode]);
        $projects=$stmt->fetchAll(PDO::FETCH_COLUMN);
        if (count($projects)!==1) throw new RuntimeException('BLOCKED_PROJECT');
        $projectId=(int)$projects[0];
        $stmt=$this->db->prepare('SELECT id,building_id,housing_type_id,calc_calculation_id FROM office_project_calculation_scopes WHERE project_id=?');
        $stmt->execute([$projectId]);$scopes=$stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($scopes)!==1) throw new RuntimeException('BLOCKED_SCOPE');
        $scope=$scopes[0];
        if ((int)$scope['building_id']<1 || (int)$scope['housing_type_id']<1) throw new RuntimeException('BLOCKED_BUILDING_OR_HOUSING');
        $stmt=$this->db->prepare("SELECT line_code,description,unit,quantity_per_dwelling,unit_price_ex_vat FROM office_scope_quantity_lines WHERE scope_id=? AND line_code IN ('DEMO-B01','DEMO-B02','DEMO-B03') ORDER BY line_code");
        $stmt->execute([(int)$scope['id']]); $lines=$stmt->fetchAll(PDO::FETCH_ASSOC);
        if(count($lines)!==3 || array_column($lines,'line_code')!==['DEMO-B01','DEMO-B02','DEMO-B03']) throw new RuntimeException('BLOCKED_QUANTITY_LINES');
        foreach($lines as $line) {
            if((float)$line['quantity_per_dwelling']<=0) throw new RuntimeException('BLOCKED_QUANTITY');
            if((float)$line['unit_price_ex_vat']!==0.0) throw new RuntimeException('BLOCKED_PRICE_NOT_REVIEWED');
        }
        $calcId=$scope['calc_calculation_id'];
        if ($calcId!==null && (!ctype_digit((string)$calcId)|| (int)$calcId<1)) throw new RuntimeException('BLOCKED_CALC_ID');
        return [
            'contract'=>'brebo-office-demo-calc-handoff-v1',
            'project_code'=>$projectCode,
            'project_id'=>$projectId,
            'scope_id'=>(int)$scope['id'],
            'building_id'=>(int)$scope['building_id'],
            'housing_type_id'=>(int)$scope['housing_type_id'],
            'calculation_id'=>$calcId===null?null:(int)$calcId,
            'status'=>$calcId===null?'AWAITING_CANONICAL_CALC_LINK':'LINKED_REFERENCE_UNVERIFIED',
            'quantity_basis'=>'per_dwelling',
            'quantity_source'=>'synthetic_demo_not_measured',
            'price_status'=>'unpriced',
            'lines'=>array_map(static fn(array $line):array=>[
                'source_finding_code'=>(string)$line['line_code'],
                'description'=>(string)$line['description'],
                'unit'=>(string)$line['unit'],
                'quantity_per_dwelling'=>(float)$line['quantity_per_dwelling'],
            ],$lines),
        ];
    }
}
