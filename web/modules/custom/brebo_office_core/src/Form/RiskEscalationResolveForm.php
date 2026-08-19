<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Form;

use Drupal\brebo_office_core\Service\RiskEscalationManager;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Assigns, starts and resolves a BREBO operational escalation. */
final class RiskEscalationResolveForm extends FormBase {
  private int $escalationId=0;
  public function __construct(private readonly RiskEscalationManager $manager) {}
  public static function create(ContainerInterface $container): static {return new static($container->get('brebo_office_core.risk_escalation_manager'));}
  public function getFormId(): string{return'brebo_risk_escalation_resolve_form';}

  public function buildForm(array $form,FormStateInterface $form_state,?int $escalation_id=NULL):array{
    $this->escalationId=(int)$escalation_id;$row=$this->manager->find($this->escalationId);if(!$row)throw new \InvalidArgumentException('Escalatie bestaat niet.');
    $payload=json_decode((string)$row['payload_json'],TRUE)?:[];
    $form['summary']=['#type'=>'item','#title'=>$this->t('Escalatie'),'#markup'=>$this->t('@level · @title',['@level'=>strtoupper((string)$row['level']),'@title'=>$row['title']])];
    $form['impact']=['#type'=>'item','#title'=>$this->t('Impact'),'#markup'=>(string)($payload['impact']['summary']??'-')];
    $form['owner_uid']=['#type'=>'entity_autocomplete','#target_type'=>'user','#title'=>$this->t('Verantwoordelijke'),'#required'=>TRUE,'#default_value'=>!empty($row['owner_uid'])?\Drupal\user\Entity\User::load((int)$row['owner_uid']):NULL];
    $form['due_date']=['#type'=>'date','#title'=>$this->t('Deadline'),'#default_value'=>$row['due_date']?:NULL];
    $form['decision_note']=['#type'=>'textarea','#title'=>$this->t('Besluit / beheersactie'),'#default_value'=>(string)($row['decision_note']??''),'#required'=>TRUE];
    $form['resolution_note']=['#type'=>'textarea','#title'=>$this->t('Afhandeling'),'#default_value'=>(string)($row['resolution_note']??''),'#description'=>$this->t('Verplicht bij definitief sluiten.')];
    $form['evidence_ref']=['#type'=>'textfield','#title'=>$this->t('Bewijs / dossierverwijzing'),'#default_value'=>(string)($row['evidence_ref']??''),'#description'=>$this->t('Bijvoorbeeld document, foto, order, controle of andere aantoonbare dossierverwijzing.')];
    $form['actions']=['#type'=>'actions'];
    $form['actions']['assign']=['#type'=>'submit','#value'=>$this->t('Toewijzen'),'#submit'=>['::assignSubmit']];
    $form['actions']['start']=['#type'=>'submit','#value'=>$this->t('In uitvoering'),'#submit'=>['::startSubmit']];
    $form['actions']['resolve']=['#type'=>'submit','#value'=>$this->t('Sluiten met bewijs'),'#button_type'=>'primary','#submit'=>['::resolveSubmit']];
    return$form;
  }

  public function submitForm(array &$form,FormStateInterface $form_state):void{}
  public function assignSubmit(array &$form,FormStateInterface $form_state):void{$this->manager->assign($this->escalationId,(int)$form_state->getValue('owner_uid'),$form_state->getValue('due_date')?:NULL,(string)$form_state->getValue('decision_note'));$this->messenger()->addStatus($this->t('Escalatie toegewezen.'));$form_state->setRedirect('brebo_glass.control_room');}
  public function startSubmit(array &$form,FormStateInterface $form_state):void{$this->manager->assign($this->escalationId,(int)$form_state->getValue('owner_uid'),$form_state->getValue('due_date')?:NULL,(string)$form_state->getValue('decision_note'));$this->manager->start($this->escalationId);$this->messenger()->addStatus($this->t('Escalatie staat in uitvoering.'));$form_state->setRedirect('brebo_glass.control_room');}
  public function resolveSubmit(array &$form,FormStateInterface $form_state):void{
    if(trim((string)$form_state->getValue('resolution_note'))===''||trim((string)$form_state->getValue('evidence_ref'))===''){$form_state->setErrorByName('resolution_note',$this->t('Afhandeling én bewijs zijn verplicht om te sluiten.'));return;}
    $this->manager->assign($this->escalationId,(int)$form_state->getValue('owner_uid'),$form_state->getValue('due_date')?:NULL,(string)$form_state->getValue('decision_note'));
    $this->manager->resolve($this->escalationId,(string)$form_state->getValue('resolution_note'),(string)$form_state->getValue('evidence_ref'),(int)$this->currentUser()->id());$this->messenger()->addStatus($this->t('Escalatie is aantoonbaar gesloten.'));$form_state->setRedirect('brebo_glass.control_room');
  }
}
