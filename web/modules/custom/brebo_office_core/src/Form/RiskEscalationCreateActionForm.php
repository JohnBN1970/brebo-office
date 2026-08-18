<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Form;

use Drupal\brebo_office_core\Service\RiskEscalationActionBridge;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Creates the canonical BREBO action for an escalation. */
final class RiskEscalationCreateActionForm extends ConfirmFormBase {
  private int $escalationId=0;

  public function __construct(private readonly Connection $database,private readonly RiskEscalationActionBridge $bridge) {}
  public static function create(ContainerInterface $container): static {return new static($container->get('database'),$container->get('brebo_office_core.risk_escalation_action_bridge'));}
  public function getFormId(): string {return 'brebo_risk_escalation_create_action_form';}

  public function buildForm(array $form,FormStateInterface $form_state,?int $escalation=NULL): array {
    $this->escalationId=(int)$escalation;
    $row=$this->database->select('brebo_risk_escalation','e')->fields('e')->condition('id',$this->escalationId)->execute()->fetchAssoc();
    if(!$row) throw new \InvalidArgumentException('Escalatie bestaat niet.');
    if(empty($row['owner_uid'])) throw new \RuntimeException('Wijs eerst een verantwoordelijke toe in de Control Room.');
    return parent::buildForm($form,$form_state);
  }

  public function getQuestion(): string {return $this->t('Een centrale BREBO Actie aanmaken voor deze escalatie?');}
  public function getConfirmText(): string {return $this->t('Actie aanmaken');}
  public function getCancelUrl(): Url {return Url::fromRoute('brebo_office_core.risk_control_room');}

  public function submitForm(array &$form,FormStateInterface $form_state): void {
    $nid=$this->bridge->ensureAction($this->escalationId,$this->currentUser());
    $this->messenger()->addStatus($this->t('BREBO Actie @nid is gekoppeld aan de escalatie.',['@nid'=>$nid]));
    $form_state->setRedirect('entity.node.canonical',['node'=>$nid]);
  }
}
