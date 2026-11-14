<?php
declare(strict_types=1);
namespace Brebo\Office\Calculation;
use PDO;
use DomainException;

final class OfficeCalcDraftConflict extends DomainException {}

/** Native CRM-draft collaboration adapter. Financial rows remain in the existing Calc tables. */
final class OfficeCalcDraftCollaboration
{
    public function __construct(private readonly PDO $db) {}
    public function canEdit(int $actor,int $draft):bool
    {
        $q=$this->db->prepare('SELECT 1 FROM office_calc_crm_drafts d JOIN office_operator_accounts a ON a.id=? AND a.active=1 AND a.can_edit_crm=1 WHERE d.id=? AND (d.created_by=? OR EXISTS(SELECT 1 FROM office_calc_crm_draft_collaborators c WHERE c.draft_id=d.id AND c.operator_id=?))');
        $q->execute([$actor,$draft,$actor,$actor]);return $q->fetchColumn()!==false;
    }
    public function owns(int $actor,int $draft):bool
    {
        $q=$this->db->prepare('SELECT 1 FROM office_calc_crm_drafts WHERE id=? AND created_by=?');$q->execute([$draft,$actor]);return $q->fetchColumn()!==false;
    }
    /** Raw canonical state is also the audit evidence, never a second editable calculation. */
    public function state(int $draft):array
    {
        $q=$this->db->prepare('SELECT id,status FROM calculations WHERE office_calculation_id=? AND office_project_id IS NULL');$q->execute([$draft]);$calculation=$q->fetch(PDO::FETCH_ASSOC)?:null;
        $version=null;$lines=[];
        if($calculation!==null){$q=$this->db->prepare('SELECT * FROM calculation_versions WHERE calculation_id=? ORDER BY version_no DESC LIMIT 1');$q->execute([$calculation['id']]);$version=$q->fetch(PDO::FETCH_ASSOC)?:null;}
        if($version!==null){$q=$this->db->prepare('SELECT * FROM calculation_lines WHERE version_id=? ORDER BY sort_order,id');$q->execute([$version['id']]);$lines=$q->fetchAll(PDO::FETCH_ASSOC);}
        if(count($lines)>10000)throw new DomainException('Deze calculatie vraagt de volledige werkbank voor revisiebeheer.');
        $q=$this->db->prepare('SELECT COALESCE(MAX(id),0) FROM office_calc_crm_draft_changes WHERE draft_id=?');$q->execute([$draft]);$sequence=(int)$q->fetchColumn();
        return ['calculation'=>$calculation,'version'=>$version,'lines'=>$lines,'sequence'=>$sequence];
    }
    public function token(array $state):string{return hash('sha256',json_encode($state,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));}
    public function expect(?string $expected,array $state):void
    {
        if(!is_string($expected)||!preg_match('/^[a-f0-9]{64}$/D',$expected)||!hash_equals($this->token($state),$expected))throw new OfficeCalcDraftConflict('Een andere bevoegde gebruiker heeft de calculatie gewijzigd. Je invoer blijft hieronder staan. Vergelijk deze met de actuele regels voordat je opnieuw opslaat.');
    }
    /** Caller owns the draft identity row lock and transaction. Audit failure must roll back the edit. */
    public function record(int $draft,int $actor,string $kind,string $reason,array $before,array $after):void
    {
        if(!$this->db->inTransaction()||!in_array($kind,['line_saved','collaborator_granted','collaborator_removed','revision_saved'],true)||$actor<1)throw new DomainException('Ongeldige wijzigingsregistratie.');
        $reason=trim($reason);if($reason===''||mb_strlen($reason)>1000)throw new DomainException('Geef een wijzigingsreden van maximaal 1000 tekens.');
        $old=json_encode($before,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);$new=json_encode($after,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
        if(strlen($old)>8000000||strlen($new)>8000000)throw new DomainException('Revisie is te groot voor deze werkbank.');
        $q=$this->db->prepare('INSERT INTO office_calc_crm_draft_changes(draft_id,actor_id,event_kind,reason,before_json,after_json,content_hash,created_at)VALUES(?,?,?,?,?,?,?,?)');$q->execute([$draft,$actor,$kind,$reason,$old,$new,hash('sha256',$new),gmdate('Y-m-d H:i:s')]);
    }
    private function locked(int $draft,int $actor,callable $rights,callable $operation):mixed
    {
        if($this->db->inTransaction())throw new DomainException('Een eigen calculatietransactie is vereist.');
        $this->db->beginTransaction();
        try{
            $q=$this->db->prepare('SELECT id FROM office_calc_crm_drafts WHERE id=?'.($this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''));$q->execute([$draft]);
            require_once __DIR__.'/OfficeCalcCrmDraftAccess.php';
            if($q->fetchColumn()===false)throw new DomainException('Calculatie niet beschikbaar.');
            (new OfficeCalcCrmDraftAccess($this->db))->assertCanManage($actor,$draft,$rights);
            $result=$operation();$this->db->commit();return $result;
        }catch(\Throwable $error){if($this->db->inTransaction())$this->db->rollBack();throw $error;}
    }
    public function read(int $draft,int $actor,callable $rights,OfficeCalcCrmDraftLineRepository $repo):array
    {
        return $this->locked($draft,$actor,$rights,function()use($draft,$actor,$rights,$repo):array{$lines=$repo->list($draft,$actor,$rights);$state=$this->state($draft);return ['lines'=>$lines,'token'=>$this->token($state),'version_no'=>(int)($state['version']['version_no']??1),'direct_cost'=>(string)($state['version']['direct_cost']??'0')];});
    }
    public function grant(int $draft,int $actor,int $target,bool $remove,string $expected,callable $rights):void
    {
        $this->locked($draft,$actor,$rights,function()use($draft,$actor,$target,$remove,$expected):void{
            if(!$this->owns($actor,$draft)||$actor===$target)throw new DomainException('Alleen de maker beheert andere bevoegde gebruikers.');
            $before=$this->state($draft);$this->expect($expected,$before);
            if(!$remove){$q=$this->db->prepare('SELECT 1 FROM office_operator_accounts WHERE id=? AND active=1 AND can_edit_crm=1');$q->execute([$target]);if($q->fetchColumn()===false)throw new DomainException('Kies een actieve bevoegde Office-gebruiker.');}
            $q=$this->db->prepare('SELECT 1 FROM office_calc_crm_draft_collaborators WHERE draft_id=? AND operator_id=?');$q->execute([$draft,$target]);$wasAllowed=$q->fetchColumn()!==false;
            $q=$this->db->prepare('DELETE FROM office_calc_crm_draft_collaborators WHERE draft_id=? AND operator_id=?');$q->execute([$draft,$target]);
            if(!$remove){$q=$this->db->prepare('INSERT INTO office_calc_crm_draft_collaborators(draft_id,operator_id,granted_by)VALUES(?,?,?)');$q->execute([$draft,$target,$actor]);}
            $this->record($draft,$actor,$remove?'collaborator_removed':'collaborator_granted','Bewerkrecht '.($remove?'ingetrokken':'toegekend').' voor Office-gebruiker #'.$target,['target'=>$target,'can_edit'=>$wasAllowed],['target'=>$target,'can_edit'=>!$remove]);
        });
    }
    public function checkpoint(int $draft,int $actor,string $reason,string $expected,callable $rights):void
    {
        $this->locked($draft,$actor,$rights,function()use($draft,$actor,$reason,$expected):void{$state=$this->state($draft);$this->expect($expected,$state);$this->record($draft,$actor,'revision_saved',$reason,$state,$state);});
    }
    public function history(int $draft,int $actor,callable $rights):array
    {
        require_once __DIR__.'/OfficeCalcCrmDraftAccess.php';(new OfficeCalcCrmDraftAccess($this->db))->assertCanManage($actor,$draft,$rights);
        $q=$this->db->prepare('SELECT e.id,e.event_kind,e.reason,e.actor_id,e.content_hash,e.created_at,a.username FROM office_calc_crm_draft_changes e LEFT JOIN office_operator_accounts a ON a.id=e.actor_id WHERE e.draft_id=? ORDER BY e.id DESC LIMIT 50');$q->execute([$draft]);return $q->fetchAll(PDO::FETCH_ASSOC);
    }
    public function detail(int $draft,int $actor,int $event,callable $rights):?array
    {
        require_once __DIR__.'/OfficeCalcCrmDraftAccess.php';(new OfficeCalcCrmDraftAccess($this->db))->assertCanManage($actor,$draft,$rights);
        $q=$this->db->prepare('SELECT * FROM office_calc_crm_draft_changes WHERE draft_id=? AND id=?');$q->execute([$draft,$event]);$row=$q->fetch(PDO::FETCH_ASSOC);return $row?:null;
    }
    public function members(int $draft):array
    {
        $q=$this->db->prepare('SELECT c.operator_id,a.username,a.active FROM office_calc_crm_draft_collaborators c JOIN office_operator_accounts a ON a.id=c.operator_id WHERE c.draft_id=? ORDER BY a.username,a.id');$q->execute([$draft]);return $q->fetchAll(PDO::FETCH_ASSOC);
    }
}
