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
    $form['workbench']['meta'] = ['#markup' => '<div class="brebo-calc-workbench__meta"><span><strong>Versie</strong> ' . htmlspecialchars((string) $version['version']) . '</span><span><strong>Status</strong> ' . htmlspecialchars((string) $version['status']) . '</span><span><strong>Classificatie</strong> ' . htmlspecialchars(strtoupper((string) $version['classification_system'])) . '</span><span class="' . ($locked ? 'is-locked' : 'is-open') . '">' . ($locked ? '🔒 Vergrendeld' : ($editable ? '● Bewerkbaar' : '○ Alleen lezen')) . '</span></div>'];
    $form['workbench']['navigation'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-calc-workbench__navigation']],
      '#weight' => -20,
    ];
    $form['workbench']['navigation']['subcalculations'] = [
      '#type' => 'link', '#title' => 'Deelcalculaties', '#url' => Url::fromRoute('brebo_calculation.subcalculations', ['node' => $node->id()]), '#attributes' => ['class' => ['button', 'button--primary']],
    ];
    $form['workbench']['navigation']['recipes'] = [
      '#type' => 'link', '#title' => 'Recept plaatsen', '#url' => Url::fromRoute('brebo_calculation.recipe_place', ['node' => $node->id()]), '#attributes' => ['class' => ['button']],
    ];
    $form['workbench']['navigation']['structure'] = [
      '#type' => 'link', '#title' => 'Calculatiestructuur', '#url' => Url::fromRoute('brebo_calculation.structure', ['node' => $node->id()]), '#attributes' => ['class' => ['button']],
    ];
    $form['workbench']['navigation']['parameters'] = [
      '#type' => 'link', '#title' => 'Parameters & opslagen', '#url' => Url::fromRoute('brebo_calculation.parameters', ['node' => $node->id()]), '#attributes' => ['class' => ['button']],
    ];
    $form['workbench']['messages'] = ['#type' => 'container', '#attributes' => ['class' => ['brebo-calc-workbench__ajax-message']]];
    if ($form_state->get('ajax_message')) {
      $form['workbench']['messages']['text'] = ['#markup' => '<div class="messages messages--status">' . htmlspecialchars((string) $form_state->get('ajax_message')) . '</div>'];
    }

    $structure = $this->database->select('brebo_calculation_structure', 's')->fields('s')->condition('calculation_id', (int) $node->id())->condition('version', $version['version'])->orderBy('sort_order')->orderBy('depth')->execute()->fetchAllAssoc('node_key', \PDO::FETCH_ASSOC);
    if (!$structure) {
      $structureUrl = Url::fromRoute('brebo_calculation.structure', ['node' => $node->id()])->toString();
      $form['workbench']['empty_state'] = [
        '#markup' => '<div class="brebo-calc-empty-state"><strong>Start met de calculatiestructuur.</strong><p>Maak eerst een hoofdgroep en paragraaf aan. Daarna voeg je hier direct calculatieregels of recepten toe.</p><a class="button button--primary" href="' . htmlspecialchars($structureUrl) . '">Structuur aanmaken</a></div>',
      ];
    }

    $rows = $this->database->select('brebo_calculation_row_domain', 'r')->fields('r')->condition('calculation_id', (int) $node->id())->condition('version', $version['version'])->orderBy('calc_line_id')->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $recipeInstances = $this->database->select('brebo_calculation_recipe_instance', 'i')->fields('i')->condition('calculation_id', (int) $node->id())->condition('calculation_version', $version['version'])->orderBy('paragraph_key')->orderBy('sort_order')->orderBy('id')->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $recipeLinesByInstance = [];
    if ($recipeInstances) {
      $instanceIds = array_map(static fn (array $instance): int => (int) $instance['id'], $recipeInstances);
      $recipeLineResult = $this->database->select('brebo_calculation_recipe_instance_line', 'l')->fields('l')->condition('recipe_instance_id', $instanceIds, 'IN')->orderBy('recipe_instance_id')->orderBy('sort_order')->orderBy('id')->execute()->fetchAll(\PDO::FETCH_ASSOC);
      foreach ($recipeLineResult as $recipeLine) {
        $recipeLinesByInstance[(int) $recipeLine['recipe_instance_id']][] = $recipeLine;
      }
    }

    $form['workbench']['grid'] = ['#type' => 'table', '#header' => ['Code','Omschrijving','Eenheid','Aantal','Arbeid','Materiaal','Materieel','Onderaanneming','Overig','Eenheidsprijs','Totaal','Acties'], '#attributes' => ['class' => ['brebo-calc-workbench__grid']]];
    foreach ($structure as $key => $item) {
      $depth = (int) $item['depth'];
      $isParagraph = (string) $item['node_type'] === 'paragraph';
      $operations = ['#markup' => ''];
      if ($isParagraph && $editable) {
        $operations = [
          '#type' => 'submit', '#value' => '+ Regel', '#submit' => ['::addRow'], '#paragraph_key' => (string) $key, '#limit_validation_errors' => [],
          '#ajax' => ['callback' => '::ajaxRefresh', 'wrapper' => 'brebo-calculation-workbench', 'progress' => ['type' => 'throbber', 'message' => 'Calculatieregel toevoegen…']],
        ];
      }

      $form['workbench']['grid']['structure_' . $key] = [
        '#attributes' => ['class' => ['brebo-calc-workbench__structure','depth-' . $depth], 'data-structure-key' => (string) $key, 'data-parent-key' => (string) ($item['parent_key'] ?? '')],
        'code' => ['#markup' => htmlspecialchars((string) ($item['code'] ?: ''))],
        'description' => ['#markup' => '<button type="button" class="brebo-calc-collapse-toggle" aria-expanded="true" title="In-/uitklappen">▾</button><strong>' . htmlspecialchars((string) $item['label']) . '</strong>'],
        'unit' => ['#markup' => ''], 'quantity' => ['#markup' => ''], 'labour' => ['#markup' => ''], 'material' => ['#markup' => ''], 'equipment' => ['#markup' => ''], 'subcontracting' => ['#markup' => ''], 'other' => ['#markup' => ''], 'unit_total' => ['#markup' => ''],
        'total' => ['#markup' => '<strong class="brebo-calc-structure-subtotal">€ 0,00</strong>'], 'operations' => $operations,
      ];

      foreach ($rows as $row) {
        if ((string) $row['paragraph_key'] !== (string) $key) { continue; }
        $lineId = (int) $row['calc_line_id'];
        $quantity = (float) ($row['quantity'] ?? 0);
        $directUnit = (float) $row['labour_unit_cost'] + (float) $row['material_unit_cost'] + (float) $row['equipment_unit_cost'] + (float) $row['subcontracting_unit_cost'] + (float) $row['other_unit_cost'];
        $lineTotal = $quantity * $directUnit;
        $form['workbench']['grid']['line_' . $lineId] = [
          '#attributes' => ['class' => ['brebo-calc-workbench__line','rule-' . str_replace('_','-',(string) $row['rule_type'])], 'data-structure-key' => (string) $key, 'data-line-id' => (string) $lineId, 'data-block-type' => 'row'],
          'code' => ['#markup' => htmlspecialchars((string) ($row['code'] ?? ''))], 'description' => ['#markup' => htmlspecialchars((string) ($row['description'] ?? 'Calculatieregel'))], 'unit' => ['#markup' => htmlspecialchars((string) ($row['unit'] ?? ''))],
          'quantity' => $this->editableNumber($lineId, 'quantity', $quantity, $editable, '0.0001'), 'labour' => $this->editableNumber($lineId, 'labour_unit_cost', (float) $row['labour_unit_cost'], $editable), 'material' => $this->editableNumber($lineId, 'material_unit_cost', (float) $row['material_unit_cost'], $editable), 'equipment' => $this->editableNumber($lineId, 'equipment_unit_cost', (float) $row['equipment_unit_cost'], $editable), 'subcontracting' => $this->editableNumber($lineId, 'subcontracting_unit_cost', (float) $row['subcontracting_unit_cost'], $editable), 'other' => $this->editableNumber($lineId, 'other_unit_cost', (float) $row['other_unit_cost'], $editable),
          'unit_total' => ['#markup' => '€ ' . number_format($directUnit, 2, ',', '.')], 'total' => ['#markup' => '<strong>€ ' . number_format($lineTotal, 2, ',', '.') . '</strong>'], 'operations' => ['#type' => 'link', '#title' => 'Prijsbronnen', '#url' => Url::fromRoute('brebo_calculation.price_sources', ['node' => $node->id(), 'line' => $lineId])],
        ];
      }

      foreach ($recipeInstances as $instance) {
        if ((string) $instance['paragraph_key'] !== (string) $key) { continue; }
        $instanceId = (int) $instance['id'];
        $instanceLines = $recipeLinesByInstance[$instanceId] ?? [];
        $recipeTotal = $this->recipeInstanceTotal($instanceLines);
        $form['workbench']['grid']['recipe_' . $instanceId] = [
          '#attributes' => ['class' => ['brebo-calc-workbench__recipe'], 'data-structure-key' => (string) $key, 'data-recipe-instance-id' => (string) $instanceId, 'data-block-type' => 'recipe'],
          'code' => ['#markup' => '<span class="brebo-calc-recipe-badge">RECEPT</span>'],
          'description' => ['#markup' => '<button type="button" class="brebo-calc-collapse-toggle" aria-expanded="true" title="Receptregels in-/uitklappen">▾</button><strong>' . htmlspecialchars((string) $instance['name']) . '</strong>'],
          'unit' => ['#markup' => htmlspecialchars((string) ($instance['unit'] ?? ''))], 'quantity' => ['#markup' => number_format((float) $instance['quantity'], 4, ',', '.')],
          'labour' => ['#markup' => ''], 'material' => ['#markup' => ''], 'equipment' => ['#markup' => ''], 'subcontracting' => ['#markup' => ''], 'other' => ['#markup' => ''], 'unit_total' => ['#markup' => ''],
          'total' => ['#markup' => '<strong>€ ' . number_format($recipeTotal, 2, ',', '.') . '</strong>'], 'operations' => ['#markup' => '<span class="brebo-calc-recipe-version">snapshot</span>'],
        ];

        foreach ($instanceLines as $recipeLine) {
          $effectiveQuantity = $this->recipeLineQuantity($recipeLine);
          $unitCost = (float) ($recipeLine['unit_cost'] ?? 0);
          $lineTotal = $effectiveQuantity * $unitCost;
          $costs = $this->recipeCostColumns((string) ($recipeLine['line_type'] ?? 'material'), $unitCost);
          $form['workbench']['grid']['recipe_line_' . (int) $recipeLine['id']] = [
            '#attributes' => ['class' => ['brebo-calc-workbench__line', 'brebo-calc-workbench__recipe-line'], 'data-structure-key' => (string) $key, 'data-recipe-instance-id' => (string) $instanceId, 'data-block-type' => 'recipe-line'],
            'code' => ['#markup' => ''], 'description' => ['#markup' => '&nbsp;&nbsp;&nbsp;↳ ' . htmlspecialchars((string) $recipeLine['description'])], 'unit' => ['#markup' => htmlspecialchars((string) ($recipeLine['unit'] ?? ''))], 'quantity' => ['#markup' => number_format($effectiveQuantity, 4, ',', '.')],
            'labour' => ['#markup' => $this->formatMoneyCell($costs['labour'])], 'material' => ['#markup' => $this->formatMoneyCell($costs['material'])], 'equipment' => ['#markup' => $this->formatMoneyCell($costs['equipment'])], 'subcontracting' => ['#markup' => $this->formatMoneyCell($costs['subcontracting'])], 'other' => ['#markup' => $this->formatMoneyCell($costs['other'])],
            'unit_total' => ['#markup' => '€ ' . number_format($unitCost, 2, ',', '.')], 'total' => ['#markup' => '<strong>€ ' . number_format($lineTotal, 2, ',', '.') . '</strong>'], 'operations' => ['#markup' => (int) ($recipeLine['is_custom'] ?? 0) === 1 ? '<small>eigen regel</small>' : ''],
          ];
        }
      }
    }

    $form['workbench']['total'] = ['#markup' => '<div class="brebo-calc-workbench__total"><span><small>Directe kostprijs</small><strong>€ ' . number_format($this->directTotal($rows) + $this->recipeInstancesTotal($recipeLinesByInstance), 2, ',', '.') . '</strong></span></div>'];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {}

  public function addRow(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $paragraphKey = (string) ($trigger['#paragraph_key'] ?? '');
    if ($paragraphKey === '') {
      throw new \RuntimeException('Paragraaf ontbreekt bij het toevoegen van de calculatieregel.');
    }
    $this->rowManager->add((int) $form_state->getValue('calculation_id'), (string) $form_state->getValue('version'), $paragraphKey, $this->currentUser());
    $form_state->set('ajax_message', 'Calculatieregel toegevoegd.');
    $form_state->setRebuild(TRUE);
  }

  public function ajaxRefresh(array &$form, FormStateInterface $form_state): array { return $form['workbench']; }

  /** @return array<string,mixed>|null */
  private function latestVersion(int $calculationId): ?array {
    $row = $this->database->select('brebo_calculation_version', 'v')->fields('v')->condition('calculation_id', $calculationId)->orderBy('id', 'DESC')->range(0, 1)->execute()->fetchAssoc();
    return $row ?: NULL;
  }

  private function editableNumber(int $lineId, string $field, float $value, bool $editable, string $step = '0.01'): array {
    if (!$editable) { return ['#markup' => number_format($value, 4, ',', '.')]; }
    return ['#type' => 'number', '#default_value' => $value, '#step' => $step, '#min' => 0, '#attributes' => ['class' => ['brebo-calc-inline-edit'], 'data-line-id' => (string) $lineId, 'data-field' => $field]];
  }

  /** @param array<int,array<string,mixed>> $rows */
  private function directTotal(array $rows): float {
    $total = 0.0;
    foreach ($rows as $row) {
      $total += (float) ($row['quantity'] ?? 0) * ((float) $row['labour_unit_cost'] + (float) $row['material_unit_cost'] + (float) $row['equipment_unit_cost'] + (float) $row['subcontracting_unit_cost'] + (float) $row['other_unit_cost']);
    }
    return $total;
  }

  /** @param array<int,array<string,mixed>> $lines */
  private function recipeInstanceTotal(array $lines): float {
    $total = 0.0;
    foreach ($lines as $line) { $total += $this->recipeLineQuantity($line) * (float) ($line['unit_cost'] ?? 0); }
    return $total;
  }

  /** @param array<int,array<int,array<string,mixed>>> $linesByInstance */
  private function recipeInstancesTotal(array $linesByInstance): float {
    $total = 0.0;
    foreach ($linesByInstance as $lines) { $total += $this->recipeInstanceTotal($lines); }
    return $total;
  }

  /** @param array<string,mixed> $line */
  private function recipeLineQuantity(array $line): float {
    $quantity = $line['manual_quantity'] !== NULL && $line['manual_quantity'] !== '' ? (float) $line['manual_quantity'] : (float) ($line['calculated_quantity'] ?? 0);
    return $quantity * (1 + ((float) ($line['waste_pct'] ?? 0) / 100));
  }

  /** @return array{labour:float,material:float,equipment:float,subcontracting:float,other:float} */
  private function recipeCostColumns(string $lineType, float $unitCost): array {
    $columns = ['labour' => 0.0, 'material' => 0.0, 'equipment' => 0.0, 'subcontracting' => 0.0, 'other' => 0.0];
    $normalized = strtolower(trim($lineType));
    $target = match ($normalized) {
      'labour', 'arbeid' => 'labour',
      'equipment', 'materieel' => 'equipment',
      'subcontracting', 'onderaanneming', 'onderaannemer' => 'subcontracting',
      'other', 'overig' => 'other',
      default => 'material',
    };
    $columns[$target] = $unitCost;
    return $columns;
  }

  private function formatMoneyCell(float $value): string { return $value === 0.0 ? '' : '€ ' . number_format($value, 2, ',', '.'); }
}
