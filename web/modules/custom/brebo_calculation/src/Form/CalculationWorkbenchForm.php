<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Form;

use Drupal\brebo_calculation\Service\CalculationRowManager;
use Drupal\brebo_calculation\Service\CalculationStructureManager;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** AJAX spreadsheet editor for the active calculation version. */
final class CalculationWorkbenchForm extends FormBase {

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $calculationEntityTypeManager,
    private readonly CalculationRowManager $rowManager,
    private readonly CalculationStructureManager $structureManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('brebo_calculation.row_manager'),
      $container->get('brebo_calculation.structure_manager'),
    );
  }

  public function getFormId(): string { return 'brebo_calculation_workbench_form'; }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_calculation') {
      return ['message' => ['#markup' => '<p>Calculatie niet gevonden.</p>']];
    }
    $version = $this->latestVersion((int) $node->id());
    if ($version === NULL) {
      return ['message' => ['#markup' => '<p>Deze calculatie heeft nog geen domeinversie. Voer eerst de migratie-audit uit.</p>']];
    }
    $locked = $version['locked_at'] !== NULL;
    $editable = !$locked && $version['status'] === 'draft' && $node->access('update') && $this->currentUser()->hasPermission('edit brebo calculation workbench');

    $form['#tree'] = TRUE;
    $form['#attached']['library'][] = 'brebo_calculation/workbench';
    $form['calculation_id'] = ['#type' => 'hidden', '#value' => (int) $node->id()];
    $form['version'] = ['#type' => 'hidden', '#value' => $version['version']];
    $form['workbench'] = ['#type' => 'container', '#attributes' => ['id' => 'brebo-calculation-workbench', 'class' => ['brebo-calc-workbench']]];
    $form['workbench']['meta'] = ['#markup' => '<div class="brebo-calc-workbench__meta"><span><strong>Versie</strong> ' . htmlspecialchars((string) $version['version']) . '</span><span><strong>Status</strong> ' . htmlspecialchars((string) $version['status']) . '</span><span><strong>Classificatie</strong> ' . htmlspecialchars(strtoupper((string) $version['classification_system']) . '</span><span class="' . ($locked ? 'is-locked' : 'is-open') . '">' . ($locked ? '🔒 Vergrendeld' : ($editable ? '● Bewerkbaar' : '○ Alleen lezen')) . '</span></div>'];
    $form['workbench']['navigation'] = ['#type' => 'container', '#attributes' => ['class' => ['brebo-calc-workbench__navigation']], '#weight' => -20];
    $form['workbench']['navigation']['subcalculations'] = ['#type' => 'link', '#title' => 'Deelcalculaties', '#url' => Url::fromRoute('brebo_calculation.subcalculations', ['node' => $node->id()]), '#attributes' => ['class' => ['button', 'button--primary']]];
    $form['workbench']['navigation']['structure'] = ['#type' => 'link', '#title' => 'Calculatiestructuur', '#url' => Url::fromRoute('brebo_calculation.structure', ['node' => $node->id()]), '#attributes' => ['class' => ['button']]];
    $form['workbench']['navigation']['parameters'] = ['#type' => 'link', '#title' => 'Parameters & opslagen', '#url' => Url::fromRoute('brebo_calculation.parameters', ['node' => $node->id()]), '#attributes' => ['class' => ['button']]];
    $form['workbench']['messages'] = ['#type' => 'container', '#attributes' => ['class' => ['brebo-calc-workbench__ajax-message']]];
    if ($form_state->get('ajax_message')) {
      $form['workbench']['messages']['text'] = ['#markup' => '<div class="messages messages--status">' . htmlspecialchars((string) $form_state->get('ajax_message')) . '</div>'];
    }

    $structure = $this->database->select('brebo_calculation_structure', 's')->fields('s')->condition('calculation_id', (int) $node->id())->condition('version', $version['version'])->orderBy('sort_order')->orderBy('depth')->execute()->fetchAllAssoc('node_key', \PDO::FETCH_ASSOC);
    $paragraphOptions = [];
    foreach ($structure as $key => $item) {
      if ($item['node_type'] === 'paragraph') {
        $paragraphOptions[(string) $key] = trim((string) ($item['code'] ?: '') . ' — ' . (string) $item['label'], ' —');
      }
    }

    if (!$structure) {
      $form['workbench']['empty_state'] = ['#markup' => '<div class="brebo-calc-empty-state"><strong>Start met de calculatiestructuur.</strong><p>Maak eerst een hoofdgroep en paragraaf aan. Daarna voeg je hier direct calculatieregels toe.</p><a class="button button--primary" href="' . Url::fromRoute('brebo_calculation.structure', ['node' => $node->id()])->toString() . '">Structuur aanmaken</a></div>'];
    }

    $rows = $this->database->select('brebo_calculation_row_domain', 'r')->fields('r')->condition('calculation_id', (int) $node->id())->condition('version', $version['version'])->orderBy('calc_line_id')->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $form['workbench']['grid'] = ['#type' => 'table', '#header' => ['Code','Omschrijving','Eenheid','Aantal','Arbeid','Materiaal','Materieel','Onderaanneming','Overig','Eenheidsprijs','Totaal','Acties'], '#attributes' => ['class' => ['brebo-calc-workbench__grid']]];
    foreach ($structure as $key => $item) {
      $isParagraph = $item['node_type'] === 'paragraph';
      $form['workbench']['grid']['structure_' . $key] = [
        '#attributes' => ['class' => ['brebo-calc-workbench__structure','depth-' . (int) $item['depth']], 'data-structure-key' => (string) $key, 'data-parent-key' => (string) ($item['parent_key'] ?? '')],
        'code' => ['#markup' => htmlspecialchars((string) ($item['code'] ?: ''))],
        'description' => ['#markup' => '<button type="button" class="brebo-calc-collapse-toggle" aria-expanded="true" title="In-/uitklappen">▾</button><strong>' . htmlspecialchars((string) $item['label']) . '</strong>'],
        'unit' => ['#markup' => ''], 'quantity' => ['#markup' => ''], 'labour' => ['#markup' => ''], 'material' => ['#markup' => ''], 'equipment' => ['#markup' => ''], 'subcontracting' => ['#markup' => ''], 'other' => ['#markup' => ''], 'unit_total' => ['#markup' => ''], 'total' => ['#markup' => '<strong class="brebo-calc-structure-subtotal">€ 0,00</strong>'],
        'operations' => $isParagraph && $editable ? ['#type' => 'submit', '#value' => '+ Regel', '#submit' => ['::addRow'], '#paragraph_key' => (string) $key, '#limit_validation_errors' => [], '#ajax' => $this->ajaxDefinition('Calculatieregel toevoegen…')] : ['#markup' => ''],
      ];
      foreach ($rows as $row) {
        if ((string) $row['paragraph_key'] !== (string) $key) { continue; }
        $lineId = (int) $row['calc_line_id'];
        $quantity = (float) ($row['quantity'] ?? 0);
        $directUnit = (float) $row['labour_unit_cost'] + (float) $row['material_unit_cost'] + (float) $row['equipment_unit_cost'] + (float) $row['subcontracting_unit_cost'] + (float) $row['other_unit_cost'];
        $lineTotal = $quantity * $directUnit;
        $form['workbench']['grid']['line_' . $lineId] = [
          '#attributes' => ['class' => ['brebo-calc-workbench__line','rule-' . str_replace('_','-',(string) $row['rule_type'])], 'data-structure-key' => (string) $key, 'data-line-id' => (string) $lineId],
          'code' => ['#markup' => htmlspecialchars((string) ($row['code'] ?? ''))], 'description' => ['#markup' => htmlspecialchars((string) ($row['description'] ?? 'Nieuwe calculatieregel'))], 'unit' => ['#markup' => htmlspecialchars((string) ($row['unit'] ?? ''))],
          'quantity' => $this->editableNumber($lineId, 'quantity', $quantity, $editable, '0.0001'), 'labour' => $this->editableNumber($lineId, 'labour_unit_cost', (float) $row['labour_unit_cost'], $editable), 'material' => $this->editableNumber($lineId, 'material_unit_cost', (float) $row['material_unit_cost'], $editable), 'equipment' => $this->editableNumber($lineId, 'equipment_unit_cost', (float) $row['equipment_unit_cost'], $editable), 'subcontracting' => $this->editableNumber($lineId, 'subcontracting_unit_cost', (float) $row['subcontracting_unit_cost'], $editable), 'other' => $this->editableNumber($lineId, 'other_unit_cost', (float) $row['other_unit_cost'], $editable),
          'unit_total' => ['#markup' => '€ ' . number_format($directUnit, 2, ',', '.')], 'total' => ['#markup' => '<strong>€ ' . number_format($lineTotal, 2, ',', '.') . '</strong>'],
          'operations' => ['#type' => 'container', '#attributes' => ['class' => ['brebo-calc-row-operations']], 'sources' => ['#type' => 'link', '#title' => 'Prijsbronnen', '#url' => Url::fromRoute('brebo_calculation.price_sources', ['node' => $node->id(), 'line' => $lineId])], 'duplicate' => ['#type' => 'submit', '#value' => 'Dupliceren', '#submit' => ['::duplicateRow'], '#line_id' => $lineId, '#limit_validation_errors' => [], '#disabled' => !$editable, '#ajax' => $this->ajaxDefinition('Regel dupliceren…')], 'delete' => ['#type' => 'submit', '#value' => 'Verwijderen', '#submit' => ['::deleteRow'], '#line_id' => $lineId, '#limit_validation_errors' => [], '#disabled' => !$editable, '#ajax' => $this->ajaxDefinition('Regel verwijderen…')]],
        ];
      }
    }
    $form['workbench']['total'] = ['#markup' => '<div class="brebo-calc-workbench__total"><span><small>Directe kostprijs</small><strong>€ ' . number_format($this->directTotal($rows), 2, ',', '.') . '</strong></span></div>'];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {}

  public function addRow(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $this->rowManager->add((int) $form_state->getValue('calculation_id'), (string) $form_state->getValue('version'), (string) ($trigger['#paragraph_key'] ?? ''), $this->currentUser());
    $form_state->set('ajax_message', 'Calculatieregel toegevoegd.'); $form_state->setRebuild(TRUE);
  }

  public function duplicateRow(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $this->rowManager->duplicate((int) $form_state->getValue('calculation_id'), (string) $form_state->getValue('version'), (int) ($trigger['#line_id'] ?? 0), $this->currentUser());
    $form_state->set('ajax_message', 'Calculatieregel gedupliceerd.'); $form_state->setRebuild(TRUE);
  }

  public function deleteRow(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $this->rowManager->delete((int) $form_state->getValue('calculation_id'), (string) $form_state->getValue('version'), (int) ($trigger['#line_id'] ?? 0), $this->currentUser());
    $form_state->set('ajax_message', 'Calculatieregel verwijderd.'); $form_state->setRebuild(TRUE);
  }

  public function ajaxRefresh(array &$form, FormStateInterface $form_state): array { return $form['workbench']; }
  private function ajaxDefinition(string $message): array { return ['callback' => '::ajaxRefresh', 'wrapper' => 'brebo-calculation-workbench', 'progress' => ['type' => 'throbber', 'message' => $message]]; }

  /** @return array<string,mixed>|null */
  private function latestVersion(int $calculationId): ?array {
    $row = $this->database->select('brebo_calculation_version', 'v')->fields('v')->condition('calculation_id', $calculationId)->orderBy('id', 'DESC')->range(0, 1)->execute()->fetchAssoc(); return $row ?: NULL;
  }
  private function editableNumber(int $lineId, string $field, float $value, bool $editable, string $step = '0.01'): array { if (!$editable) { return ['#markup' => number_format($value, 4, ',', '.')]; } return ['#type' => 'number', '#default_value' => $value, '#step' => $step, '#min' => 0, '#attributes' => ['class' => ['brebo-calc-inline-edit'], 'data-line-id' => (string) $lineId, 'data-field' => $field]]; }
  /** @param array<int,array<string,mixed>> $rows */
  private function directTotal(array $rows): float { $total = 0.0; foreach ($rows as $row) { $total += (float) ($row['quantity'] ?? 0) * ((float) $row['labour_unit_cost'] + (float) $row['material_unit_cost'] + (float) $row['equipment_unit_cost'] + (float) $row['subcontracting_unit_cost'] + (float) $row['other_unit_cost']); } return $total; }
}
