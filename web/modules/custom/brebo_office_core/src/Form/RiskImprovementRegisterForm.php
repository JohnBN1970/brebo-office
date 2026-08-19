<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Form;

use Drupal\brebo_office_core\Service\RiskImprovementAdvisor;
use Drupal\brebo_office_core\Service\RiskImprovementManager;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Registers a detected recurring pattern as a governed improvement. */
final class RiskImprovementRegisterForm extends FormBase {
  public function __construct(private readonly RiskImprovementAdvisor $advisor,private readonly RiskImprovementManager $manager) {}
  public static function create(ContainerInterface $container): static {return new static($container->get('brebo_office_core.risk_improvement_advisor'),$container->get('brebo_office_core.risk_improvement_manager'));}
  public function getFormId(): string{return 'brebo_risk_improvement_register_form';}
  public function buildForm(array $form,FormStateInterface $form_state,?string $fingerprint=NULL): array {
    $match=NULL;foreach($this->advisor->suggestions(90,3) as$s){$fp=hash('sha256',(string)$s['domain'].'|'.(string)$s['cause']);if(hash_equals($fp,(string)$fingerprint)){$match=$s;break;}}
    if(!$match){$form['missing']=['#markup'=>'<p>Dit verbeterpatroon is niet meer actueel of onvoldoende onderbouwd.</p>'];return$form;}
    $form_state->set('suggestion',$match);
    $form['summary']=['#type'=>'item','#title'=>$this->t('Onderbouwing'),'#markup'=>$this->t('@count herhalingen in @projects projecten · bekende blootstelling € @amount',['@count'=>$match['occurrences'],'@projects'=>$match['project_count'],'@amount'=>number_format((float)$match['known_exposure'],2,',','.')])];
    $form['cause']=['#type'=>'item','#title'=>$this->t('Terugkerende oorzaak'),'#markup'=>$match['cause']];
    $form['proposal']=['#type'=>'item','#title'=>$this->t('Voorgestelde preventieve aanpak'),'#markup'=>$match['proposal']];
    $form['actions']=['#type'=>'actions'];$form['actions']['submit']=['#type'=>'submit','#value'=>$this->t('Als verbeterpunt registreren'),'#button_type'=>'primary'];return$form;
  }
  public function submitForm(array &$form,FormStateInterface $form_state): void {$id=$this->manager->registerSuggestion((array)$form_state->get('suggestion'),$this->currentUser());$this->messenger()->addStatus($this->t('Verbeterpunt @id is geregistreerd en wacht op een formeel besluit.',['@id'=>$id]));$form_state->setRedirect('brebo_office_core.risk_control_room');}
}
