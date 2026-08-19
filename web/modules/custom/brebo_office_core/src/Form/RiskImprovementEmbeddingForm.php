<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Form;

use Drupal\brebo_office_core\Service\RiskImprovementEmbeddingManager;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Creates, approves and closes structural embedding proposals. */
final class RiskImprovementEmbeddingForm extends FormBase {
  public function __construct(private readonly Connection $database,private readonly RiskImprovementEmbeddingManager $manager) {}
  public static function create(ContainerInterface $container): static {return new static($container->get('database'),$container->get('brebo_office_core.risk_improvement_embedding_manager'));}
  public function getFormId(): string{return 'brebo_risk_improvement_embedding_form';}

  public function buildForm(array $form,FormStateInterface $form_state,?int $improvement=NULL,?int $embedding=NULL): array {
    $this->manager->ensureStorage();
    if($embedding){
      $row=$this->database->select('brebo_risk_improvement_embedding','e')->fields('e')->condition('id',(int)$embedding)->execute()->fetchAssoc();
      if(!$row){$form['missing']=['#markup'=>'<p>Borgingsvoorstel niet gevonden.</p>'];return$form;}
      $form_state->set('embedding_id',(int)$row['id']);
      $form['target']=['#type'=>'item','#title'=>$this->t('Borgingsdoel'),'#markup'=>$row['target_type'].($row['target_reference']?' · '.$row['target_reference']:'')];
      $form['proposal']=['#type'=>'item','#title'=>$this->t('Structurele wijziging'),'#markup'=>$row['change_proposal']];
      $form['impact']=['#type'=>'item','#title'=>$this->t('Impact'),'#markup'=>$row['impact_note']?:'-'];
      $form['status']=['#type'=>'item','#title'=>$this->t('Status'),'#markup'=>$row['status']];
      if($row['status']==='proposed'){$form['actions']=['#type'=>'actions','approve'=>['#type'=>'submit','#value'=>$this->t('Borging goedkeuren'),'#button_type'=>'primary','#submit'=>['::approve']]];}
      elseif($row['status']==='approved'){$form['evidence']=['#type'=>'textfield','#title'=>$this->t('Implementatiebewijs / dossierverwijzing'),'#required'=>TRUE,'#description'=>$this->t('Bijvoorbeeld wijzigingsbesluit, normversie, Git-commit, werkinstructie of gecontroleerd document.')];$form['actions']=['#type'=>'actions','implemented'=>['#type'=>'submit','#value'=>$this->t('Als geïmplementeerd vastleggen'),'#button_type'=>'primary','#submit'=>['::implemented']]];}
      return$form;
    }

    $improvementRow=$this->database->select('brebo_risk_improvement','i')->fields('i')->condition('id',(int)$improvement)->execute()->fetchAssoc();
    if(!$improvementRow||$improvementRow['status']!=='effective'){$form['missing']=['#markup'=>'<p>Alleen een bewezen effectieve verbetering kan worden geborgd.</p>'];return$form;}
    $form_state->set('improvement_id',(int)$improvementRow['id']);
    $form['measure']=['#type'=>'item','#title'=>$this->t('Bewezen effectieve maatregel'),'#markup'=>$improvementRow['measure_note']?:$improvementRow['proposal']];
    $form['target_type']=['#type'=>'select','#title'=>$this->t('Borgingsdoel'),'#required'=>TRUE,'#options'=>['norm'=>'Norm','control_gate'=>'Controlepoort','calculation_rule'=>'Calculatieregel','procurement_rule'=>'Inkoopregel','workflow'=>'Workflow','work_instruction'=>'Werkinstructie']];
    $form['target_reference']=['#type'=>'textfield','#title'=>$this->t('Doelreferentie'),'#description'=>$this->t('Optioneel: normcode, controlepoort, workflow-ID of andere bestaande referentie.')];
    $form['change_proposal']=['#type'=>'textarea','#title'=>$this->t('Structurele wijziging'),'#required'=>TRUE,'#default_value'=>$improvementRow['measure_note']??''];
    $form['impact_note']=['#type'=>'textarea','#title'=>$this->t('Impact en aandachtspunten'),'#description'=>$this->t('Beschrijf gevolgen voor kwaliteit, kosten, planning, veiligheid, beheer of andere processen.')];
    $form['actions']=['#type'=>'actions','submit'=>['#type'=>'submit','#value'=>$this->t('Borgingsvoorstel aanmaken'),'#button_type'=>'primary']];return$form;
  }

  public function submitForm(array &$form,FormStateInterface $state):void{$id=$this->manager->propose((int)$state->get('improvement_id'),(string)$state->getValue('target_type'),$state->getValue('target_reference')?:NULL,(string)$state->getValue('change_proposal'),(string)$state->getValue('impact_note'),$this->currentUser());$this->messenger()->addStatus($this->t('Borgingsvoorstel @id aangemaakt en wacht op goedkeuring.',['@id'=>$id]));$state->setRedirect('brebo_office_core.risk_improvement_embedding_review',['embedding'=>$id]);}
  public function approve(array &$form,FormStateInterface $state):void{$this->manager->approve((int)$state->get('embedding_id'),$this->currentUser());$this->messenger()->addStatus($this->t('Borgingsvoorstel goedgekeurd. Implementatie moet nog aantoonbaar worden uitgevoerd.'));$state->setRebuild();}
  public function implemented(array &$form,FormStateInterface $state):void{$this->manager->markImplemented((int)$state->get('embedding_id'),(string)$state->getValue('evidence'),$this->currentUser());$this->messenger()->addStatus($this->t('Structurele borging is met bewijs als geïmplementeerd vastgelegd.'));$state->setRedirect('brebo_office_core.risk_control_room');}
}
