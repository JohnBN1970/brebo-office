<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Form;

use Drupal\brebo_calculation\Service\SubcalculationManager;
use Drupal\brebo_calculation\Service\CalculationWorkspaceStateService;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Manage reusable subcalculations for one calculation. */
final class SubcalculationOverviewForm extends FormBase {

  public function __construct(private readonly CalculationWorkspaceStateService $workspaceState, private readonly SubcalculationManager $manager) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_calculation.workspace_state'), $container->get('brebo_calculation.subcalculation_manager'));
  }

  public function getFormId(): string { return 'brebo_calculation_subcalculation_overview_form'; }

  public function buildForm(array $form, FormStateInterface $form_state, ?int $calculation = NULL): array {
    $calculationId = (int) $calculation;
    if ($calculationId <= 0) {
      return ['message' => ['#markup' => '<p>Calculatie niet gevonden.</p>']];
    }
    try {
      $state = $this->workspaceState->state($calculationId);
    }
    catch (\RuntimeException) {
      return ['message' => ['#markup' => '<p>Geen actieve calculatieversie gevonden.</p>']];
    }
    $version = (array) $state['version'];
    $form['#tree'] = TRUE;
    $form['#attached']['library'][] = 'brebo_calculation/workbench';
    $form['calculation_id'] = ['#type' => 'hidden', '#value' => $calculationId];
    $form['version'] = ['#type' => 'hidden', '#value' => (string) $version['version']];
    $records = $this->manager->list($calculationId, (string) $version['version']);

    $form['intro'] = ['#markup' => '<p>Deelcalculaties zijn herbruikbare rekeneenheden. Dit kan een woningtype zijn, maar net zo goed een repeterend geveldeel, balkon, kozijnmodule, dakvlak, trappenhuis, galerijvak, bouwdeel of vrij werkpakket.</p>'];
    $form['overview'] = ['#type' => 'table', '#header' => ['Code', 'Deelcalculatie', 'Type', 'Rekeneenheid', 'Scope', 'Toepassingen', 'Directe kostprijs / eenheid', 'Status', 'Actie'], '#empty' => 'Nog geen deelcalculaties aangemaakt.', '#attributes' => ['class' => ['brebo-subcalculation-table']]];
    foreach ($records as $record) {
      $id = (int) $record['id'];
      $scopeCount = $this->manager->scopeCount($id);
      $applicationCount = $this->manager->applicationCount($id);
      $totals = $this->manager->totals((int) $id);
      $form['overview']['sub_' . $id] = [
        'code' => ['#markup' => htmlspecialchars((string) ($record['code'] ?: '—'))],
        'label' => ['#markup' => '<strong>' . htmlspecialchars((string) $record['label']) . '</strong>'],
        'type' => ['#markup' => htmlspecialchars($this->typeLabel((string) $record['subcalculation_type']))],
        'unit' => ['#markup' => htmlspecialchars((string) ($record['unit_label'] ?: 'eenheid'))],
        'scope' => ['#markup' => (string) $scopeCount],
        'applications' => ['#markup' => (string) $applicationCount],
        'direct' => ['#markup' => '<strong>€ ' . number_format($totals['direct'], 2, ',', '.') . '</strong>'],
        'status' => ['#markup' => htmlspecialchars((string) $record['status'])],
        'action' => ['#type' => 'link', '#title' => 'Samenstellen', '#url' => Url::fromRoute('brebo_calculation.subcalculation_detail', ['calculation' => $calculationId, 'subcalculation' => $id])],
      ];
    }

    $form['add'] = ['#type' => 'details', '#title' => 'Deelcalculatie toevoegen', '#open' => !$records];
    $form['add']['code'] = ['#type' => 'textfield', '#title' => 'Code', '#size' => 20];
    $form['add']['label'] = ['#type' => 'textfield', '#title' => 'Naam', '#required' => TRUE];
    $form['add']['subcalculation_type'] = ['#type' => 'select', '#title' => 'Type rekeneenheid', '#options' => [
      'housing_type' => 'Woningtype',
      'repeating_facade' => 'Repeterend geveldeel',
      'balcony_type' => 'Balkon(type)',
      'frame_module' => 'Kozijn-/puimodule',
      'roof_section' => 'Dakvlak / dakdeel',
      'stairwell_type' => 'Trappenhuis(type)',
      'gallery_section' => 'Galerij-/balkonvak',
      'building_part' => 'Bouwdeel',
      'technical_zone' => 'Technische zone',
      'work_package' => 'Werkpakket / inkooppakket',
      'phase' => 'Fase',
      'manual' => 'Vrije deelcalculatie',
    ], '#default_value' => 'manual'];
    $form['add']['unit_label'] = ['#type' => 'textfield', '#title' => 'Rekeneenheid', '#default_value' => 'st', '#description' => 'Bijvoorbeeld woning, gevelvak, balkon, kozijn, dakvlak, m² of st.'];
    $form['add']['base_quantity'] = ['#type' => 'number', '#title' => 'Basiseenheid', '#default_value' => 1, '#step' => '0.0001', '#min' => 0.0001];
    $form['add']['context_type'] = ['#type' => 'textfield', '#title' => 'Object-/contexttype', '#description' => 'Type canoniek gebouw-/projectobject waarop deze rekeneenheid betrekking heeft.'];
    $form['add']['context_ref'] = ['#type' => 'textfield', '#title' => 'Object-/contextreferentie', '#description' => 'Optionele verwijzing naar het canonieke gebouw-/projectobject of type.'];
    $form['add']['submit'] = ['#type' => 'submit', '#value' => 'Deelcalculatie aanmaken', '#button_type' => 'primary'];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $id = $this->manager->create((int) $form_state->getValue('calculation_id'), (string) $form_state->getValue('version'), (array) $form_state->getValue('add'), (int) $this->currentUser()->id());
    $this->messenger()->addStatus($this->t('Deelcalculatie @id aangemaakt.', ['@id' => $id]));
    $form_state->setRebuild(TRUE);
  }

  private function typeLabel(string $type): string {
    return [
      'housing_type' => 'Woningtype', 'repeating_facade' => 'Repeterend geveldeel', 'balcony_type' => 'Balkon(type)', 'frame_module' => 'Kozijn-/puimodule', 'roof_section' => 'Dakvlak / dakdeel', 'stairwell_type' => 'Trappenhuis(type)', 'gallery_section' => 'Galerij-/balkonvak', 'building_part' => 'Bouwdeel', 'technical_zone' => 'Technische zone', 'work_package' => 'Werkpakket / inkooppakket', 'phase' => 'Fase', 'manual' => 'Vrije deelcalculatie',
    ][$type] ?? $type;
  }

}
