<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Form;

use Drupal\brebo_calculation\Service\CalculationRowManager;
use Drupal\brebo_calculation\Service\CalculationStructureManager;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
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

  public function getFormId(): string {
    return 'brebo_calculation_workbench_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_calculation') {
      return ['message' => ['#markup' => '<p>Calculatie niet gevonden.</p>']];
    }

    $version = $this->latestVersion((int) $node->id());
    if ($version === NULL) {
      return ['message' => ['#markup' => '<p>Deze calculatie heeft nog geen domeinversie. Voer eerst de migratie-audit uit.</p>']];
    }

    $locked = $version['locked_at'] !== NULL;
    $editable = !$locked
      && $version['status'] === 'draft'
      && $node->access('update')
      && $this->currentUser()->hasPermission('edit brebo calculation workbench');

    $form['#tree'] = TRUE;
    $form['#attached']['library'][] = 'brebo_calculation/workbench';
    $form['calculation_id'] = ['#type' => 'hidden', '#value' => (int) $node->id()];
    $form['version'] = ['#type' => 'hidden', '#value' => $version['version']];

    $form['workbench'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'brebo-calculation-workbench', 'class' => ['brebo-calc-workbench']],
    ];
    $form['workbench']['meta'] = [
      '#markup' => '<div class="brebo-calc-workbench__meta">'
        . '<span><strong>Versie</strong> ' . htmlspecialchars((string) $version['version']) . '</span>'
        . '<span><strong>Status</strong> ' . htmlspecialchars((string) $version['status']) . '</span>'
        . '<span><strong>Classificatie</strong> ' . htmlspecialchars(strtoupper((string) $version['classification_system'])) . '</span>'
        . '<span class="' . ($locked ? 'is-locked' : 'is-open') . '">' . ($locked ? '🔒 Vergrendeld' : ($editable ? '● Bewerkbaar' : '○ Alleen lezen')) . '</span>'
        . '</div>',
    ];
    $form['workbench']['messages'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-calc-workbench__ajax-message']],
    ];
    if ($form_state->get('ajax_message')) {
      $form['workbench']['messages']['text'] = [
        '#markup' => '<div class="messages messages--status">' . htmlspecialchars((string) $form_state->get('ajax_message')) . '</div>',
      ];
    }

    $structure = $this->database->select('brebo_calculation_structure', 's')
      ->fields('s')
      ->condition('calculation_id', (int) $node->id())
      ->condition('version', $version['version'])
      ->orderBy('sort_order')
      ->orderBy('depth')
      ->execute()->fetchAllAssoc('node_key', \PDO::FETCH_ASSOC);

    $childCounts = [];
    $mainGroupOptions = [];
    foreach ($structure as $key => $item) {
      if (!empty($item['parent_key'])) {
        $childCounts[(string) $item['parent_key']] = ($childCounts[(string) $item['parent_key']] ?? 0) + 1;
      }
      if ($item['node_type'] === 'main_group') {
        $mainGroupOptions[(string) $key] = trim((string) ($item['code'] ?: '') . ' — ' . (string) $item['label'], ' —');
      }
    }

    $form['workbench']['structure_quick'] = [
      '#type' => 'details',
      '#title' => $this->t('Structuur direct toevoegen'),
      '#open' => !$structure,
      '#attributes' => ['class' => ['brebo-calc-workbench__structure-quick']],
    ];
    $form['workbench']['structure_quick']['main_group'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-calc-quick-row']],
    ];
    $form['workbench']['structure_quick']['main_group']['code'] = [
      '#type' => 'textfield', '#title' => $this->t('Hoofdgroepcode'), '#title_display' => 'invisible', '#placeholder' => $this->t('Code'), '#size' => 10, '#disabled' => !$editable,
    ];
    $form['workbench']['structure_quick']['main_group']['label'] = [
      '#type' => 'textfield', '#title' => $this->t('Hoofdgroepomschrijving'), '#title_display' => 'invisible', '#placeholder' => $this->t('Nieuwe hoofdgroep'), '#size' => 34, '#disabled' => !$editable,
    ];
    $form['workbench']['structure_quick']['main_group']['add'] = [
      '#type' => 'submit', '#value' => $this->t('+ Hoofdgroep'), '#submit' => ['::addMainGroupQuick'], '#disabled' => !$editable,
      '#limit_validation_errors' => [['workbench', 'structure_quick', 'main_group']], '#ajax' => $this->ajaxDefinition($this->t('Hoofdgroep toevoegen…')),
    ];

    $form['workbench']['structure_quick']['paragraph'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-calc-quick-row']],
    ];
    $form['workbench']['structure_quick']['paragraph']['parent'] = [
      '#type' => 'select', '#title' => $this->t('Hoofdgroep'), '#title_display' => 'invisible', '#options' => $mainGroupOptions, '#empty_option' => $this->t('- hoofdgroep -'), '#disabled' => !$editable || !$mainGroupOptions,
    ];
    $form['workbench']['structure_quick']['paragraph']['code'] = [
      '#type' => 'textfield', '#title' => $this->t('Paragraafcode'), '#title_display' => 'invisible', '#placeholder' => $this->t('Code'), '#size' => 10, '#disabled' => !$editable,
    ];
    $form['workbench']['structure_quick']['paragraph']['label'] = [
      '#type' => 'textfield', '#title' => $this->t('Paragraafomschrijving'), '#title_display' => 'invisible', '#placeholder' => $this->t('Nieuwe paragraaf'), '#size' => 34, '#disabled' => !$editable,
    ];
    $form['workbench']['structure_quick']['paragraph']['location_ref'] = [
      '#type' => 'textfield', '#title' => $this->t('Locatie'), '#title_display' => 'invisible', '#placeholder' => $this->t('Gebouwlocatie'), '#size' => 18, '#disabled' => !$editable,
    ];
    $form['workbench']['structure_quick']['paragraph']['add'] = [
      '#type' => 'submit', '#value' => $this->t('+ Paragraaf'), '#submit' => ['::addParagraphQuick'], '#disabled' => !$editable || !$mainGroupOptions,
      '#limit_validation_errors' => [['workbench', 'structure_quick', 'paragraph']], '#ajax' => $this->ajaxDefinition($this->t('Paragraaf toevoegen…')),
    ];

    $leafParagraphOptions = [];
    foreach ($structure as $key => $item) {
      if ($item['node_type'] === 'paragraph' && empty($childCounts[(string) $key])) {
        $leafParagraphOptions[(string) $key] = trim((string) ($item['code'] ?: '') . ' — ' . (string) $item['label'], ' —');
      }
    }

    $domains = $this->database->select('brebo_calculation_row_domain', 'r')
      ->fields('r')
      ->condition('calculation_id', (int) $node->id())
      ->condition('version', $version['version'])
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $lineIds = array_map(static fn (array $row): int => (int) $row['calc_line_id'], $domains);
    $lines = $lineIds ? $this->calculationEntityTypeManager->getStorage('node')->loadMultiple($lineIds) : [];
    $byParagraph = [];
    foreach ($domains as $domain) {
      $byParagraph[(string) $domain['paragraph_key']][] = $domain;
    }

    $form['workbench']['grid'] = [
      '#type' => 'table',
      '#header' => ['Code', 'Omschrijving', 'Locatie', 'Type', 'Aantal', 'EH', 'Arbeid', 'Materiaal', 'Materieel', 'Onderaanneming', 'Overig', 'Kostprijs/EH', 'Totaal', 'Acties'],
      '#attributes' => ['class' => ['brebo-calc-workbench__grid', 'brebo-calc-workbench__grid--editable']],
      '#sticky' => TRUE,
      '#empty' => $this->t('Nog geen calculatiestructuur. Voeg hierboven eerst een hoofdgroep toe.'),
    ];

    $grandTotal = 0.0;
    foreach ($structure as $key => $item) {
      $depth = (int) $item['depth'];
      $isLeafParagraph = $item['node_type'] === 'paragraph' && empty($childCounts[(string) $key]);
      $structureKey = 'structure_' . md5((string) $key);
      $form['workbench']['grid'][$structureKey] = [
        '#attributes' => [
          'class' => ['brebo-calc-workbench__structure', 'depth-' . $depth, 'type-' . $item['node_type']],
          'data-structure-key' => (string) $key,
        ],
        'code' => ['#markup' => htmlspecialchars((string) ($item['code'] ?: '—'))],
        'description' => ['#markup' => '<strong>' . str_repeat('&nbsp;&nbsp;&nbsp;', $depth) . htmlspecialchars((string) $item['label']) . '</strong>'],
        'location' => ['#markup' => htmlspecialchars((string) ($item['location_ref'] ?: '—'))],
        'type' => ['#markup' => htmlspecialchars(strtoupper((string) $item['classification_system']))],
        'quantity' => ['#markup' => ''], 'unit' => ['#markup' => ''], 'labour' => ['#markup' => ''], 'material' => ['#markup' => ''], 'equipment' => ['#markup' => ''], 'subcontracting' => ['#markup' => ''], 'other' => ['#markup' => ''], 'unit_total' => ['#markup' => ''], 'total' => ['#markup' => ''],
        'operations' => $isLeafParagraph ? [
          '#type' => 'submit', '#value' => $this->t('+ Regel'), '#name' => 'add_' . md5((string) $key), '#submit' => ['::addRow'], '#paragraph_key' => (string) $key,
          '#disabled' => !$editable, '#limit_validation_errors' => [], '#ajax' => $this->ajaxDefinition($this->t('Regel toevoegen…')), '#attributes' => ['class' => ['button', 'button--small', 'brebo-calc-row-action']],
        ] : ['#markup' => ''],
      ];

      foreach ($byParagraph[$key] ?? [] as $domain) {
        $lineId = (int) $domain['calc_line_id'];
        $line = $lines[$lineId] ?? NULL;
        $quantity = $line instanceof NodeInterface ? (float) ($line->get('field_brebo_contract_quantity')->value ?? 0) : 0.0;
        $unit = $line instanceof NodeInterface ? (string) ($line->get('field_brebo_unit')->value ?? '') : '';
        $description = $line instanceof NodeInterface ? (string) ($line->get('field_brebo_line_description')->value ?? $line->label()) : ('Regel ' . $lineId);
        $labour = (float) $domain['labour_unit_cost'];
        $material = (float) $domain['material_unit_cost'];
        $equipment = (float) $domain['equipment_unit_cost'];
        $subcontracting = (float) $domain['subcontracting_unit_cost'];
        $other = (float) $domain['other_unit_cost'];
        $unitTotal = $labour + $material + $equipment + $subcontracting + $other;
        $total = $quantity * $unitTotal;
        if (!in_array($domain['rule_type'], ['option', 'note'], TRUE)) {
          $grandTotal += $total;
        }

        $rowKey = 'line_' . $lineId;
        $common = ['#disabled' => !$editable, '#attributes' => ['class' => ['brebo-calc-cell']]];
        $form['workbench']['grid'][$rowKey] = [
          '#attributes' => ['class' => ['brebo-calc-workbench__line', 'rule-' . $domain['rule_type']], 'data-line-id' => $lineId],
          'code' => ['#markup' => ''],
          'description' => ['#type' => 'textfield', '#default_value' => $description, '#size' => 38] + $common,
          'location_ref' => ['#type' => 'textfield', '#default_value' => (string) ($domain['location_ref'] ?? ''), '#size' => 16] + $common,
          'rule_type' => ['#type' => 'select', '#default_value' => $domain['rule_type'], '#options' => ['normal' => 'Normaal', 'allowance' => 'Stelpost', 'option' => 'Optie', 'note' => 'Notitie', 'distributed' => 'Verdisconterend', 'adjustable' => 'Verrekenbaar']] + $common,
          'quantity' => ['#type' => 'number', '#default_value' => $quantity, '#step' => '0.0001', '#min' => 0] + $common,
          'unit' => ['#type' => 'textfield', '#default_value' => $unit, '#size' => 6, '#maxlength' => 16] + $common,
          'labour_unit_cost' => ['#type' => 'number', '#default_value' => $labour, '#step' => '0.0001', '#min' => 0] + $common,
          'material_unit_cost' => ['#type' => 'number', '#default_value' => $material, '#step' => '0.0001', '#min' => 0] + $common,
          'equipment_unit_cost' => ['#type' => 'number', '#default_value' => $equipment, '#step' => '0.0001', '#min' => 0] + $common,
          'subcontracting_unit_cost' => ['#type' => 'number', '#default_value' => $subcontracting, '#step' => '0.0001', '#min' => 0] + $common,
          'other_unit_cost' => ['#type' => 'number', '#default_value' => $other, '#step' => '0.0001', '#min' => 0] + $common,
          'unit_total' => ['#markup' => '<span class="brebo-calc-derived">€ ' . number_format($unitTotal, 2, ',', '.') . '</span>'],
          'total' => ['#markup' => '<strong class="brebo-calc-derived">€ ' . number_format($total, 2, ',', '.') . '</strong>'],
          'operations' => [
            '#type' => 'container', '#attributes' => ['class' => ['brebo-calc-row-operations']],
            'duplicate' => ['#type' => 'submit', '#value' => '⧉', '#title' => $this->t('Regel dupliceren'), '#name' => 'duplicate_' . $lineId, '#submit' => ['::duplicateRow'], '#line_id' => $lineId, '#disabled' => !$editable, '#limit_validation_errors' => [], '#ajax' => $this->ajaxDefinition($this->t('Dupliceren…')), '#attributes' => ['class' => ['button', 'button--small', 'brebo-calc-row-action']]],
            'target' => ['#type' => 'select', '#title' => $this->t('Verplaats naar'), '#title_display' => 'invisible', '#options' => $leafParagraphOptions, '#default_value' => (string) $key, '#disabled' => !$editable, '#attributes' => ['class' => ['brebo-calc-move-target']]],
            'move' => ['#type' => 'submit', '#value' => '↕', '#title' => $this->t('Naar geselecteerde paragraaf verplaatsen'), '#name' => 'move_' . $lineId, '#submit' => ['::moveRow'], '#line_id' => $lineId, '#disabled' => !$editable, '#limit_validation_errors' => [], '#ajax' => $this->ajaxDefinition($this->t('Verplaatsen…')), '#attributes' => ['class' => ['button', 'button--small', 'brebo-calc-row-action']]],
            'delete' => ['#type' => 'submit', '#value' => '×', '#title' => $this->t('Regel verwijderen'), '#name' => 'delete_' . $lineId, '#submit' => ['::deleteRow'], '#line_id' => $lineId, '#disabled' => !$editable, '#limit_validation_errors' => [], '#ajax' => $this->ajaxDefinition($this->t('Verwijderen…')), '#attributes' => ['class' => ['button', 'button--small', 'button--danger', 'brebo-calc-row-action'], 'data-brebo-confirm-delete' => $this->t('Deze calculatieregel verwijderen?')]],
          ],
        ];
      }
    }

    $form['workbench']['total'] = ['#markup' => '<div class="brebo-calc-workbench__total"><span>Calculatietotaal excl. opties</span><strong>€ ' . number_format($grandTotal, 2, ',', '.') . '</strong></div>'];
    $form['workbench']['actions'] = ['#type' => 'actions', '#attributes' => ['class' => ['brebo-calc-workbench__actions']]];
    $form['workbench']['actions']['save'] = ['#type' => 'submit', '#value' => $this->t('Wijzigingen opslaan'), '#button_type' => 'primary', '#disabled' => !$editable, '#ajax' => $this->ajaxDefinition($this->t('Herberekenen…'))];
    if (!$editable && !$locked) {
      $form['workbench']['actions']['notice'] = ['#markup' => '<span class="description">Alleen een ontgrendelde conceptversie met bewerkrechten kan worden aangepast.</span>'];
    }
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if (!$this->persistGrid($form_state)) {
      return;
    }
    $form_state->set('ajax_message', 'Wijzigingen opgeslagen en calculatie opnieuw doorgerekend.');
    $form_state->setRebuild(TRUE);
  }

  public function addMainGroupQuick(array &$form, FormStateInterface $form_state): void {
    $values = (array) $form_state->getValue(['workbench', 'structure_quick', 'main_group']);
    $this->structureManager->addMainGroup((int) $form_state->getValue('calculation_id'), (string) $form_state->getValue('version'), (string) ($values['code'] ?? ''), (string) ($values['label'] ?? ''), $this->currentUser());
    $form_state->set('ajax_message', 'Hoofdgroep toegevoegd.');
    $form_state->setRebuild(TRUE);
  }

  public function addParagraphQuick(array &$form, FormStateInterface $form_state): void {
    $values = (array) $form_state->getValue(['workbench', 'structure_quick', 'paragraph']);
    $this->structureManager->addParagraph((int) $form_state->getValue('calculation_id'), (string) $form_state->getValue('version'), (string) ($values['parent'] ?? ''), (string) ($values['code'] ?? ''), (string) ($values['label'] ?? ''), trim((string) ($values['location_ref'] ?? '')) ?: NULL, $this->currentUser());
    $form_state->set('ajax_message', 'Paragraaf toegevoegd.');
    $form_state->setRebuild(TRUE);
  }

  public function addRow(array &$form, FormStateInterface $form_state): void {
    if (!$this->persistGrid($form_state)) {
      return;
    }
    $trigger = $form_state->getTriggeringElement();
    $this->rowManager->add((int) $form_state->getValue('calculation_id'), (string) $form_state->getValue('version'), (string) ($trigger['#paragraph_key'] ?? ''), $this->currentUser());
    $form_state->set('ajax_message', 'Nieuwe calculatieregel toegevoegd.');
    $form_state->setRebuild(TRUE);
  }

  public function duplicateRow(array &$form, FormStateInterface $form_state): void {
    if (!$this->persistGrid($form_state)) {
      return;
    }
    $trigger = $form_state->getTriggeringElement();
    $this->rowManager->duplicate((int) $form_state->getValue('calculation_id'), (string) $form_state->getValue('version'), (int) ($trigger['#line_id'] ?? 0), $this->currentUser());
    $form_state->set('ajax_message', 'Calculatieregel gedupliceerd.');
    $form_state->setRebuild(TRUE);
  }

  public function moveRow(array &$form, FormStateInterface $form_state): void {
    if (!$this->persistGrid($form_state)) {
      return;
    }
    $trigger = $form_state->getTriggeringElement();
    $parents = $trigger['#array_parents'] ?? [];
    array_pop($parents);
    $parents[] = 'target';
    $this->rowManager->move((int) $form_state->getValue('calculation_id'), (string) $form_state->getValue('version'), (int) ($trigger['#line_id'] ?? 0), (string) $form_state->getValue($parents), $this->currentUser());
    $form_state->set('ajax_message', 'Calculatieregel verplaatst.');
    $form_state->setRebuild(TRUE);
  }

  public function deleteRow(array &$form, FormStateInterface $form_state): void {
    if (!$this->persistGrid($form_state)) {
      return;
    }
    $trigger = $form_state->getTriggeringElement();
    $this->rowManager->delete((int) $form_state->getValue('calculation_id'), (string) $form_state->getValue('version'), (int) ($trigger['#line_id'] ?? 0), $this->currentUser());
    $form_state->set('ajax_message', 'Calculatieregel verwijderd.');
    $form_state->setRebuild(TRUE);
  }

  public function ajaxRefresh(array &$form, FormStateInterface $form_state): array {
    return $form['workbench'];
  }

  private function persistGrid(FormStateInterface $form_state): bool {
    $calculationId = (int) $form_state->getValue('calculation_id');
    $version = (string) $form_state->getValue('version');
    $current = $this->latestVersion($calculationId);
    if ($current === NULL || $current['version'] !== $version || $current['locked_at'] !== NULL || $current['status'] !== 'draft') {
      $form_state->setErrorByName('version', $this->t('Deze calculatieversie is inmiddels gewijzigd, vastgesteld of vergrendeld. Vernieuw de pagina.'));
      return FALSE;
    }
    $calculation = $this->calculationEntityTypeManager->getStorage('node')->load($calculationId);
    if (!$calculation instanceof NodeInterface || !$calculation->access('update') || !$this->currentUser()->hasPermission('edit brebo calculation workbench')) {
      $form_state->setErrorByName('calculation_id', $this->t('U heeft geen recht om deze calculatie te wijzigen.'));
      return FALSE;
    }

    $allowedRuleTypes = ['normal', 'allowance', 'option', 'note', 'distributed', 'adjustable'];
    $gridValues = (array) ($form_state->getValue(['workbench', 'grid']) ?? []);
    $storage = $this->calculationEntityTypeManager->getStorage('node');
    $transaction = $this->database->startTransaction();
    try {
      foreach ($gridValues as $key => $values) {
        if (!is_string($key) || !str_starts_with($key, 'line_') || !is_array($values)) {
          continue;
        }
        $lineId = (int) substr($key, 5);
        $line = $storage->load($lineId);
        if (!$line instanceof NodeInterface || $line->bundle() !== 'brebo_calc_line') {
          continue;
        }
        $domain = $this->database->select('brebo_calculation_row_domain', 'r')->fields('r', ['paragraph_key'])->condition('calc_line_id', $lineId)->condition('calculation_id', $calculationId)->condition('version', $version)->execute()->fetchAssoc();
        if (!$domain) {
          continue;
        }

        if ($line->hasField('field_brebo_line_description')) {
          $line->set('field_brebo_line_description', trim((string) ($values['description'] ?? '')));
        }
        if ($line->hasField('field_brebo_contract_quantity')) {
          $line->set('field_brebo_contract_quantity', max(0, (float) ($values['quantity'] ?? 0)));
        }
        if ($line->hasField('field_brebo_unit')) {
          $line->set('field_brebo_unit', trim((string) ($values['unit'] ?? '')));
        }
        $line->setNewRevision(TRUE);
        $line->setRevisionLogMessage('Calculatieregel via AJAX-werkbank bijgewerkt.');
        $line->save();

        $ruleType = (string) ($values['rule_type'] ?? 'normal');
        if (!in_array($ruleType, $allowedRuleTypes, TRUE)) {
          $ruleType = 'normal';
        }
        $this->database->update('brebo_calculation_row_domain')->fields([
          'rule_type' => $ruleType,
          'location_ref' => trim((string) ($values['location_ref'] ?? '')) ?: NULL,
          'labour_unit_cost' => max(0, (float) ($values['labour_unit_cost'] ?? 0)),
          'material_unit_cost' => max(0, (float) ($values['material_unit_cost'] ?? 0)),
          'equipment_unit_cost' => max(0, (float) ($values['equipment_unit_cost'] ?? 0)),
          'subcontracting_unit_cost' => max(0, (float) ($values['subcontracting_unit_cost'] ?? 0)),
          'other_unit_cost' => max(0, (float) ($values['other_unit_cost'] ?? 0)),
        ])->condition('calc_line_id', $lineId)->condition('calculation_id', $calculationId)->condition('version', $version)->execute();
      }
      return TRUE;
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

  /** @return array<string,mixed> */
  private function ajaxDefinition(string $message): array {
    return ['callback' => '::ajaxRefresh', 'wrapper' => 'brebo-calculation-workbench', 'progress' => ['type' => 'throbber', 'message' => $message]];
  }

  /** @return array<string,mixed>|null */
  private function latestVersion(int $calculationId): ?array {
    $record = $this->database->select('brebo_calculation_version', 'v')->fields('v')->condition('calculation_id', $calculationId)->orderBy('id', 'DESC')->range(0, 1)->execute()->fetchAssoc();
    return $record ?: NULL;
  }

}
