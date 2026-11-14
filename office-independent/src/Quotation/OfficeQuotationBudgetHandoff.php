<?php
declare(strict_types=1);
namespace Brebo\Office\Quotation;
use PDO;
use DomainException;
use Throwable;

/** Authenticated quotation acceptance and exact budget transfer in one transaction. */
final class OfficeQuotationBudgetHandoff
{
    public function __construct(private readonly PDO $db) {}
    private function lock():string{return $this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';}
    public function transfer(int $quotationId,int $actor,int $expectedVersion,string $expectedCostHash,?int $evidenceId,bool $confirmed):array
    {
        if($quotationId<1||$actor<1||$expectedVersion<1||!preg_match('/^[a-f0-9]{64}$/D',$expectedCostHash)||!$confirmed||$this->db->inTransaction()||$this->db->getAttribute(PDO::ATTR_ERRMODE)!==PDO::ERRMODE_EXCEPTION)throw new DomainException('Verified quotation handoff context required');
        foreach(['OfficeCalcAcceptanceSnapshotWriter','OfficeCalcCrmTransferService','OfficeCalcPersistedAcceptanceVerifier','OfficeCalcAcceptanceIdentity','OfficeCalcWorkBudgetHandoff']as$class)require_once dirname(__DIR__).'/Calculation/'.$class.'.php';
        require_once __DIR__.'/OfficeQuotationBudgetBinding.php';
        $this->db->beginTransaction();
        try{
            $q=$this->db->prepare('SELECT * FROM office_quotations WHERE id=?'.$this->lock());$q->execute([$quotationId]);$quote=$q->fetch(PDO::FETCH_ASSOC);
            if(!$quote||!in_array($quote['status'],['sent','accepted'],true)||(int)$quote['version']!==$expectedVersion||$quote['currency']!=='EUR'||empty($quote['approved_by'])||empty($quote['approved_at']))throw new DomainException('Only the approved, sent quotation revision can be accepted');
            $q=$this->db->prepare('SELECT * FROM office_quotation_budget_bindings WHERE quotation_id=? AND quotation_version=?'.$this->lock());$q->execute([$quotationId,$expectedVersion]);$binding=$q->fetch(PDO::FETCH_ASSOC);
            if(!$binding||!hash_equals($expectedCostHash,(string)$binding['cost_sha256'])||!hash_equals((string)$binding['cost_sha256'],hash('sha256',(string)$binding['cost_json']))||(int)$binding['opportunity_id']!==(int)$quote['opportunity_id']||(int)$binding['draft_id']!==(int)$quote['calculation_id']||(int)$binding['calculation_version']!==(int)$quote['calculation_version'])throw new DomainException('Immutable calculation binding missing or changed');
            OfficeQuotationBudgetBinding::requireActor($this->db,$actor,(int)$binding['draft_id']);
            $q=$this->db->prepare('SELECT * FROM office_crm_opportunities WHERE id=?'.$this->lock());$q->execute([$binding['opportunity_id']]);$opp=$q->fetch(PDO::FETCH_ASSOC);
            if(!$opp||(int)$opp['relation_id']!==(int)$quote['relation_id'])throw new DomainException('Quotation CRM relationship changed');
            $q=$this->db->prepare('SELECT snapshot_json,snapshot_sha256 FROM office_quotation_versions WHERE quotation_id=? AND version=?'.$this->lock());$q->execute([$quotationId,$expectedVersion]);$version=$q->fetch(PDO::FETCH_ASSOC);
            if(!$version||!hash_equals((string)$version['snapshot_sha256'],hash('sha256',(string)$version['snapshot_json'])))throw new DomainException('Quotation source snapshot changed');
            $snapshot=json_decode((string)$version['snapshot_json'],true,512,JSON_THROW_ON_ERROR);
            if(($snapshot['kind']??'')!=='calculation_sales_import'||(int)($snapshot['source']['calculation_id']??0)!==(int)$quote['calculation_id']||(int)($snapshot['source']['calculation_version']??0)!==(int)$quote['calculation_version'])throw new DomainException('Quotation calculation source mismatch');
            $hash=hash('sha256',json_encode(['calculation_id'=>(int)$quote['calculation_id'],'version'=>(int)$quote['calculation_version'],'sales_lines'=>$snapshot['sales_lines']??[]],JSON_THROW_ON_ERROR));
            require_once __DIR__.'/OfficeQuotationCalculationImport.php';
            OfficeQuotationCalculationImport::prepare(['calculation_id'=>(int)$quote['calculation_id'],'version'=>(int)$quote['calculation_version'],'sales_lines'=>$snapshot['sales_lines']??[],'total_ex_vat_cents'=>$snapshot['total_ex_vat_cents']??null]);
            if(!hash_equals((string)$binding['source_hash'],$hash)||!hash_equals((string)($snapshot['source']['source_hash']??''),$hash)||!is_int($snapshot['total_ex_vat_cents']??null)||(string)$snapshot['total_ex_vat_cents']!==(string)$quote['amount_ex_vat_cents'])throw new DomainException('Quotation selling amount or source hash changed');
            $revision='quotation-'.$quotationId.'-v'.$expectedVersion.'-'.$binding['cost_sha256'];
            $cost=json_decode((string)$binding['cost_json'],true,512,JSON_THROW_ON_ERROR);$lines=$cost['lines']??[];
            $acceptance=['crm_opportunity_id'=>(string)$binding['opportunity_id'],'calculation_id'=>(int)$binding['draft_id'],'accepted_revision'=>$revision,'status'=>'accepted'];
            $budget=\Brebo\Office\Calculation\OfficeCalcWorkBudgetHandoff::prepare($acceptance,$lines);
            $q=$this->db->prepare('SELECT * FROM office_commercial_acceptances WHERE opportunity_id=?'.$this->lock());$q->execute([$binding['opportunity_id']]);$commercial=$q->fetch(PDO::FETCH_ASSOC);
            if($quote['status']==='sent'){
                if($commercial||$opp['status']!=='open'||!in_array($opp['stage'],['proposal','negotiation'],true)||!is_int($evidenceId)||$evidenceId<1)throw new DomainException('Acceptance evidence and open quotation-stage opportunity required');
                $this->requireEvidence($evidenceId,$quote);
                $q=$this->db->prepare('INSERT INTO office_commercial_acceptances(opportunity_id,quotation_id,acceptance_kind,evidence_document_id,evidence_reference,accepted_by_operator_id,accepted_at,contract_amount_ex_vat_cents,idempotency_key)VALUES(?,?,?,?,?,?,CURRENT_TIMESTAMP,?,?)');$q->execute([$binding['opportunity_id'],$quotationId,'quotation',$evidenceId,'Offertestudio versie '.$expectedVersion,$actor,(int)$quote['amount_ex_vat_cents'],hash('sha256',$revision)]);
                $q=$this->db->prepare("UPDATE office_quotations SET status='accepted' WHERE id=? AND version=? AND status='sent'");$q->execute([$quotationId,$expectedVersion]);if($q->rowCount()!==1)throw new DomainException('Quotation changed during acceptance');
                $q=$this->db->prepare("UPDATE office_crm_opportunities SET stage='won',status='closed',next_action_at=NULL WHERE id=? AND status='open'");$q->execute([$binding['opportunity_id']]);if($q->rowCount()!==1)throw new DomainException('Opportunity changed during acceptance');
            }else{
                if(!$commercial||$commercial['acceptance_kind']!=='quotation'||(int)$commercial['quotation_id']!==$quotationId||(string)$commercial['contract_amount_ex_vat_cents']!==(string)$quote['amount_ex_vat_cents']||$opp['stage']!=='won'||$opp['status']!=='closed')throw new DomainException('Registered quotation acceptance differs');
                $this->requireEvidence((int)$commercial['evidence_document_id'],$quote);
            }
            $verify=new \Brebo\Office\Calculation\OfficeCalcPersistedAcceptanceVerifier();
            $q=$this->db->prepare('SELECT office_project_id,acceptance_key FROM office_calc_crm_acceptance_transfers WHERE crm_opportunity_id=?'.$this->lock());$q->execute([$acceptance['crm_opportunity_id']]);$prior=$q->fetch(PDO::FETCH_ASSOC);
            if($prior){
                if(!hash_equals(\Brebo\Office\Calculation\OfficeCalcAcceptanceIdentity::key($acceptance),(string)$prior['acceptance_key'])||!$verify($this->db,$acceptance,$lines))throw new DomainException('Existing transfer has another source');
                $q=$this->db->prepare("SELECT 1 FROM office_project_memberships WHERE project_id=? AND operator_id=? AND permission='manage'");$q->execute([$prior['office_project_id'],$actor]);if($q->fetchColumn()===false)throw new DomainException('Project management rights required');
                $this->requireExistingBudget((int)$prior['office_project_id'],$budget['items']);
                $this->db->commit();return ['project_id'=>(int)$prior['office_project_id'],'replayed'=>true];
            }
            $q=$this->db->prepare('SELECT project_id FROM office_calc_crm_drafts WHERE id=?'.$this->lock());$q->execute([$binding['draft_id']]);if($q->fetchColumn()!==null)throw new DomainException('Calculation already assigned to a project');
            $q=$this->db->prepare('SELECT 1 FROM office_crm_calc_acceptances WHERE opportunity_id=?');$q->execute([$binding['opportunity_id']]);
            if($q->fetchColumn()===false){(new \Brebo\Office\Calculation\OfficeCalcAcceptanceSnapshotWriter($this->db))->acceptInTransaction((int)$binding['opportunity_id'],(int)$binding['draft_id'],$revision,$actor,static fn(PDO $db,int $opp,int $calc,string $rev,int $a):array=>['verified'=>true,'calculation_id'=>$calc,'revision'=>$rev,'lines'=>$lines]);}
            if(!$verify($this->db,$acceptance,$lines))throw new DomainException('Canonical acceptance snapshot differs');
            $create=function(PDO $db,array $a)use($quote,$actor,$quotationId,$binding):int{
                $q=$db->prepare('INSERT INTO office_projects(code,title,status,project_kind,description)VALUES(?,?,?,?,?)');$q->execute(['OFF-Q'.$quotationId,(string)$quote['title'],'concept','execution','Offerte #'.$quotationId.' · CRM #'.$a['crm_opportunity_id']]);$id=(int)$db->lastInsertId();
                $q=$db->prepare("INSERT INTO office_project_memberships(project_id,operator_id,permission)VALUES(?,?,'manage')");$q->execute([$id,$actor]);
                $q=$db->prepare('UPDATE office_calc_crm_drafts SET project_id=? WHERE id=? AND project_id IS NULL');$q->execute([$id,$binding['draft_id']]);if($q->rowCount()!==1)throw new DomainException('Calculation project link changed');
                return $id;
            };
            $result=(new \Brebo\Office\Calculation\OfficeCalcCrmTransferService($this->db))->transferInTransaction($acceptance,$lines,$create,$verify);
            if(!$this->db->inTransaction())throw new DomainException('Handoff transaction ended unexpectedly');
            $this->db->commit();return $result+['replayed'=>false];
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    private function requireExistingBudget(int $project,array $items):void
    {
        $q=$this->db->prepare('SELECT b.id,b.locked_at FROM office_calc_crm_acceptance_transfers t JOIN office_calc_work_budget_baselines b ON b.acceptance_transfer_id=t.id JOIN office_projects p ON p.id=t.office_project_id WHERE p.id=? AND b.version_number=1'.$this->lock());$q->execute([$project]);$baseline=$q->fetch(PDO::FETCH_ASSOC);
        if(!$baseline||empty($baseline['locked_at']))throw new DomainException('Existing locked baseline missing');
        $q=$this->db->prepare('SELECT source_line_id,cost_type,quantity,unit_cost_cents,budget_cents,activity_ref FROM office_calc_work_budget_lines WHERE baseline_id=? ORDER BY source_line_id'.$this->lock());$q->execute([$baseline['id']]);$actual=$q->fetchAll(PDO::FETCH_ASSOC);usort($items,static fn(array $a,array $b):int=>strcmp($a['source_line_id'],$b['source_line_id']));
        if(count($items)!==count($actual))throw new DomainException('Existing budget line count differs');
        foreach($items as$i=>$item){$row=$actual[$i];foreach(['source_line_id','cost_type','activity_ref']as$key)if($row[$key]!==$item[$key])throw new DomainException('Existing budget source differs');foreach(['quantity'=>4,'unit_cost_cents'=>2,'budget_cents'=>0]as$key=>$scale)if(!\Brebo\Office\Calculation\OfficeCalcAcceptedLineAmounts::equal($row[$key],$item[$key],$scale))throw new DomainException('Existing budget amount differs');}
    }
    private function requireEvidence(int $documentId,array $quote):void
    {
        if($documentId<1)throw new DomainException('Acceptance document required');
        $q=$this->db->prepare("SELECT d.id,o.sha256,o.byte_size FROM office_documents d JOIN office_document_originals o ON o.id=d.original_id WHERE d.id=? AND d.lifecycle_status='active'".$this->lock());$q->execute([$documentId]);$doc=$q->fetch(PDO::FETCH_ASSOC);
        if(!$doc||(int)$doc['byte_size']<1||!preg_match('/^[a-f0-9]{64}$/D',(string)$doc['sha256']))throw new DomainException('Active immutable evidence document required');
        $q=$this->db->prepare('SELECT context_type,context_id,relation_role FROM office_document_contexts WHERE document_id=?'.$this->lock());$q->execute([$documentId]);$contexts=$q->fetchAll(PDO::FETCH_ASSOC);$linked=false;
        foreach($contexts as$context){if($context['context_type']!=='organization'||(string)$context['context_id']!==(string)$quote['relation_id'])throw new DomainException('Evidence is outside the quotation relation dossier');if(in_array($context['relation_role'],['contractual','correspondence'],true))$linked=true;}
        if(!$linked)throw new DomainException('Link acceptance evidence to the CRM relation as contractual or correspondence');
    }
}
