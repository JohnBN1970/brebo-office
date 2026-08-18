<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Form;

use Drupal\brebo_glass\Service\GlassCandidateSelector;
use Drupal\brebo_glass\Service\GlassPositionRepository;
use Drupal\brebo_glass\Service\GlassProductRepository;
use Drupal\brebo_glass\Service\GlassThreePointMeasurementCalculator;
use Drupal\brebo_glass\Service\GlassSpecificationCalculator;
use Drupal\brebo_glass\Service\GlassTechnicalRuleEvaluator;
use Drupal\brebo_glass\Service\GlassWindLoadCalculator;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Captures a glass position in the canonical BREBO object structure.
 */
final class GlassPositionForm extends FormBase {

  public function __construct(
    private readonly GlassThreePointMeasurementCalculator $measurementCalculator,
    private readonly GlassSpecificationCalculator $calculator,
    private readonly GlassTechnicalRuleEvaluator $technicalRules,
    private readonly GlassWindLoadCalculator $windCalculator,
    private readonly GlassCandidateSelector $candidateSelector,
    private readonly GlassProductRepository $productRepository,
    private readonly GlassPositionRepository $repository,
    private readonly UuidInterface $uuid,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_glass.three_point_measurement_calculator'),
      $container->get('brebo_glass.specification_calculator'),
      $container->get('brebo_glass.technical_rule_evaluator'),
      $container->get('brebo_glass.wind_load_calculator'),
      $container->get('brebo_glass.candidate_selector'),
      $container->get('brebo_glass.product_repository'),
      $container->get('brebo_glass.position_repository'),
      $container->get('uuid'),
    );
  }

  public function getFormId(): string {
    return 'brebo_glass_position_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['context'] = ['#type' => 'details', '#title' => $this->t('Objectkoppeling'), '#open' => TRUE];
    $form['context']['building_nid'] = ['#type' => 'entity_autocomplete', '#title' => $this->t('Gebouw'), '#target_type' => 'node', '#selection_settings' => ['target_bundles' => ['brebo_building']], '#required' => TRUE];
    $form['context']['project_nid'] = ['#type' => 'entity_autocomplete', '#title' => $this->t('Project'), '#target_type' => 'node', '#selection_settings' => ['target_bundles' => ['brebo_project']]];
    $form['context']['position_code'] = ['#type' => 'textfield', '#title' => $this->t('Positiecode'), '#maxlength' => 64, '#required' => TRUE];
    $form['context']['location'] = ['#type' => 'textfield', '#title' => $this->t('Locatie'), '#description' => $this->t('Bijvoorbeeld: voorgevel, verdieping 2, kozijn K-2.14.'), '#required' => TRUE];

    $form['specification'] = ['#type' => 'details', '#title' => $this->t('Glasspecificatie'), '#open' => TRUE];
    $form['specification']['application_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Toepassing'),
      '#options' => [
        'standard' => $this->t('Standaard raam/gevel'),
        'door' => $this->t('In deur'),
        'adjacent_door' => $this->t('Naast deur'),
        'low_level' => $this->t('Laag bij vloer/loopzone'),
        'wet_area' => $this->t('Natte ruimte'),
        'ceiling' => $this->t('Tegen of onder plafond'),
        'overhead' => $this->t('Beglazing boven personen'),
        'fall_protection' => $this->t('Vloerafscheiding/doorvalbeveiliging'),
        'fire_separation' => $this->t('Brandwerende scheiding'),
      ],
      '#description' => $this->t('Bepaalt welke technische controles en bewijsstukken verplicht zijn.'),
      '#required' => TRUE,
    ];
    $form['specification']['selection_notice'] = ['#markup' => '<p>' . $this->t('Glastype en opbouw worden automatisch gekozen uit de afzonderlijk geverifieerde productcatalogus.') . '</p>'];
    $form['specification']['frame_material'] = ['#type' => 'select', '#title' => $this->t('Kozijnmateriaal'), '#empty_option' => $this->t('- Onbekend -'), '#options' => ['wood' => $this->t('Hout'), 'aluminium' => $this->t('Aluminium'), 'plastic' => $this->t('Kunststof'), 'steel' => $this->t('Staal'), 'other' => $this->t('Overig')]];
    $form['measurement'] = ['#type' => 'details', '#title' => $this->t('Driepuntsmeting sponning'), '#open' => TRUE];
    $form['measurement']['explanation'] = ['#markup' => '<p>' . $this->t('Meet de breedte boven, midden en onder en de hoogte links, midden en rechts. De kleinste maat minus de vastgelegde aftrek wordt de bestelmaat.') . '</p>'];
    foreach ([
      'width_top_mm' => 'Breedte boven (mm)',
      'width_middle_mm' => 'Breedte midden (mm)',
      'width_bottom_mm' => 'Breedte onder (mm)',
      'height_left_mm' => 'Hoogte links (mm)',
      'height_middle_mm' => 'Hoogte midden (mm)',
      'height_right_mm' => 'Hoogte rechts (mm)',
    ] as $key => $title) {
      $form['measurement'][$key] = ['#type' => 'number', '#title' => $this->t($title), '#min' => 1, '#step' => 1, '#required' => TRUE];
    }
    $form['measurement']['width_deduction_mm'] = ['#type' => 'number', '#title' => $this->t('Aftrek breedte (mm)'), '#min' => 0, '#step' => 1, '#required' => TRUE, '#description' => $this->t('Totale aftrek op de kleinste gemeten breedte.')];
    $form['measurement']['height_deduction_mm'] = ['#type' => 'number', '#title' => $this->t('Aftrek hoogte (mm)'), '#min' => 0, '#step' => 1, '#required' => TRUE, '#description' => $this->t('Totale aftrek op de kleinste gemeten hoogte.')];
    $form['measurement']['quantity'] = ['#type' => 'number', '#title' => $this->t('Aantal'), '#min' => 1, '#step' => 1, '#required' => TRUE, '#default_value' => 1];

    $form['wind'] = ['#type' => 'details', '#title' => $this->t('Verplichte windbelasting en glaskeuze'), '#open' => TRUE];
    $form['wind']['peak_velocity_pressure_kpa'] = ['#type' => 'number', '#title' => $this->t('Pieksnelheidsdruk qₚ(z) (kPa)'), '#step' => 0.001, '#min' => 0.001, '#required' => TRUE];
    $form['wind']['external_pressure_coefficient'] = ['#type' => 'number', '#title' => $this->t('Buitendrukcoëfficiënt cₚₑ'), '#step' => 0.001, '#required' => TRUE];
    $form['wind']['internal_pressure_coefficient'] = ['#type' => 'number', '#title' => $this->t('Binnendrukcoëfficiënt cₚᵢ'), '#step' => 0.001, '#required' => TRUE];
    $form['wind']['wind_partial_factor'] = ['#type' => 'number', '#title' => $this->t('Veiligheidsfactor γ'), '#step' => 0.001, '#min' => 0.001, '#required' => TRUE];
    $form['wind']['wind_standard_ref'] = ['#type' => 'textfield', '#title' => $this->t('Norm en versie'), '#maxlength' => 255, '#required' => TRUE];
    $form['wind']['wind_calculation_ref'] = ['#type' => 'textfield', '#title' => $this->t('Bronberekening'), '#maxlength' => 255, '#required' => TRUE];
    $form['wind']['wind_verified'] = ['#type' => 'checkbox', '#title' => $this->t('Windberekening en glasadvies door deskundige geverifieerd'), '#required' => TRUE];

    $form['evidence'] = ['#type' => 'details', '#title' => $this->t('Classificaties en bewijs'), '#open' => TRUE];
    $form['evidence']['safety_class'] = ['#type' => 'textfield', '#title' => $this->t('Letselveiligheidsclassificatie'), '#maxlength' => 64, '#description' => $this->t('Neem de exacte classificatie uit certificaat of prestatieverklaring over.')];
    $form['evidence']['fire_class'] = ['#type' => 'textfield', '#title' => $this->t('Brandwerendheidsclassificatie'), '#maxlength' => 64, '#description' => $this->t('Bijvoorbeeld de projectspecifiek vereiste E/EW/EI-classificatie met tijdsduur.')];
    $form['evidence']['performance_declaration_ref'] = ['#type' => 'textfield', '#title' => $this->t('Prestatieverklaring/certificaat'), '#maxlength' => 255, '#description' => $this->t('Referentie naar DoP, productcertificaat, berekening of gelijkwaardige onderbouwing.')];

    $form['verification'] = ['#type' => 'details', '#title' => $this->t('Herkomst en verificatie'), '#open' => TRUE];
    $form['verification']['measurement_source'] = ['#type' => 'select', '#title' => $this->t('Bron maatvoering'), '#options' => ['manual' => $this->t('Handmatig ingemeten'), 'drawing' => $this->t('Tekening'), 'photo_ai' => $this->t('Foto/AI'), 'import' => $this->t('Import')], '#required' => TRUE];
    $form['verification']['measurement_verified'] = ['#type' => 'checkbox', '#title' => $this->t('Maatvoering handmatig gecontroleerd')];
    $form['verification']['technical_notes'] = ['#type' => 'textarea', '#title' => $this->t('Technische opmerkingen')];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Glaspositie opslaan en controleren'), '#button_type' => 'primary'];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    try {
      $this->calculateMeasurement($form_state);
      $measurement = $this->calculateMeasurement($form_state);
      $this->selectCandidate($form_state, $measurement);
    }
    catch (\InvalidArgumentException $exception) {
      $form_state->setErrorByName('width_top_mm', $this->t('@message', ['@message' => $exception->getMessage()]));
    }

    if ($form_state->getValue('measurement_source') !== 'manual' && !$form_state->getValue('measurement_verified')) {
      $this->messenger()->addWarning($this->t('Automatisch of extern verkregen maatvoering blijft concept totdat deze handmatig is gecontroleerd.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $verified = (bool) $form_state->getValue('measurement_verified');
    $application = (string) $form_state->getValue('application_type');
    $measurement = $this->calculateMeasurement($form_state);
    $candidate = $this->selectCandidate($form_state, $measurement);
    $glassType = (string) $candidate['glass_type'];
    $wind = $this->calculateWind($form_state, $candidate);
    $result = $this->calculator->calculate(
      $measurement['width_mm'],
      $measurement['height_mm'],
      (int) $form_state->getValue('quantity'),
      (string) $candidate['composition'],
    );
    $technical = $this->technicalRules->evaluate([
      'application_type' => $application,
      'glass_type' => $glassType,
      'measurement_verified' => $verified,
      'safety_class' => $candidate['safety_class'] ?? '',
      'fire_class' => $candidate['fire_class'] ?? '',
      'performance_declaration_ref' => $form_state->getValue('performance_declaration_ref'),
      'wind_check_required' => TRUE,
      'wind_check_state' => $wind['state'],
    ]);

    $this->repository->insert([
      'uuid' => $this->uuid->generate(),
      'building_nid' => (int) $form_state->getValue('building_nid'),
      'project_nid' => $form_state->getValue('project_nid') ? (int) $form_state->getValue('project_nid') : NULL,
      'position_code' => trim((string) $form_state->getValue('position_code')),
      'location' => trim((string) $form_state->getValue('location')),
      'frame_material' => $form_state->getValue('frame_material') ?: NULL,
      'application_type' => $application,
      'glass_type' => $glassType,
      'composition' => (string) $candidate['composition'],
      'safety_class' => trim((string) ($candidate['safety_class'] ?? '')) ?: NULL,
      'fire_class' => trim((string) ($candidate['fire_class'] ?? '')) ?: NULL,
      'performance_declaration_ref' => trim((string) $form_state->getValue('performance_declaration_ref')) ?: NULL,
      'width_top_mm' => (int) $form_state->getValue('width_top_mm'),
      'width_middle_mm' => (int) $form_state->getValue('width_middle_mm'),
      'width_bottom_mm' => (int) $form_state->getValue('width_bottom_mm'),
      'height_left_mm' => (int) $form_state->getValue('height_left_mm'),
      'height_middle_mm' => (int) $form_state->getValue('height_middle_mm'),
      'height_right_mm' => (int) $form_state->getValue('height_right_mm'),
      'width_deduction_mm' => (int) $form_state->getValue('width_deduction_mm'),
      'height_deduction_mm' => (int) $form_state->getValue('height_deduction_mm'),
      'width_spread_mm' => $measurement['width_spread_mm'],
      'height_spread_mm' => $measurement['height_spread_mm'],
      'width_mm' => $measurement['width_mm'],
      'height_mm' => $measurement['height_mm'],
      'quantity' => (int) $form_state->getValue('quantity'),
      'area_m2' => $result['area_m2'],
      'perimeter_m' => $result['perimeter_m'],
      'estimated_weight_kg' => $result['estimated_weight_kg'] ?: NULL,
      'wind_check_required' => 1,
      'peak_velocity_pressure_kpa' => (float) $form_state->getValue('peak_velocity_pressure_kpa'),
      'external_pressure_coefficient' => (float) $form_state->getValue('external_pressure_coefficient'),
      'internal_pressure_coefficient' => (float) $form_state->getValue('internal_pressure_coefficient'),
      'wind_partial_factor' => (float) $form_state->getValue('wind_partial_factor'),
      'design_wind_pressure_kpa' => $wind['design_pressure_kpa'],
      'glass_wind_resistance_kpa' => (float) $candidate['wind_resistance_kpa'],
      'wind_utilization' => $wind['utilization'],
      'wind_standard_ref' => trim((string) $form_state->getValue('wind_standard_ref')),
      'wind_calculation_ref' => trim((string) $form_state->getValue('wind_calculation_ref')),
      'wind_verified' => 1,
      'recommended_glass_ref' => (string) $candidate['product_code'] . ' — ' . (string) $candidate['label'],
      'measurement_source' => (string) $form_state->getValue('measurement_source'),
      'measurement_verified' => $verified ? 1 : 0,
      'technical_status' => $verified ? 'measured' : 'concept',
      'technical_check_state' => $technical['state'],
      'technical_notes' => trim(implode("\n", array_filter([
        (string) $form_state->getValue('technical_notes'),
        ...$measurement['warnings'],
        ...$result['warnings'],
        ...$wind['issues'],
        ...$technical['issues'],
      ]))),
      'created_by' => (int) $this->currentUser()->id(),
    ]);

    foreach ($technical['issues'] as $issue) {
      $this->messenger()->addWarning($this->t('@issue', ['@issue' => $issue]));
    }
    $this->messenger()->addStatus($this->t('Glaspositie opgeslagen: @area m², circa @weight kg. Technische voorcontrole: @state.', [
      '@area' => $result['area_m2'],
      '@weight' => $result['estimated_weight_kg'],
      '@state' => $technical['state'],
    ]));
    $form_state->setRedirect('brebo_glass.position_overview');
  }

  /**
   * @return array{width_mm: int, height_mm: int, width_spread_mm: int, height_spread_mm: int, warnings: string[]}
   */
  /**
   * @return array{design_pressure_kpa: float, utilization: float, state: string, issues: string[]}
   */
  private function calculateWind(FormStateInterface $formState, array $candidate): array {
    return $this->windCalculator->calculate(
      (float) $formState->getValue('peak_velocity_pressure_kpa'),
      (float) $formState->getValue('external_pressure_coefficient'),
      (float) $formState->getValue('internal_pressure_coefficient'),
      (float) $formState->getValue('wind_partial_factor'),
      (float) $candidate['wind_resistance_kpa'],
      (string) $formState->getValue('wind_standard_ref'),
      (string) $formState->getValue('wind_calculation_ref'),
      (bool) $formState->getValue('wind_verified'),
    );
  }

  /**
   * @param array{width_mm: int, height_mm: int} $measurement
   *
   * @return array<string, mixed>
   */
  private function selectCandidate(FormStateInterface $formState, array $measurement): array {
    $designPressure = $this->windCalculator->designPressure(
      (float) $formState->getValue('peak_velocity_pressure_kpa'),
      (float) $formState->getValue('external_pressure_coefficient'),
      (float) $formState->getValue('internal_pressure_coefficient'),
      (float) $formState->getValue('wind_partial_factor'),
    );
    $selection = $this->candidateSelector->select(
      array_values($this->productRepository->activeVerifiedCandidates()),
      $designPressure,
      $measurement['width_mm'],
      $measurement['height_mm'],
      (string) $formState->getValue('application_type'),
    );
    if ($selection['recommended'] === NULL) {
      throw new \InvalidArgumentException('Geen geverifieerd glasproduct voldoet aan winddruk, bestelmaat en toepassing.');
    }
    return $selection['recommended'];
  }

  private function calculateMeasurement(FormStateInterface $formState): array {
    return $this->measurementCalculator->calculate(
      [
        (int) $formState->getValue('width_top_mm'),
        (int) $formState->getValue('width_middle_mm'),
        (int) $formState->getValue('width_bottom_mm'),
      ],
      [
        (int) $formState->getValue('height_left_mm'),
        (int) $formState->getValue('height_middle_mm'),
        (int) $formState->getValue('height_right_mm'),
      ],
      (int) $formState->getValue('width_deduction_mm'),
      (int) $formState->getValue('height_deduction_mm'),
    );
  }

}
