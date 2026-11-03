<?php

declare(strict_types=1);

namespace Brebo\Office\Demo;

use PDO;
use RuntimeException;

/**
 * Prepares exactly three test-only source quantity lines under one EXISTING
 * canonical calculation scope. Never constructs a fake calculation ID.
 * Existing line prices and quantities are never changed.
 */
final class OfficeDemoQuantitySeeder
{
    public function __construct(private PDO $db) {}

    public function run(bool $apply): array
    {
        $required = [
            'office_projects'=>['id','code'],
            'office_inspections'=>['id','project_id','title'],
            'office_inspection_findings'=>['id','inspection_id','code'],
            'office_project_calculation_scopes'=>['id','project_id','building_id','housing_type_id','scope_kind','scope_label','calc_calculation_id'],
            'office_scope_quantity_lines'=>['id','scope_id','line_code','description','unit','quantity_per_dwelling','unit_price_ex_vat'],
        ];
        $stmt=$this->db->query('SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()');
        $schema=[];
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $schema[$row['TABLE_NAME']][]=$row['COLUMN_NAME'];
        foreach($required as $table=>$columns) {
            if(array_diff($columns,$schema[$table]??[])!==[]) throw new RuntimeException('BLOCKED_SCHEMA '.$table);
        }
        $q=$this->db->prepare('SELECT id FROM office_projects WHERE code=?');
        $q->execute(['TEST-2026-001']); $projects=$q->fetchAll(PDO::FETCH_COLUMN);
        if(count($projects)!==1) throw new RuntimeException('BLOCKED_PROJECT');
        $projectId=(int)$projects[0];
        $q=$this->db->prepare('SELECT id FROM office_inspections WHERE project_id=? AND title=?');
        $q->execute([$projectId,'DEMO - Bouwkundige opname 232 woningen']);
        $inspections=$q->fetchAll(PDO::FETCH_COLUMN);
        if(count($inspections)!==1) throw new RuntimeException('BLOCKED_INSPECTION');
        $inspectionId=(int)$inspections[0];
        $q=$this->db->prepare("SELECT code,COUNT(*) n FROM office_inspection_findings WHERE inspection_id=? AND code IN ('DEMO-B01','DEMO-B02','DEMO-B03') GROUP BY code");
        $q->execute([$inspectionId]); $found=[];
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r) $found[$r['code']]=(int)$r['n'];
        if(count($found)!==3 || array_sum($found)!==3) throw new RuntimeException('BLOCKED_FINDINGS');
        $q=$this->db->prepare('SELECT id,building_id,housing_type_id,calc_calculation_id FROM office_project_calculation_scopes WHERE project_id=? ORDER BY id');
        $q->execute([$projectId]);$scopes=$q->fetchAll(PDO::FETCH_ASSOC);
        if(count($scopes)!==1) throw new RuntimeException('BLOCKED_SCOPE_SELECTION');
        $scope=$scopes[0];
        if(!empty($scope['calc_calculation_id']) && !ctype_digit((string)$scope['calc_calculation_id'])) throw new RuntimeException('BLOCKED_CALC_LINK');
        $scopeId=(int)$scope['id'];
        if($scopeId<1) throw new RuntimeException('BLOCKED_SCOPE');
        // These quantities are synthetic demonstration assumptions, not measured data.
        // Prices are intentionally zero; they require approved cost-source valuation.
        $lines=[
            ['DEMO-B01','DEMO | Kozijninspectie en plaatselijk houtherstel','st', '232.0000','0.00'],
            ['DEMO-B02','DEMO | Beglazing en kierdichting herstellen','st','232.0000','0.00'],
            ['DEMO-B03','DEMO | Aansluitvoegen kozijn-gevel vernieuwen','st','232.0000','0.00'],
        ];
        $select=$this->db->prepare('SELECT id,description,unit,quantity_per_dwelling,unit_price_ex_vat FROM office_scope_quantity_lines WHERE scope_id=? AND line_code=?');
        $pending=[];
        foreach($lines as [$code,$description,$unit,$qty,$price]) {
            $select->execute([$scopeId,$code]);$rows=$select->fetchAll(PDO::FETCH_ASSOC);
            if(count($rows)>1) throw new RuntimeException('BLOCKED_DUPLICATE_LINE '.$code);
            if(count($rows)===1) {
                $r=$rows[0];
                if((string)$r['description']!==$description || (string)$r['unit']!==$unit
                    || abs((float)$r['quantity_per_dwelling']-(float)$qty)>0.00001
                    || abs((float)$r['unit_price_ex_vat']-(float)$price)>0.00001) {
                    throw new RuntimeException('BLOCKED_LINE_COLLISION '.$code);
                }
            } else {
                $pending[]=[$scopeId,$code,$description,$unit,$qty,$price];
            }
        }
        $result=['project_id'=>$projectId,'inspection_id'=>$inspectionId,'scope_id'=>$scopeId,
            'calc_linked'=>!empty($scope['calc_calculation_id'])?'yes':'no',
            'already_present'=>count($lines)-count($pending),'pending'=>count($pending)];
        if(!$apply) return ['status'=>'DRY_RUN']+$result;
        $this->db->beginTransaction();
        try {
            $insert=$this->db->prepare('INSERT INTO office_scope_quantity_lines (scope_id,line_code,description,unit,quantity_per_dwelling,unit_price_ex_vat) VALUES (?,?,?,?,?,?)');
            foreach($pending as $args) $insert->execute($args);
            $this->db->commit();
        } catch(\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        return ['status'=>'APPLIED']+$result;
    }
}
