<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Form;

use Drupal\brebo_calculation\Service\CalculationNormFeedbackService;
use Drupal\brebo_glass\Service\GlassCalculationLineFactory;
use Drupal\brebo_glass\Service\GlassPositionRepository;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Completes a glass position and feeds actuals back to BREBO norms. */
final class GlassCompletionForm extends FormBase {
  private int $positionId = 0;

  public function __construct(
    private readonly Connection $database,
    private readonly GlassPositionRepository $positions,
    private readonly GlassCalculationLineFactory $lineFactory,
    private readonly CalculationNormFeedbackService $feedback,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('database'), $container->get('brebo_glass.position_repository'), $container->get('brebo_glass.calculation_line_factory'), $container->get('brebo_calculation.norm_feedback'));
  }

  public function getFormId(): string { return 'brebo_glass_completion_form'; }

  public function buildForm(array $form, FormStateInterface $form_state, ?int $position_id = NULL): array {
    $this->positionId = (int) $position_id;
    $position = $this->positions->find($this->positionId);
    if (!$position) throw new \InvalidArgumentException('Glaspositie bestaat niet.');
    if (!in_array((string) $position['technical_status'], ['approved', 'ordered'], TRUE)) {
      $form['blocked'] = ['#markup' => '<p>Alleen vrijgegeven of bestelde glasposities kunnen als gemonteerd worden afgerond.</p>'];
      return $form;
    }
    $planned = $this->planned($position);
    $form['summary'] = ['#type' => 'item', '#title' => $this->t('Glaspositie'), '#markup' => $this->t('@code · @qty st. · begrote montage @hours uur', ['@code' => $position['position_code'], '@qty' => $position['quantity'], '@hours' => number_format($planned['installation'], 2, ',', '.')])];
    $form['actual_installation_hours'] = ['#type' => 'number', '#title' => $this->t('Werkelijke montage-uren'), '#required' => TRUE, '#min' => 0, '#step' => 0.01];
    $form['actual_sealant_m'] = ['#type' => 'number', '#title' => $this->t('Werkelijk kit-/afdichtingsverbruik (m)'), '#min' => 0, '#step' => 0.01, '#description' => $this->t('Optioneel; alleen invullen als werkelijk verbruik bekend is.')];
    $form['actual_handling_kg'] = ['#type' => 'number', '#title' => $this->t('Werkelijke handling/hijslast (kg)'), '#min' => 0, '#step' => 0.01, '#description' => $this->t('Optioneel; alleen invullen als werkelijk gebruik geregistreerd is.')];
    $form['note'] = ['#type' => 'textarea', '#title' => $this->t('Uitvoeringsnotitie'), '#description' => $this->t('Leg afwijkende omstandigheden vast; deze context helpt bij latere normbeoordeling.')];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Gemonteerd melden en nacalculeren'), '#button_type' => 'primary'];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $position = $this->positions->find($this->positionId);
    if (!$position) return;
    $planned = $this->planned($position);
    $context = ['glass_type' => $position['glass_type'] ?? '', 'application_type' => $position['application_type'] ?? '', 'area_m2' => (float) $position['area_m2'], 'weight_kg' => (float) ($position['estimated_weight_kg'] ?? 0), 'note' => trim((string) $form_state->getValue('note'))];
    $projectId = !empty($position['project_nid']) ? (int) $position['project_nid'] : NULL;
    $this->feedback->record('glass', 'installation_total_hours', $planned['installation'], (float) $form_state->getValue('actual_installation_hours'), 'uur', 'brebo_glass_position', (string) $this->positionId, $projectId, $context);
    if ($form_state->getValue('actual_sealant_m') !== '' && $planned['sealant'] !== NULL) $this->feedback->record('glass', 'sealant_total_m', $planned['sealant'], (float) $form_state->getValue('actual_sealant_m'), 'm', 'brebo_glass_position', (string) $this->positionId, $projectId, $context);
    if ($form_state->getValue('actual_handling_kg') !== '' && $planned['handling'] !== NULL) $this->feedback->record('glass', 'handling_total_kg', $planned['handling'], (float) $form_state->getValue('actual_handling_kg'), 'kg', 'brebo_glass_position', (string) $this->positionId, $projectId, $context);
    $this->database->update('brebo_glass_position')->fields(['technical_status' => 'installed', 'changed' => time()])->condition('id', $this->positionId)->execute();
    $this->messenger()->addStatus($this->t('Glaspositie is gemonteerd gemeld; werkelijke waarden zijn aan de BREBO normanalyse toegevoegd.'));
    $form_state->setRedirect('brebo_glass.position_overview');
  }

  /** @param array<string,mixed> $position @return array{installation:float,sealant:?float,handling:?float} */
  private function planned(array $position): array {
    $result = ['installation' => 0.0, 'sealant' => NULL, 'handling' => NULL];
    foreach ($this->lineFactory->build($position) as $line) {
      if ($line['key'] === 'installation') $result['installation'] = (float) $line['quantity'];
      if ($line['key'] === 'sealant') $result['sealant'] = (float) $line['quantity'];
      if ($line['key'] === 'handling') $result['handling'] = (float) $line['quantity'];
    }
    return $result;
  }
}
