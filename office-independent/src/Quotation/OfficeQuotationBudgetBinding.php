<?php
declare(strict_types=1);
namespace Brebo\Office\Quotation;
use PDO;
use DomainException;

/** Immutable private costs captured under native Calc locks during quote generation. */
final class OfficeQuotationBudgetBinding
{
    public function __construct(private readonly PDO $db) {}
    private function lock():string{return $this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';}
    public function bind(int $quoteId,int $draftId,int $actor,int $opportunity,array $prepared,callable $resolveSales):void
    {
        if(!$this->db->inTransaction())throw new DomainException('Binding requires the quotation transaction');
        $q=$this->db->prepare('SELECT * FROM office_quotations WHERE id=?'.$this->lock());$q->execute([$quoteId]);$quote=$q->fetch(PDO::FETCH_ASSOC);
        if(!$quote||$quote['status']!=='draft'||(int)$quote['created_by']!==$actor||(int)$quote['opportunity_id']!==$opportunity||(int)$quote['calculation_id']!==$draftId||(int)$quote['calculation_version']!==$prepared['calculation_version'])throw new DomainException('Quotation calculation context differs');
        $q=$this->db->prepare('SELECT d.*,o.relation_id,o.stage,o.status AS opportunity_status,o.revision FROM office_calc_crm_drafts d JOIN office_crm_opportunities o ON o.id=d.opportunity_id WHERE d.id=?'.$this->lock());$q->execute([$draftId]);$draft=$q->fetch(PDO::FETCH_ASSOC);
        if(!$draft||(int)$draft['opportunity_id']!==$opportunity||(int)$draft['relation_id']!==(int)$quote['relation_id']||$draft['project_id']!==null||$draft['opportunity_status']!=='open'||(int)$draft['crm_revision']!==(int)$draft['revision'])throw new DomainException('Native calculation no longer available');
        self::requireActor($this->db,$actor,$draftId);
        $q=$this->db->prepare('SELECT * FROM calculations WHERE office_calculation_id=? AND office_project_id IS NULL'.$this->lock());$q->execute([$draftId]);$calc=$q->fetch(PDO::FETCH_ASSOC);
        if(!$calc)throw new DomainException('Native calculation identity missing');
        $q=$this->db->prepare('SELECT * FROM calculation_versions WHERE calculation_id=? ORDER BY version_no DESC LIMIT 1'.$this->lock());$q->execute([$calc['id']]);$version=$q->fetch(PDO::FETCH_ASSOC);
        if(!$version||(int)$version['version_no']!==$prepared['calculation_version'])throw new DomainException('Native calculation version changed');
        $q=$this->db->prepare('SELECT * FROM calculation_lines WHERE version_id=? ORDER BY sort_order,id'.$this->lock());$q->execute([$version['id']]);$raw=$q->fetchAll(PDO::FETCH_ASSOC);
        if(!$raw||count($raw)>2000)throw new DomainException('Native calculation lines missing or too large');
        // Legitimate native writers also hold the draft row lock. Re-read sales
        // after taking it so a same-number mutable draft cannot race this binding.
        $current=$resolveSales($actor,$draftId,(int)$version['version_no']);
        require_once __DIR__.'/OfficeQuotationCalculationImport.php';
        if(!is_array($current)||(int)($current['opportunity_id']??0)!==$opportunity||(int)($current['relation_id']??0)!==(int)$quote['relation_id']||!is_array($current['sales_snapshot']??null))throw new DomainException('Authorized sales source missing');
        $sales=OfficeQuotationCalculationImport::prepare($current['sales_snapshot']);
        if(!hash_equals($prepared['source_hash'],$sales['source_hash'])||$sales['total_ex_vat_cents']!==$prepared['total_ex_vat_cents'])throw new DomainException('Sales source changed during quotation generation');
        $columns=['labor'=>'labour_unit_cost','material'=>'material_unit_cost','equipment'=>'equipment_unit_cost','subcontract'=>'subcontracting_unit_cost','other'=>'other_unit_cost'];$lines=[];$total='0';
        foreach($raw as$row){
            $sum='0';foreach($columns as$column)$sum=bcadd($sum,(string)$row[$column],4);
            if(($row['line_type']??'')!=='item'){if(bccomp($sum,'0',4)!==0)throw new DomainException('Optional or structural costs require explicit resolution');continue;}
            $quantity=(string)($row['quantity']??'');
            foreach($columns as$type=>$column){$price=(string)$row[$column];if(bccomp($price,'0',4)===0)continue;$lines[]=['line_id'=>'native-'.$row['id'].'-'.$type,'cost_type'=>$type,'quantity_decimal'=>$quantity,'unit_cost_decimal'=>$price,'activity_ref'=>null];}
            $total=bcadd($total,bcmul($quantity,$sum,8),8);
        }
        require_once dirname(__DIR__).'/Calculation/OfficeCalcWorkBudgetHandoff.php';
        \Brebo\Office\Calculation\OfficeCalcWorkBudgetHandoff::prepare(['crm_opportunity_id'=>(string)$opportunity,'calculation_id'=>$draftId,'accepted_revision'=>'binding-validation','status'=>'accepted'],$lines);
        if(bccomp(bcadd($total,'0.00005',4),(string)$version['direct_cost'],4)!==0)throw new DomainException('Native costs do not reconcile');
        $json=json_encode(['lines'=>$lines,'source_rows'=>$raw,'native_calculation_id'=>(int)$calc['id'],'native_version_id'=>(int)$version['id']],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
        $q=$this->db->prepare('INSERT INTO office_quotation_budget_bindings(quotation_id,quotation_version,opportunity_id,draft_id,calculation_version,source_hash,cost_json,cost_sha256,created_by)VALUES(?,?,?,?,?,?,?,?,?)');
        $q->execute([$quoteId,(int)$quote['version'],$opportunity,$draftId,(int)$version['version_no'],$prepared['source_hash'],$json,hash('sha256',$json),$actor]);
    }
    public static function requireActor(PDO $db,int $actor,int $draft):void
    {
        $q=$db->prepare('SELECT 1 FROM office_operator_accounts a JOIN office_calc_crm_drafts d ON d.id=? WHERE a.id=? AND a.active=1 AND a.can_edit_crm=1 AND(d.created_by=a.id OR EXISTS(SELECT 1 FROM office_calc_crm_draft_collaborators c WHERE c.draft_id=d.id AND c.operator_id=a.id))');$q->execute([$draft,$actor]);
        if($q->fetchColumn()===false)throw new DomainException('Current calculation and CRM rights required');
    }
}
