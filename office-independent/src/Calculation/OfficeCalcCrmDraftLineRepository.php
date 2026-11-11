<?php
declare(strict_types=1);
namespace Brebo\Office\Calculation;

use DomainException;
use PDO;
use Throwable;

/**
 * Single Calc source: calculation_versions + calculation_lines in Office-test.
 * office_calc_crm_drafts owns only CRM identity/rights, never parallel line data.
 * Existing project calculations are not imported or written.
 */
final class OfficeCalcCrmDraftLineRepository
{
    private const TYPES = [
        'labor' => 'L', 'material' => 'M', 'equipment' => 'E',
        'subcontract' => 'S', 'other' => 'O',
    ];
    private const COST_COLUMNS = [
        'labor' => 'labour_unit_cost',
        'material' => 'material_unit_cost',
        'equipment' => 'equipment_unit_cost',
        'subcontract' => 'subcontracting_unit_cost',
        'other' => 'other_unit_cost',
    ];

    public function __construct(private readonly PDO $db) {}

    private function version(int $draftId, bool $create): ?int
    {
        $q=$this->db->prepare('SELECT id,status FROM calculations WHERE office_calculation_id=? AND office_project_id IS NULL');
        $q->execute([$draftId]);
        $calc=$q->fetch(PDO::FETCH_ASSOC);
        if (!is_array($calc)) {
            if (!$create) return null;
            $insert=$this->db->prepare('INSERT INTO calculations(office_project_id,office_calculation_id,code,title,status) VALUES(NULL,?,?,?,?)');
            $insert->execute([$draftId,'CRM-DRAFT-'.$draftId,'CRM calculatie #'.$draftId,'draft']);
            $calc=['id'=>(int)$this->db->lastInsertId(),'status'=>'draft'];
        }
        if ($calc['status']!=='draft') throw new DomainException('Calc is locked');
        $q=$this->db->prepare('SELECT id,status FROM calculation_versions WHERE calculation_id=? ORDER BY version_no DESC LIMIT 1');
        $q->execute([(int)$calc['id']]);
        $row=$q->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            if (!$create) return null;
            $insert=$this->db->prepare('INSERT INTO calculation_versions(calculation_id,version_no,status) VALUES(?,1,?)');
            $insert->execute([(int)$calc['id'],'draft']);
            return (int)$this->db->lastInsertId();
        }
        if ($row['status']!=='draft') throw new DomainException('Calc version is locked');
        return (int)$row['id'];
    }

    public function save(int $draftId,int $actorId,string $lineId,string $costType,
        string $description,int $quantity,int $unitCostCents,?string $activityRef,
        callable $canManageOpportunity): void
    {
        if ($draftId<1 || $actorId<1 || !preg_match('/^[a-zA-Z0-9_-]{1,60}$/D',$lineId)
            || !isset(self::TYPES[$costType]) || trim($description)==='' || mb_strlen($description)>500
            || $quantity<1 || $unitCostCents<0 || $unitCostCents>100000000000
            || $activityRef!==null || $this->db->inTransaction()) {
            throw new DomainException('Invalid canonical Calc line');
        }
        $this->db->beginTransaction();
        try {
            require_once __DIR__.'/OfficeCalcCrmDraftAccess.php';
            // Lock the identity to serialize first Calc creation and line edits.
            $driver=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
            $sql='SELECT id FROM office_calc_crm_drafts WHERE id=?'.
                ($driver==='mysql'?' FOR UPDATE':'');
            $q=$this->db->prepare($sql);
            $q->execute([$draftId]);
            if ($q->fetchColumn()===false
                || !(new OfficeCalcCrmDraftAccess($this->db))->canManage($actorId,$draftId,$canManageOpportunity)) {
                throw new DomainException('CRM Calc access denied');
            }
            $version=$this->version($draftId,true);
            $codes=array_map(static fn(string $prefix):string=>$prefix.':'.$lineId,array_values(self::TYPES));
            $holders=implode(',',array_fill(0,count($codes),'?'));
            $q=$this->db->prepare('SELECT id FROM calculation_lines WHERE version_id=? AND code IN ('.$holders.') LIMIT 1');
            $q->execute(array_merge([$version],$codes));
            $existing=$q->fetchColumn();
            $value=number_format($unitCostCents/100,4,'.','');
            $costs=array_fill_keys(array_values(self::COST_COLUMNS),'0.0000');
            $costs[self::COST_COLUMNS[$costType]]=$value;
            $code=self::TYPES[$costType].':'.$lineId;
            if ($existing!==false) {
                $q=$this->db->prepare('UPDATE calculation_lines SET code=?,description=?,quantity=?,labour_unit_cost=?,material_unit_cost=?,equipment_unit_cost=?,subcontracting_unit_cost=?,other_unit_cost=? WHERE id=? AND version_id=?');
                $q->execute([$code,trim($description),$quantity,
                    $costs['labour_unit_cost'],$costs['material_unit_cost'],
                    $costs['equipment_unit_cost'],$costs['subcontracting_unit_cost'],
                    $costs['other_unit_cost'],(int)$existing,$version]);
            } else {
                $q=$this->db->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM calculation_lines WHERE version_id=?');
                $q->execute([$version]);
                $sort=(int)$q->fetchColumn();
                $q=$this->db->prepare('INSERT INTO calculation_lines(version_id,parent_id,sort_order,line_type,code,description,unit,quantity,labour_unit_cost,material_unit_cost,equipment_unit_cost,subcontracting_unit_cost,other_unit_cost) VALUES(?,NULL,?,?,?,?,NULL,?,?,?,?,?,?)');
                $q->execute([$version,$sort,'item',$code,trim($description),$quantity,
                    $costs['labour_unit_cost'],$costs['material_unit_cost'],
                    $costs['equipment_unit_cost'],$costs['subcontracting_unit_cost'],
                    $costs['other_unit_cost']]);
            }
            $q=$this->db->prepare('SELECT COALESCE(SUM(quantity*(labour_unit_cost+material_unit_cost+equipment_unit_cost+subcontracting_unit_cost+other_unit_cost)),0) FROM calculation_lines WHERE version_id=?');
            $q->execute([$version]);
            $total=$q->fetchColumn();
            $q=$this->db->prepare('UPDATE calculation_versions SET direct_cost=? WHERE id=? AND status=?');
            $q->execute([(string)$total,$version,'draft']);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function list(int $draftId,int $actorId,callable $canManageOpportunity): array
    {
        require_once __DIR__.'/OfficeCalcCrmDraftAccess.php';
        (new OfficeCalcCrmDraftAccess($this->db))->assertCanManage($actorId,$draftId,$canManageOpportunity);
        $version=$this->version($draftId,false);
        if ($version===null) return [];
        $q=$this->db->prepare('SELECT code,description,quantity,labour_unit_cost,material_unit_cost,equipment_unit_cost,subcontracting_unit_cost,other_unit_cost FROM calculation_lines WHERE version_id=? AND line_type=? ORDER BY sort_order,id');
        $q->execute([$version,'item']);
        $results=[];
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $code=(string)$row['code'];
            $prefix=substr($code,0,1);
            $type=array_search($prefix,self::TYPES,true);
            if ($type===false || substr($code,1,1)!==':') continue;
            $results[]=[
                'line_id'=>substr($code,2),
                'cost_type'=>$type,
                'description'=>$row['description'],
                'quantity'=>(int)$row['quantity'],
                'unit_cost_cents'=>(int)round((float)$row[self::COST_COLUMNS[$type]]*100),
                'activity_ref'=>null,
            ];
        }
        return $results;
    }
}
