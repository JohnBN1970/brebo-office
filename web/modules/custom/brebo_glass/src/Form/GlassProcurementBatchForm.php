<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Form;

use Drupal\brebo_glass\Service\GlassPositionRepository;
use Drupal\brebo_glass\Service\GlassProcurementBuilder;
use Drupal\brebo_procurement\Service\ProcurementRequestManager;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Bundles multiple approved glass positions into one supplier request. */
final class GlassProcurementBatchForm extends FormBase {

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

  public function getFormId(): string { return 'brebo_glass_procurement_batch_form'; }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $eligible = $this->positions->findAll('', 'approved', 'changed', 'desc');
    $options = [];
    foreach ($eligible as $position) {
      $options[(int) $position['id']] = sprintf(
        '%s · %s · %d x %d mm · %d st. · project %s',
        $position['position_code'],
        $position['composition'],
        $position['width_mm'],
        $position['height_mm'],
        $position['quantity'],
        $position['project_nid'] ?: '-'
      );
    }
    $form['positions'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Vrijgegeven glasposities'),
      '#options' => $options,
      '#required' => TRUE,
      '#description' => $this->t('Een bundel mag alleen posities uit hetzelfde project bevatten.'),
    ];
    $form['supplier_name'] = ['#type'=>'textfield','#title'=>$this->t('Leverancier'),'#required'=>TRUE];
    $form['supplier_ref'] = ['#type'=>'textfield','#title'=>$this->t('Leveranciersreferentie')];
    $form['supplier_email'] = ['#type'=>'email','#title'=>$this->t('E-mailadres leverancier'),'#required'=>TRUE];
    $form['requested_delivery_date'] = ['#type'=>'date','#title'=>$this->t('Gewenste leverdatum')];
    $form['delivery_location'] = ['#type'=>'textfield','#title'=>$this->t('Afleverlocatie'),'#required'=>TRUE];
    $form['note'] = ['#type'=>'textarea','#title'=>$this->t('Aanvullende voorwaarden')];
    $form['actions'] = ['#type'=>'actions'];
    $form['actions']['submit'] = ['#type'=>'submit','#value'=>$this->t('Gebundelde aanvraag maken'),'#button_type'=>'primary'];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $ids = array_values(array_filter((array) $form_state->getValue('positions')));
    if (!$ids) return;
    $projects = [];
    foreach ($ids as $id) {
      $position = $this->positions->find((int) $id);
      if (!$position || (string) $position['technical_status'] !== 'approved') {
        $form_state->setErrorByName('positions', $this->t('Een geselecteerde glaspositie is niet meer beschikbaar of niet vrijgegeven.'));
        return;
      }
      $projects[(string) ($position['project_nid'] ?? '')] = TRUE;
    }
    if (count($projects) > 1) {
      $form_state->setErrorByName('positions', $this->t('Selecteer alleen glasposities uit hetzelfde project.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $ids = array_values(array_filter((array) $form_state->getValue('positions')));
    $lines = [];
    $projectId = NULL;
    foreach ($ids as $id) {
      $position = $this->positions->find((int) $id);
      if (!$position) continue;
      $projectId ??= !empty($position['project_nid']) ? (int) $position['project_nid'] : NULL;
      array_push($lines, ...$this->builder->build($position));
    }
    try {
      $requestId = $this->requests->create(
        $lines,
        $projectId,
        ['name'=>$form_state->getValue('supplier_name'),'ref'=>$form_state->getValue('supplier_ref'),'email'=>$form_state->getValue('supplier_email')],
        $form_state->getValue('requested_delivery_date') ?: NULL,
        (string) $form_state->getValue('delivery_location'),
        (string) $form_state->getValue('note'),
        $this->currentUser(),
      );
      $this->messenger()->addStatus($this->t('@count glasposities gebundeld in leveranciersaanvraag @id.', ['@count'=>count($ids),'@id'=>$requestId]));
      $form_state->setRedirect('brebo_glass.position_overview');
    }
    catch (\Throwable $e) { $this->messenger()->addError($e->getMessage()); }
  }
}
