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
        callable $canManageOpportunity,?string $expectedRevision=null,string $reason='Calculatieregel gewijzigd',?string $exactQuantity=null,?string $exactUnitCost=null,?string $unit=null,?array $priceSource=null,?array $quantityAdjustment=null): void
    {
        if ($draftId<1 || $actorId<1 || !preg_match('/^[a-zA-Z0-9_-]{1,60}$/D',$lineId)
            || !isset(self::TYPES[$costType]) || trim($description)==='' || mb_strlen($description)>500
            || $quantity<1 || $unitCostCents<0 || $unitCostCents>100000000000
            || $activityRef!==null || $this->db->inTransaction()) {
            throw new DomainException('Invalid canonical Calc line');
        }
        if($exactQuantity!==null&&(!preg_match('/^[0-9]{1,6}(?:\\.[0-9]{1,4})?$/D',$exactQuantity)||bccomp($exactQuantity,'0',4)<=0))throw new DomainException('Ongeldige hoeveelheid.');
        if($exactUnitCost!==null&&!preg_match('/^[0-9]{1,8}(?:\\.[0-9]{1,4})?$/D',$exactUnitCost))throw new DomainException('Ongeldige eenheidsprijs.');
        if($unit!==null&&(trim($unit)===''||mb_strlen($unit)>32))throw new DomainException('Ongeldige eenheid.');
        $adjustmentEnabled=getenv('OFFICE_CALC_QUANTITY_ADJUSTMENTS_SCHEMA_VERIFIED')==='enabled';
        if($quantityAdjustment!==null){
            if(!$adjustmentEnabled||$exactQuantity===null||$exactUnitCost===null)throw new DomainException('Correctieopslag niet beschikbaar.');
            require_once __DIR__.'/OfficeCalcQuantityAdjustments.php';
            $checked=OfficeCalcQuantityAdjustments::snapshot(
                $exactQuantity,
                $quantityAdjustment['net_quantity']??null,
                $quantityAdjustment['material_loss_percent']??null,
                $quantityAdjustment['extra_labor_hours']??null,
                $costType
            );
            if($checked['net_quantity']===null)throw new DomainException('Nettohoeveelheid ontbreekt.');
        }
        $priceSourcesEnabled=getenv('OFFICE_CALC_DRAFT_MATERIALS_ENABLED')==='enabled';
        if($priceSource!==null){
            if(!$priceSourcesEnabled||$costType!=='material'||($priceSource['price_source_type']??'')!=='article'||$exactUnitCost===null||bccomp((string)($priceSource['source_unit_price']??'-1'),$exactUnitCost,4)!==0||($priceSource['unit']??null)!==$unit)throw new DomainException('Ongeldige gecontroleerde materiaalbron.');
            foreach(['office_source_id'=>128,'source_reference'=>255,'source_supplier'=>255,'source_details'=>65000]as$key=>$max)if(!is_string($priceSource[$key]??null)||mb_strlen($priceSource[$key])>$max)throw new DomainException('Onvolledige materiaalbron.');
            $date=(string)($priceSource['source_price_date']??'');$parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);if(!$parsed||$parsed->format('Y-m-d')!==$date)throw new DomainException('Ongeldige prijsdatum.');
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
            $collaboration=null;$before=null;
            if(getenv('OFFICE_CALC_DRAFT_COLLABORATION_ENABLED')==='enabled'){
                require_once __DIR__.'/OfficeCalcDraftCollaboration.php';
                $collaboration=new OfficeCalcDraftCollaboration($this->db);
                $before=$collaboration->state($draftId);$collaboration->expect($expectedRevision,$before);
            }
            $version=$this->version($draftId,true);
            $codes=array_map(static fn(string $prefix):string=>$prefix.':'.$lineId,array_values(self::TYPES));
            $holders=implode(',',array_fill(0,count($codes),'?'));
            $q=$this->db->prepare('SELECT * FROM calculation_lines WHERE version_id=? AND code IN ('.$holders.') LIMIT 1');
            $q->execute(array_merge([$version],$codes));
            $existingRow=$q->fetch(PDO::FETCH_ASSOC);$existing=$existingRow===false?false:$existingRow['id'];
            // Do not silently invalidate a persisted adjustment when an existing line is edited.
            // Until the correction editor is wired, edits of adjusted rows must be rejected.
            if($adjustmentEnabled&&$existing!==false&&$quantityAdjustment===null){
                $check=$this->db->prepare('SELECT calc_material_loss_percent,calc_extra_labor_hours FROM calculation_lines WHERE id=? AND version_id=?');
                $check->execute([(int)$existing,$version]);
                $adjusted=$check->fetch(PDO::FETCH_ASSOC);
                if(is_array($adjusted)&&($adjusted['calc_material_loss_percent']!==null||$adjusted['calc_extra_labor_hours']!==null)){
                    throw new DomainException('Bewerk eerst de correctie; bestaande gecorrigeerde regel is beveiligd.');
                }
            }
            $value=$exactUnitCost??number_format($unitCostCents/100,4,'.','');
            $storedQuantity=$exactQuantity??$quantity;
            $costs=array_fill_keys(array_values(self::COST_COLUMNS),'0.0000');
            $costs[self::COST_COLUMNS[$costType]]=$value;
            $code=self::TYPES[$costType].':'.$lineId;
            if ($existing!==false) {
                $q=$this->db->prepare('UPDATE calculation_lines SET code=?,description=?,quantity=?,unit=COALESCE(?,unit),labour_unit_cost=?,material_unit_cost=?,equipment_unit_cost=?,subcontracting_unit_cost=?,other_unit_cost=? WHERE id=? AND version_id=?');
                $q->execute([$code,trim($description),$storedQuantity,$unit,
                    $costs['labour_unit_cost'],$costs['material_unit_cost'],
                    $costs['equipment_unit_cost'],$costs['subcontracting_unit_cost'],
                    $costs['other_unit_cost'],(int)$existing,$version]);
            } else {
                $q=$this->db->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM calculation_lines WHERE version_id=?');
                $q->execute([$version]);
                $sort=(int)$q->fetchColumn();
                $q=$this->db->prepare('INSERT INTO calculation_lines(version_id,parent_id,sort_order,line_type,code,description,unit,quantity,labour_unit_cost,material_unit_cost,equipment_unit_cost,subcontracting_unit_cost,other_unit_cost) VALUES(?,NULL,?,?,?,?,?,?,?,?,?,?,?)');
                $q->execute([$version,$sort,'item',$code,trim($description),$unit,$storedQuantity,
                    $costs['labour_unit_cost'],$costs['material_unit_cost'],
                    $costs['equipment_unit_cost'],$costs['subcontracting_unit_cost'],
                    $costs['other_unit_cost']]);
            }
            if($quantityAdjustment!==null){
                $lineRowId=$existing!==false?(int)$existing:(int)$this->db->lastInsertId();
                $q=$this->db->prepare('UPDATE calculation_lines SET calc_net_quantity=?,calc_material_loss_percent=?,calc_extra_labor_hours=?,calc_quantity_source=?,calc_quantity_source_ref=? WHERE id=? AND version_id=?');
                $q->execute([
                    $quantityAdjustment['net_quantity'],
                    $quantityAdjustment['material_loss_percent']??null,
                    $quantityAdjustment['extra_labor_hours']??null,
                    'manual',
                    null,
                    $lineRowId,$version
                ]);
            }
            if($priceSourcesEnabled){
                $lineRowId=$existing!==false?(int)$existing:(int)$this->db->lastInsertId();
                $preserve=false;
                if($priceSource===null&&is_array($existingRow)&&($existingRow['price_source_type']??'manual')==='article'){
                    $sameCode=(string)$existingRow['code']===$code;$samePrice=bccomp((string)$existingRow[self::COST_COLUMNS[$costType]],(string)$value,4)===0;
                    $preserve=$sameCode&&$samePrice&&($unit===null||(string)$existingRow['unit']===$unit);
                }
                if(!$preserve){
                    $source=$priceSource??['price_source_type'=>'manual','office_source_id'=>null,'source_reference'=>null,'source_supplier'=>null,'source_unit_price'=>null,'source_price_date'=>null,'source_document_id'=>null,'source_details'=>null];
                    $q=$this->db->prepare('UPDATE calculation_lines SET price_source_type=?,office_source_id=?,source_reference=?,source_supplier=?,source_unit_price=?,source_price_date=?,source_document_id=?,source_details=? WHERE id=? AND version_id=?');
                    $q->execute([$source['price_source_type'],$source['office_source_id'],$source['source_reference'],$source['source_supplier'],$source['source_unit_price'],$source['source_price_date'],$source['source_document_id'],$source['source_details'],$lineRowId,$version]);
                }
            }
            $q=$this->db->prepare('SELECT COALESCE(SUM(quantity*(labour_unit_cost+material_unit_cost+equipment_unit_cost+subcontracting_unit_cost+other_unit_cost)),0) FROM calculation_lines WHERE version_id=?');
            $q->execute([$version]);
            $total=$q->fetchColumn();
            $q=$this->db->prepare('UPDATE calculation_versions SET direct_cost=? WHERE id=? AND status=?');
            $q->execute([(string)$total,$version,'draft']);
            if($collaboration!==null)$collaboration->record($draftId,$actorId,'line_saved',$reason,$before,$collaboration->state($draftId));
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
        $q=$this->db->prepare('SELECT * FROM calculation_lines WHERE version_id=? AND line_type=? ORDER BY sort_order,id');
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
                'quantity_decimal'=>(string)$row['quantity'],
                'net_quantity_decimal'=>(string)($row['calc_net_quantity']??$row['quantity']),
                'material_loss_percent'=>isset($row['calc_material_loss_percent'])?(string)$row['calc_material_loss_percent']:null,
                'extra_labor_hours'=>isset($row['calc_extra_labor_hours'])?(string)$row['calc_extra_labor_hours']:null,
                'unit_cost_decimal'=>(string)$row[self::COST_COLUMNS[$type]],
                'unit'=>(string)($row['unit']??''),
                'price_source_type'=>(string)($row['price_source_type']??'manual'),
                'source_supplier'=>(string)($row['source_supplier']??''),
                'source_reference'=>(string)($row['source_reference']??''),
                'source_details'=>(string)($row['source_details']??''),
                'unit_cost_cents'=>(int)round((float)$row[self::COST_COLUMNS[$type]]*100),
                'activity_ref'=>null,
            ];
        }
        return $results;
    }
}
