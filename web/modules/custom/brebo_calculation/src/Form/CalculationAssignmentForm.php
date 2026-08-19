<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Form;

use Drupal\brebo_calculation\Service\CalculationWorkAssignmentManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Assigns a calculation to a BREBO calculator with deadline and action. */
final class CalculationAssignmentForm extends FormBase {
  public function __construct(private readonly CalculationWorkAssignmentManager $manager, private readonly EntityTypeManagerInterface $entityTypeManager) {}
  public static function create(ContainerInterface $container): static {return new static($container->get('brebo_calculation.work_assignment_manager'),$container->get('entity_type.manager'));}
  public function getFormId(): string {return 'brebo_calculation_assignment_form';}
  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node=NULL): array {
    if(!$node instanceof NodeInterface||$node->bundle()!=='brebo_calculation') return ['message'=>['#markup'=>'<p>Calculatie niet gevonden.</p>']];
    $form['calculation_id']=['#type'=>'hidden','#value'=>(int)$node->id()];
    $ids=$this->entityTypeManager->getStorage('user')->getQuery()->accessCheck(TRUE)->condition('status',1)->sort('name')->execute();$options=[];
    foreach($this->entityTypeManager->getStorage('user')->loadMultiple($ids) as $account){if($account->hasPermission('edit brebo calculation workbench'))$options[(int)$account->id()]=$account->getDisplayName();}
    $form['owner_uid']=['#type'=>'select','#title'=>$this->t('Verantwoordelijke calculator'),'#options'=>$options,'#required'=>TRUE];
    $form['due_date']=['#type'=>'date','#title'=>$this->t('Deadline')];
    $form['action']=['#type'=>'textfield','#title'=>$this->t('Actie'),'#required'=>TRUE,'#maxlength'=>255,'#placeholder'=>$this->t('Bijv. prijsaanvragen afronden en calculatie vrijgeven')];
    $form['status']=['#type'=>'select','#title'=>$this->t('Status'),'#options'=>['open'=>'Open','in_progress'=>'In uitvoering','waiting'=>'Wachtend','ready'=>'Gereed voor beoordeling','resolved'=>'Afgerond','cancelled'=>'Geannuleerd'],'#default_value'=>'open'];
    $form['actions']['#type']='actions';$form['actions']['submit']=['#type'=>'submit','#value'=>$this->t('Toewijzing opslaan'),'#button_type'=>'primary'];return $form;
  }
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $due=$form_state->getValue('due_date');$dueTs=$due?strtotime((string)$due.' 23:59:59'):NULL;
    $this->manager->save((int)$form_state->getValue('calculation_id'),(int)$form_state->getValue('owner_uid'),$dueTs?:NULL,(string)$form_state->getValue('status'),(string)$form_state->getValue('action'),(int)$this->currentUser()->id());
    $this->messenger()->addStatus($this->t('Calculatie-opvolging opgeslagen en toegevoegd aan Mijn Werkbak.'));
  }
}
