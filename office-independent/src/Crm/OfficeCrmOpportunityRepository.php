<?php
declare(strict_types=1);
namespace Brebo\Office\Crm;
use PDO;

/** Native persistence adapter. Actor is provided exclusively by the verified Office session. */
final class OfficeCrmOpportunityRepository
{
    public function __construct(private readonly PDO $db) {}
    private function rows(string $sql,array $args=[]): array
    {
        $q=$this->db->prepare($sql);$q->execute($args);return $q->fetchAll(PDO::FETCH_ASSOC);
    }
    public function authorize(string $actor,bool $write=false): void
    {
        if($actor===''||$this->rows('SELECT 1 FROM office_operator_accounts WHERE id=? AND active=1'.($write?' AND can_edit_crm=1':''),[$actor])===[])throw new \DomainException('Geen CRM-bevoegdheid.');
    }
    public function listForViews(string $actor='',bool $marketing=false): array
    {
        $this->authorize($actor);
        $scope=$marketing?"o.relation_id IS NULL":"o.relation_id IS NOT NULL";
        return $this->rows("SELECT o.*,o.title AS name,r.display_name AS relation_name,
            DATE(o.next_action_at) AS due_date FROM office_crm_opportunities o
            LEFT JOIN office_crm_relations r ON r.id=o.relation_id
            WHERE (o.status='open' OR (o.status='closed' AND o.stage IN ('won','lost'))) AND $scope
            ORDER BY o.next_action_at IS NULL,o.next_action_at,o.id");
    }
    public function calendar(string $actor,bool $marketing=false): array
    {
        $this->authorize($actor);
        $scope=$marketing?"o.relation_id IS NULL":"o.relation_id IS NOT NULL";
        return $this->rows("SELECT o.id,o.title AS name,o.stage,r.display_name AS relation_name,a.title AS next_action,
            DATE(a.scheduled_at) AS due_date,a.scheduled_at,a.completed_at,a.activity_type,a.id AS activity_id
            FROM office_crm_opportunity_activities a JOIN office_crm_opportunities o ON o.id=a.opportunity_id
            LEFT JOIN office_crm_relations r ON r.id=o.relation_id
            WHERE a.scheduled_at IS NOT NULL AND $scope ORDER BY a.scheduled_at,a.id");
    }
    public function detail(string $actor,int $id): array
    {
        $this->authorize($actor);$row=$this->rows('SELECT o.*,r.display_name AS relation_name FROM office_crm_opportunities o LEFT JOIN office_crm_relations r ON r.id=o.relation_id WHERE o.id=?',[$id])[0]??null;
        if($row===null)throw new \OutOfBoundsException('Verkoopkans niet gevonden.');
        $row['activities']=$this->rows('SELECT * FROM office_crm_opportunity_activities WHERE opportunity_id=? ORDER BY created_at DESC,id DESC',[$id]);
        return $row;
    }
    public function choices(string $actor): array
    {
        $this->authorize($actor);
        return ['relations'=>$this->rows("SELECT id,display_name FROM office_crm_relations WHERE status='active' ORDER BY display_name"),
            'contacts'=>$this->rows("SELECT c.id,c.given_name,c.family_name,rc.relation_id FROM office_crm_contact_people c JOIN office_crm_relation_contacts rc ON rc.contact_id=c.id WHERE c.status='active' ORDER BY c.family_name,c.given_name")];
    }
    private function validateRelation(array $data): void
    {
        if($this->rows("SELECT 1 FROM office_crm_relations WHERE id=? AND status='active'",[$data['relation_id']])===[])throw new \DomainException('Kies een actieve relatie.');
        if($data['contact_id']!==null&&$this->rows("SELECT 1 FROM office_crm_relation_contacts rc JOIN office_crm_contact_people c ON c.id=rc.contact_id WHERE rc.relation_id=? AND rc.contact_id=? AND c.status='active'",[$data['relation_id'],$data['contact_id']])===[])throw new \DomainException('Contactpersoon hoort niet bij deze relatie.');
    }
    private function lock(int $id,int $revision): array
    {
        $suffix=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
        $row=$this->rows('SELECT * FROM office_crm_opportunities WHERE id=?'.$suffix,[$id])[0]??null;
        if(!$row)throw new \OutOfBoundsException('Verkoopkans niet gevonden.');
        if((int)$row['revision']!==$revision)throw new \UnexpectedValueException('De verkoopkans is intussen gewijzigd. Herlaad de pagina.');
        return $row;
    }
    private function transaction(string $actor,string $key,array $intent,callable $write): int
    {
        $this->authorize($actor,true);OfficeCrmOpportunityPolicy::key($key);
        if($this->db->inTransaction())throw new \LogicException('Eigen transactie vereist.');
        $hash=hash('sha256',json_encode($intent,JSON_THROW_ON_ERROR));
        $this->db->beginTransaction();
        try{
            $this->authorize($actor,true);
            $prior=$this->rows('SELECT opportunity_id,request_hash FROM office_crm_opportunity_activities WHERE created_by=? AND request_key=?',[$actor,$key]);
            if($prior!==[]){
                if(!hash_equals((string)$prior[0]['request_hash'],$hash))throw new \DomainException('Formuliercode is voor andere inhoud gebruikt.');
                $id=(int)$prior[0]['opportunity_id'];$this->db->commit();return $id;
            }
            [$id,$type,$title,$details]=$write();
            $q=$this->db->prepare('INSERT INTO office_crm_opportunity_activities (opportunity_id,activity_type,title,created_by,request_key,request_hash,detail_json) VALUES (?,?,?,?,?,?,?)');
            $q->execute([$id,$type,$title,$actor,$key,$hash,json_encode($details,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
            $this->db->commit();return $id;
        }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    public function create(int $relationId,string $title,?int $contactId=null,string $actor='',array $extra=[],string $key=''): int
    {
        $data=OfficeCrmOpportunityPolicy::details(array_replace($extra,['relation_id'=>$relationId,'title'=>$title,'contact_id'=>$contactId??'']));
        return $this->transaction($actor,$key,['create',$data],function()use($data):array{
            $this->validateRelation($data);
            $q=$this->db->prepare("INSERT INTO office_crm_opportunities (relation_id,contact_id,title,stage,currency,description,expected_value,expected_close_date,service_type) VALUES (?,?,?,'prospect','EUR',?,?,?,?)");
            $q->execute([$data['relation_id'],$data['contact_id'],$data['title'],$data['description'],$data['expected_value'],$data['expected_close_date'],$data['service_type']]);
            $id=(int)$this->db->lastInsertId();
            $role=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?"INSERT IGNORE":"INSERT OR IGNORE";
            $q=$this->db->prepare($role." INTO office_crm_relation_roles (relation_id,role_code) VALUES (?,'prospect')");$q->execute([$data['relation_id']]);
            return [$id,'created','Verkoopkans aangemaakt',$data];
        });
    }
    public function createLead(string $actor,string $title,string $description,string $source,string $key): int
    {
        $title=trim($title);$description=trim($description);$source=trim($source);
        if($title===''||!mb_check_encoding($title,'UTF-8')||!mb_check_encoding($description,'UTF-8')||!mb_check_encoding($source,'UTF-8')||mb_strlen($title,'UTF-8')>255||mb_strlen($description,'UTF-8')>12000||mb_strlen($source,'UTF-8')>128)throw new \InvalidArgumentException('Controleer naam, bron en toelichting van de lead.');
        return $this->transaction($actor,$key,['lead',$title,$description,$source],function()use($title,$description,$source):array{
            $q=$this->db->prepare("INSERT INTO office_crm_opportunities (title,stage,currency,description,source) VALUES (?,'lead','EUR',?,?)");$q->execute([$title,$description,$source]);
            return [(int)$this->db->lastInsertId(),'lead_created','Marketing-lead vastgelegd',['source'=>$source]];
        });
    }
    private function prospectRole(int $relation): void
    {
        $sql=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?'INSERT IGNORE':'INSERT OR IGNORE';
        $q=$this->db->prepare($sql." INTO office_crm_relation_roles (relation_id,role_code) VALUES (?,'prospect')");$q->execute([$relation]);
    }
    public function update(string $actor,int $id,int $revision,array $input,string $key,bool $qualify=false): int
    {
        $data=OfficeCrmOpportunityPolicy::details($input);
        return $this->transaction($actor,$key,['update',$id,$revision,$data,$qualify],function()use($id,$revision,$data,$qualify):array{
            $old=$this->lock($id,$revision);
            if($old['status']!=='open')throw new \DomainException('Heropen de verkoopkans voordat de inhoud wordt gewijzigd.');
            $this->validateRelation($data);
            if(($old['stage']==='lead')!==$qualify)throw new \DomainException('Gebruik de expliciete leadkwalificatie.');
            $q=$this->db->prepare('UPDATE office_crm_opportunities SET relation_id=?,contact_id=?,title=?,description=?,expected_value=?,expected_close_date=?,service_type=?,revision=revision+1,updated_at=CURRENT_TIMESTAMP WHERE id=? AND revision=?');
            $q->execute([$data['relation_id'],$data['contact_id'],$data['title'],$data['description'],$data['expected_value'],$data['expected_close_date'],$data['service_type'],$id,$revision]);
            if($qualify){$q=$this->db->prepare("UPDATE office_crm_opportunities SET stage='prospect' WHERE id=?");$q->execute([$id]);$this->prospectRole($data['relation_id']);}
            return [$id,$qualify?'qualified':'updated',$qualify?'Lead gekwalificeerd als prospect':'Verkoopkans bijgewerkt',['before'=>$old,'after'=>$data]];
        });
    }
    public function transition(int $id,string $from,string $to,string $actor='',int $revision=0,string $note='',string $key=''): bool
    {
        $data=OfficeCrmOpportunityPolicy::transition($from,$to,$note);
        $this->transaction($actor,$key,['stage',$id,$revision,$from,$data],function()use($id,$revision,$from,$data):array{
            $old=$this->lock($id,$revision);
            if(($data['stage']==='prospect'&&empty($old['relation_id']))||($data['stage']==='lead'&&!empty($old['relation_id'])))throw new \DomainException('De gekozen fase past niet bij de relatiekwalificatie.');
            if($from==='lead'&&$data['stage']==='prospect')throw new \DomainException('Koppel eerst een relatie via leadkwalificatie.');
            if($old['stage']!==$from)throw new \UnexpectedValueException('De fase is intussen gewijzigd.');
            $q=$this->db->prepare('UPDATE office_crm_opportunities SET stage=?,status=?,outcome_note=?,revision=revision+1,updated_at=CURRENT_TIMESTAMP WHERE id=? AND revision=?');
            $q->execute([$data['stage'],$data['status'],$data['outcome_note'],$id,$revision]);
            return [$id,'stage','Fase gewijzigd: '.OfficeCrmFunnel::label($data['stage']),['from'=>$from]+$data];
        });return true;
    }
    public function addActivity(string $actor,int $id,int $revision,array $input,string $key): int
    {
        $data=OfficeCrmOpportunityPolicy::activity($input);
        return $this->transaction($actor,$key,['activity',$id,$revision,$data],function()use($actor,$id,$revision,$data):array{
            $old=$this->lock($id,$revision);if($old['status']!=='open')throw new \DomainException('Deze verkoopkans is gesloten.');
            $q=$this->db->prepare('INSERT INTO office_crm_opportunity_activities (opportunity_id,activity_type,title,scheduled_at,created_by) VALUES (?,?,?,?,?)');
            $q->execute([$id,$data['activity_type'],$data['title'],$data['scheduled_at'],$actor]);
            $activityId=(int)$this->db->lastInsertId();$this->syncNext($id);
            return [$id,'activity_added','Activiteit gepland',['activity_id'=>$activityId]+$data];
        });
    }
    public function completeActivity(string $actor,int $id,int $revision,int $activity,string $key): int
    {
        return $this->transaction($actor,$key,['complete',$id,$revision,$activity],function()use($id,$revision,$activity):array{
            $this->lock($id,$revision);
            $q=$this->db->prepare('UPDATE office_crm_opportunity_activities SET completed_at=CURRENT_TIMESTAMP WHERE id=? AND opportunity_id=? AND scheduled_at IS NOT NULL AND completed_at IS NULL');$q->execute([$activity,$id]);
            if($q->rowCount()!==1)throw new \DomainException('Activiteit niet gevonden of al afgerond.');
            $this->syncNext($id);return [$id,'activity_completed','Activiteit afgerond',['activity_id'=>$activity]];
        });
    }
    private function syncNext(int $id): void
    {
        $next=$this->rows('SELECT title,scheduled_at FROM office_crm_opportunity_activities WHERE opportunity_id=? AND scheduled_at IS NOT NULL AND completed_at IS NULL ORDER BY scheduled_at,id LIMIT 1',[$id])[0]??null;
        $q=$this->db->prepare('UPDATE office_crm_opportunities SET next_action=?,next_action_at=?,opname_required=?,revision=revision+1,updated_at=CURRENT_TIMESTAMP WHERE id=?');
        $hasOpname=$this->rows("SELECT 1 FROM office_crm_opportunity_activities WHERE opportunity_id=? AND activity_type LIKE 'opname:%' AND completed_at IS NULL LIMIT 1",[$id])!==[];
        $q->execute([$next['title']??null,$next['scheduled_at']??null,$hasOpname?1:0,$id]);
    }
}
