<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;

/** Creates and governs structural embedding proposals for proven improvements. */
final class RiskImprovementEmbeddingManager {
  public function __construct(private readonly Connection $database) {}

  public function ensureStorage(): void {
    $schema=$this->database->schema();
    if($schema->tableExists('brebo_risk_improvement_embedding')) return;
    $schema->createTable('brebo_risk_improvement_embedding',[
      'description'=>'Governed proposals to embed effective improvements into BREBO standards and workflows.',
      'fields'=>[
        'id'=>['type'=>'serial','not null'=>TRUE],
        'improvement_id'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],
        'target_type'=>['type'=>'varchar_ascii','length'=>64,'not null'=>TRUE],
        'target_reference'=>['type'=>'varchar','length'=>255,'not null'=>FALSE],
        'change_proposal'=>['type'=>'text','size'=>'big','not null'=>TRUE],
        'impact_note'=>['type'=>'text','size'=>'big','not null'=>FALSE],
        'status'=>['type'=>'varchar_ascii','length'=>32,'not null'=>TRUE,'default'=>'proposed'],
        'approved_by'=>['type'=>'int','unsigned'=>TRUE,'not null'=>FALSE],
        'approved_at'=>['type'=>'int','unsigned'=>TRUE,'not null'=>FALSE],
        'implemented_by'=>['type'=>'int','unsigned'=>TRUE,'not null'=>FALSE],
        'implemented_at'=>['type'=>'int','unsigned'=>TRUE,'not null'=>FALSE],
        'implementation_evidence'=>['type'=>'varchar','length'=>1024,'not null'=>FALSE],
        'created'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],
        'created_by'=>['type'=>'int','unsigned'=>TRUE,'not null'=>FALSE],
      ],
      'primary key'=>['id'],
      'indexes'=>['improvement_status'=>['improvement_id','status'],'target'=>['target_type','target_reference']],
    ]);
  }

  public function propose(int $improvementId,string $targetType,?string $targetReference,string $changeProposal,string $impactNote,AccountInterface $account): int {
    $this->ensureStorage();
    $improvement=$this->database->select('brebo_risk_improvement','i')->fields('i')->condition('id',$improvementId)->execute()->fetchAssoc();
    if(!$improvement||$improvement['status']!=='effective') throw new \RuntimeException('Alleen bewezen effectieve verbeteringen mogen worden geborgd.');
    $allowed=['norm','control_gate','calculation_rule','procurement_rule','workflow','work_instruction'];
    if(!in_array($targetType,$allowed,TRUE)) throw new \InvalidArgumentException('Ongeldig borgingsdoel.');
    if(trim($changeProposal)==='') throw new \RuntimeException('Beschrijf de structurele wijziging.');
    return (int)$this->database->insert('brebo_risk_improvement_embedding')->fields([
      'improvement_id'=>$improvementId,'target_type'=>$targetType,'target_reference'=>trim((string)$targetReference)?:NULL,
      'change_proposal'=>trim($changeProposal),'impact_note'=>trim($impactNote)?:NULL,'status'=>'proposed',
      'created'=>time(),'created_by'=>(int)$account->id(),
    ])->execute();
  }

  public function approve(int $id,AccountInterface $account): void {
    $this->ensureStorage();
    $updated=$this->database->update('brebo_risk_improvement_embedding')->fields(['status'=>'approved','approved_by'=>(int)$account->id(),'approved_at'=>time()])->condition('id',$id)->condition('status','proposed')->execute();
    if(!$updated) throw new \RuntimeException('Borgingsvoorstel kan niet worden goedgekeurd vanuit de huidige status.');
  }

  public function markImplemented(int $id,string $evidence,AccountInterface $account): void {
    if(trim($evidence)==='') throw new \RuntimeException('Bewijs van implementatie is verplicht.');
    $updated=$this->database->update('brebo_risk_improvement_embedding')->fields(['status'=>'implemented','implemented_by'=>(int)$account->id(),'implemented_at'=>time(),'implementation_evidence'=>trim($evidence)])->condition('id',$id)->condition('status','approved')->execute();
    if(!$updated) throw new \RuntimeException('Alleen goedgekeurde borgingsvoorstellen kunnen als geïmplementeerd worden gemarkeerd.');
  }
}
