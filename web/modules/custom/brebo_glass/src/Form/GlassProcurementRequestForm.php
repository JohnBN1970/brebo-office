<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Form;

use Drupal\brebo_glass\Service\GlassPositionRepository;
use Drupal\brebo_glass\Service\GlassProcurementBuilder;
use Drupal\brebo_procurement\Service\ProcurementRequestManager;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Creates a traceable supplier request from an approved glass position. */
final class GlassProcurementRequestForm extends FormBase {
  private int $positionId = 0;

  public function __construct(
    private readonly GlassPositionRepository $positions,
    private readonly GlassProcurementBuilder $builder,
    private readonly ProcurementRequestManager $requests,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_glass.position_repository'),
      $container->get('brebo_glass.procurement_builder'),
      $container->get('brebo_procurement.request_manager'),
    );
  }

  public function getFormId(): string { return 'brebo_glass_procurement_request_form'; }

  public function buildForm(array $form, FormStateInterface $form_state, ?int $position_id = NULL): array {
    $this->positionId = (int) $position_id;
    $position = $this->positions->find($this->positionId);
    if (!$position) throw new \InvalidArgumentException('Glaspositie bestaat niet.');
    if (!in_array((string) $position['technical_status'], ['approved', 'ordered'], TRUE)) {
      $form['blocked'] = ['#markup' => '<p>Deze glaspositie moet eerst technisch zijn vrijgegeven.</p>'];
      return $form;
    }
    $form['position'] = ['#type'=>'item','#title'=>$this->t('Inkoopbehoefte'),'#markup'=>$this->t('@code · @composition · @qty st. · @w x @h mm', ['@code'=>$position['position_code'],'@composition'=>$position['composition'],'@qty'=>$position['quantity'],'@w'=>$position['width_mm'],'@h'=>$position['height_mm']])];
    $form['supplier_name'] = ['#type'=>'textfield','#title'=>$this->t('Leverancier'),'#required'=>TRUE];
    $form['supplier_ref'] = ['#type'=>'textfield','#title'=>$this->t('Leveranciersreferentie')];
    $form['supplier_email'] = ['#type'=>'email','#title'=>$this->t('E-mailadres leverancier'),'#required'=>TRUE];
    $form['requested_delivery_date'] = ['#type'=>'date','#title'=>$this->t('Gewenste leverdatum')];
    $form['delivery_location'] = ['#type'=>'textfield','#title'=>$this->t('Afleverlocatie'),'#required'=>TRUE];
    $form['note'] = ['#type'=>'textarea','#title'=>$this->t('Aanvullende inkoopvoorwaarden')];
    $form['actions'] = ['#type'=>'actions'];
    $form['actions']['submit'] = ['#type'=>'submit','#value'=>$this->t('Leveranciersaanvraag aanmaken'),'#button_type'=>'primary'];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $position = $this->positions->find($this->positionId);
    if (!$position) return;
    try {
      $requestId = $this->requests->create(
        $this->builder->build($position),
        !empty($position['project_nid']) ? (int) $position['project_nid'] : NULL,
        ['name'=>$form_state->getValue('supplier_name'),'ref'=>$form_state->getValue('supplier_ref'),'email'=>$form_state->getValue('supplier_email')],
        $form_state->getValue('requested_delivery_date') ?: NULL,
        (string) $form_state->getValue('delivery_location'),
        (string) $form_state->getValue('note'),
        $this->currentUser(),
      );
      $this->messenger()->addStatus($this->t('Leveranciersaanvraag @id is aangemaakt vanuit glaspositie @code.', ['@id'=>$requestId,'@code'=>$position['position_code']]));
      $form_state->setRedirect('brebo_glass.position_overview');
    }
    catch (\Throwable $e) { $this->messenger()->addError($e->getMessage()); }
  }
}
