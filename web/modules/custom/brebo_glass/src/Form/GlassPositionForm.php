<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Form;

use Drupal\brebo_glass\Service\GlassPositionRepository;
use Drupal\brebo_glass\Service\GlassSpecificationCalculator;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Captures a glass position in the canonical BREBO object structure.
 */
final class GlassPositionForm extends FormBase {

  public function __construct(
    private readonly GlassSpecificationCalculator $calculator,
    private readonly GlassPositionRepository $repository,
    private readonly UuidInterface $uuid,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_glass.specification_calculator'),
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
    $form['specification']['glass_type'] = ['#type' => 'select', '#title' => $this->t('Glastype'), '#options' => ['single' => $this->t('Enkelglas'), 'insulating' => $this->t('Isolatieglas'), 'laminated' => $this->t('Gelaagd glas'), 'tempered' => $this->t('Gehard glas'), 'fire_resistant' => $this->t('Brandwerend glas'), 'other' => $this->t('Overig')], '#required' => TRUE];
    $form['specification']['composition'] = ['#type' => 'textfield', '#title' => $this->t('Opbouw'), '#description' => $this->t('Bijvoorbeeld 4-16-4 of 44.2-15-6.'), '#required' => TRUE];
    $form['specification']['frame_material'] = ['#type' => 'select', '#title' => $this->t('Kozijnmateriaal'), '#empty_option' => $this->t('- Onbekend -'), '#options' => ['wood' => $this->t('Hout'), 'aluminium' => $this->t('Aluminium'), 'plastic' => $this->t('Kunststof'), 'steel' => $this->t('Staal'), 'other' => $this->t('Overig')]];
    foreach (['width_mm' => 'Breedte (mm)', 'height_mm' => 'Hoogte (mm)', 'quantity' => 'Aantal'] as $key => $title) {
      $form['specification'][$key] = ['#type' => 'number', '#title' => $this->t($title), '#min' => 1, '#step' => 1, '#required' => TRUE, '#default_value' => $key === 'quantity' ? 1 : NULL];
    }

    $form['verification'] = ['#type' => 'details', '#title' => $this->t('Herkomst en verificatie'), '#open' => TRUE];
    $form['verification']['measurement_source'] = ['#type' => 'select', '#title' => $this->t('Bron maatvoering'), '#options' => ['manual' => $this->t('Handmatig ingemeten'), 'drawing' => $this->t('Tekening'), 'photo_ai' => $this->t('Foto/AI'), 'import' => $this->t('Import')], '#required' => TRUE];
    $form['verification']['measurement_verified'] = ['#type' => 'checkbox', '#title' => $this->t('Maatvoering handmatig gecontroleerd')];
    $form['verification']['technical_notes'] = ['#type' => 'textarea', '#title' => $this->t('Technische opmerkingen')];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Glaspositie opslaan'), '#button_type' => 'primary'];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if ($form_state->getValue('measurement_source') !== 'manual' && !$form_state->getValue('measurement_verified')) {
      $this->messenger()->addWarning($this->t('Automatisch of extern verkregen maatvoering blijft concept totdat deze handmatig is gecontroleerd.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $result = $this->calculator->calculate((int) $form_state->getValue('width_mm'), (int) $form_state->getValue('height_mm'), (int) $form_state->getValue('quantity'), (string) $form_state->getValue('composition'));
    $verified = (bool) $form_state->getValue('measurement_verified');
    $this->repository->insert([
      'uuid' => $this->uuid->generate(),
      'building_nid' => (int) $form_state->getValue('building_nid'),
      'project_nid' => $form_state->getValue('project_nid') ? (int) $form_state->getValue('project_nid') : NULL,
      'position_code' => trim((string) $form_state->getValue('position_code')),
      'location' => trim((string) $form_state->getValue('location')),
      'frame_material' => $form_state->getValue('frame_material') ?: NULL,
      'glass_type' => (string) $form_state->getValue('glass_type'),
      'composition' => trim((string) $form_state->getValue('composition')),
      'width_mm' => (int) $form_state->getValue('width_mm'),
      'height_mm' => (int) $form_state->getValue('height_mm'),
      'quantity' => (int) $form_state->getValue('quantity'),
      'area_m2' => $result['area_m2'],
      'perimeter_m' => $result['perimeter_m'],
      'estimated_weight_kg' => $result['estimated_weight_kg'] ?: NULL,
      'measurement_source' => (string) $form_state->getValue('measurement_source'),
      'measurement_verified' => $verified ? 1 : 0,
      'technical_status' => $verified ? 'measured' : 'concept',
      'technical_notes' => trim(implode("\n", array_filter([(string) $form_state->getValue('technical_notes'), ...$result['warnings']]))),
      'created_by' => (int) $this->currentUser()->id(),
    ]);
    $this->messenger()->addStatus($this->t('Glaspositie opgeslagen: @area m², circa @weight kg.', ['@area' => $result['area_m2'], '@weight' => $result['estimated_weight_kg']]));
    $form_state->setRedirect('brebo_glass.position_add');
  }

}

