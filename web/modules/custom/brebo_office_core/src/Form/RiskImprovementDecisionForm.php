<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Form;

use Drupal\brebo_office_core\Service\RiskImprovementManager;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Handles management decision, measure definition and validation. */
final class RiskImprovementDecisionForm extends FormBase {
  public function __construct(private readonly Connection $database,private readonly RiskImprovementManager $manager) {}
  public static function create(ContainerInterface $container): static{return new static($container->get('database'),$container->get('brebo_office_core.risk_improvement_manager'));}
  public function getFormId(): string{return 'brebo_risk_improvement_decision_form';}
  public function buildForm(array $form,FormStateInterface $form_state,?int $improvement=NULL): array {
    $this->manager->ensureStorage();$row=$this->database->select('brebo_risk_improvement','i')->fields('i')->condition('id',(int)$improvement)->execute()->fetchAssoc();if(!$row){$form['missing']=['#markup'=>'<p>Verbeterpunt niet gevonden.</p>'];return$form;}$form_state->set('id',(int)$row['id']);
    $form['status']=['#type'=>'item','#title'=>$this->t('Status'),'#markup'=>$row['status']];$form['cause']=['#type'=>'item','#title'=>$this->t('Oorzaak'),'#markup'=>$row['cause']];$form['proposal']=['#type'=>'item','#title'=>$this->t('Voorstel'),'#markup'=>$row['proposal']];
    if($row['status']==='proposed'){$form['decision']=['#type'=>'textarea','#title'=>$this->t('Motivering besluit'),'#required'=>TRUE];$form['owner_uid']=['#type'=>'entity_autocomplete','#title'=>$this->t('Eigenaar verbetering'),'#target_type'=>'user'];$form['actions']=['#type'=>'actions'];$form['actions']['approve']=['#type'=>'submit','#value'=>$this->t('Goedkeuren'),'#submit'=>['::approve']];$form['actions']['reject']=['#type'=>'submit','#value'=>$this->t('Afwijzen'),'#submit'=>['::reject'],'#limit_validation_errors'=>[['decision']]];}
    elseif(in_array($row['status'],['approved','needs_revision'],TRUE)){$form['measure']=['#type'=>'textarea','#title'=>$this->t('Definitieve preventieve maatregel'),'#default_value'=>$row['measure_note']??'','#required'=>TRUE];$form['actions']=['#type'=>'actions','start'=>['#type'=>'submit','#value'=>$this->t('Start praktijkvalidatie'),'#button_type'=>'primary','#submit'=>['::startValidation']]];}
    elseif($row['status']==='validating'){$form['progress']=['#type'=>'item','#title'=>$this->t('Validatievoortgang'),'#markup'=>$row['validated_cases'].' / '.$row['validation_target'].' succesvolle praktijkgevallen'];$form['actions']=['#type'=>'actions','success'=>['#type'=>'submit','#value'=>$this->t('Praktijkgeval akkoord'),'#submit'=>['::success']],'fail'=>['#type'=>'submit','#value'=>$this->t('Praktijkgeval onvoldoende'),'#submit'=>['::fail']]];}
    return$form;
  }
  public function approve(array &$form,FormStateInterface $state):void{$owner=$state->getValue('owner_uid');$this->manager->decide((int)$state->get('id'),TRUE,(string)$state->getValue('decision'),$owner?(int)$owner:NULL,$this->currentUser());$this->done($state,'Verbeterpunt goedgekeurd. Leg nu de definitieve maatregel vast.');}
  public function reject(array &$form,FormStateInterface $state):void{$this->manager->decide((int)$state->get('id'),FALSE,(string)$state->getValue('decision'),NULL,$this->currentUser());$this->done($state,'Verbeterpunt gemotiveerd afgewezen.');}
  public function startValidation(array &$form,FormStateInterface $state):void{$this->manager->startValidation((int)$state->get('id'),(string)$state->getValue('measure'));$this->done($state,'Praktijkvalidatie gestart.');}
  public function success(array &$form,FormStateInterface $state):void{$this->manager->recordValidation((int)$state->get('id'),TRUE);$this->done($state,'Succesvol praktijkgeval geregistreerd.');}
  public function fail(array &$form,FormStateInterface $state):void{$this->manager->recordValidation((int)$state->get('id'),FALSE);$this->done($state,'Validatie onvoldoende: maatregel moet worden herzien.');}
  private function done(FormStateInterface $state,string $message):void{$this->messenger()->addStatus($this->t($message));$state->setRedirect('brebo_office_core.risk_control_room');}
  public function submitForm(array &$form,FormStateInterface $form_state):void{}
}
