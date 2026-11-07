<?php
declare(strict_types=1);
namespace Brebo\Office\Finance\Center;
use PDO;
require_once __DIR__.'/Ledger.php';
final class Parties {
  public function __construct(private readonly PDO $db,private readonly string $actor){}
  private function authorize(int $project):void{(new Ledger($this->db,$this->actor))->authorize(true);if(getenv('OFFICE_CRM_RELATIONS_WRITE_ENABLED')!=='enabled')throw new \DomainException('CRM-schrijfbeheer is niet actief.');$q=$this->db->prepare("SELECT 1 FROM office_project_memberships WHERE operator_id=? AND project_id=? AND permission='manage'");$q->execute([$this->actor,$project]);if(!$q->fetchColumn())throw new \DomainException('Projectbeheer is vereist om de factuurdebiteur te wijzigen.');}
  public function current(int $project):array{(new Ledger($this->db,$this->actor))->authorize();(new Ledger($this->db,$this->actor))->project($project);$q=$this->db->prepare("SELECT assignment_type,assignment_id,party_role,relation_id FROM office_crm_assignment_parties WHERE assignment_type IN ('office_project','project') AND assignment_id=? AND party_role='invoice_debtor' ORDER BY assignment_type,relation_id");$q->execute([(string)$project]);return $q->fetchAll(PDO::FETCH_ASSOC);}
  public function bind(int $project,int $relation,string $expected):void{
    $this->authorize($project);$this->db->beginTransaction();try{$lock=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';$q=$this->db->prepare('SELECT id FROM office_projects WHERE id=?'.$lock);$q->execute([$project]);if(!$q->fetchColumn())throw new \DomainException('Project ontbreekt.');$before=$this->current($project);if(!hash_equals(Amounts::hash($before),$expected))throw new \DomainException('De factuurdebiteur is gewijzigd. Controleer opnieuw.');$q=$this->db->prepare("SELECT 1 FROM office_crm_relations WHERE id=? AND status='active'");$q->execute([$relation]);if(!$q->fetchColumn())throw new \DomainException('Kies een actieve relatie.');
      $this->db->prepare("DELETE FROM office_crm_assignment_parties WHERE assignment_type IN ('office_project','project') AND assignment_id=? AND party_role='invoice_debtor'")->execute([(string)$project]);$this->db->prepare('INSERT INTO office_crm_assignment_parties(assignment_type,assignment_id,party_role,relation_id) VALUES(?,?,?,?)')->execute(['office_project',(string)$project,'invoice_debtor',$relation]);$this->db->prepare('INSERT INTO office_finance_center_audit(operator_id,event,entity_id,detail_json,created_at) VALUES(?,?,?,?,?)')->execute([$this->actor,'project_invoice_debtor_set',$project,json_encode(['before'=>$before,'relation_id'=>$relation],JSON_THROW_ON_ERROR),gmdate('c')]);$this->db->commit();
    }catch(\Throwable $e){$this->db->rollBack();throw $e;}
  }
}
