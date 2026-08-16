<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Form;

use Drupal\brebo_calculation\Service\SubcalculationManager;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Edit the calculation scope of one reusable subcalculation. */
final class SubcalculationDetailForm extends FormBase {

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly SubcalculationManager $manager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('brebo_calculation.subcalculation_manager'),
    );
  }

  public function getFormId(): string {
    return 'brebo_calculation_subcalculation_detail_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL, ?int $subcalculation = NULL): array {
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_calculation' || !$subcalculation) {
      throw new \InvalidArgumentException('Calculation and subcalculation expected.');
    }
    $sub = $this->database->select('brebo_calculation_subcalculation', 's')->fields('s')
      ->condition('id', $subcalculation)
      ->condition('calculation_id', (int) $node->id())
      ->execute()->fetchAssoc();
    if (!$sub) {
      throw new \InvalidArgumentException('Subcalculation does not belong to this calculation.');
    }

    $form['subcalculation_id'] = ['#type' => 'hidden', '#value' => $subcalculation];
    $form['heading'] = ['#markup' => '<div class="brebo-calc-workbench__meta"><span><strong>' . htmlspecialchars((string) $sub['label']) . '</strong></span><span>' . htmlspecialchars((string) ($sub['unit_label'] ?: 'eenheid')) . '</span><span>' . htmlspecialchars((string) $sub['status']) . '</span></div>'];

    $selected = $this->database->select('brebo_calculation_subcalculation_scope', 'ss')->fields('ss', ['scope_type', 'scope_ref'])
      ->condition('subcalculation_id', $subcalculation)->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $selectedKeys = [];
    foreach ($selected as $scope) {
      $selectedKeys[$scope['scope_type'] . ':' . $scope['scope_ref']] = TRUE;
    }

    $structure = $this->database->select('brebo_calculation_structure', 's')->fields('s')
      ->condition('calculation_id', (int) $node->id())
      ->condition('version', (string) $sub['version'])
      ->orderBy('sort_order')->orderBy('depth')->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $domains = $this->database->select('brebo_calculation_row_domain', 'r')->fields('r')
      ->condition('calculation_id', (int) $node->id())
      ->condition('version', (string) $sub['version'])
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $lineIds = array_map(static fn(array $r): int => (int) $r['calc_line_id'], $domains);
    $lines = $lineIds ? $this->entityTypeManager->getStorage('node')->loadMultiple($lineIds) : [];
    $byParagraph = [];
    foreach ($domains as $domain) {
      $byParagraph[$domain['paragraph_key']][] = $domain;
    }

    $form['scope'] = ['#type' => 'table', '#header' => ['Opnemen', 'Code', 'Omschrijving', 'Niveau/type', 'Aantal', 'Eenheid'], '#tree' => TRUE, '#attributes' => ['class' => ['brebo-calc-workbench__grid']]];
    foreach ($structure as $item) {
      $key = (string) $item['node_key'];
      $form['scope']['structure_' . $item['id']] = [
        'selected' => ['#type' => 'checkbox', '#default_value' => isset($selectedKeys['structure:' . $key])],
        'code' => ['#markup' => htmlspecialchars((string) ($item['code'] ?: '—'))],
        'description' => ['#markup' => '<strong>' . str_repeat('&nbsp;&nbsp;&nbsp;', (int) $item['depth']) . htmlspecialchars((string) $item['label']) . '</strong>'],
        'type' => ['#markup' => htmlspecialchars((string) $item['node_type'])],
        'quantity' => ['#markup' => ''],
        'unit' => ['#markup' => ''],
        'scope_type' => ['#type' => 'hidden', '#value' => 'structure'],
        'scope_ref' => ['#type' => 'hidden', '#value' => $key],
      ];
      foreach ($byParagraph[$key] ?? [] as $domain) {
        $lineId = (int) $domain['calc_line_id'];
        $line = $lines[$lineId] ?? NULL;
        $description = $line instanceof NodeInterface ? (string) ($line->get('field_brebo_line_description')->value ?? $line->label()) : 'Regel ' . $lineId;
        $quantity = $line instanceof NodeInterface ? (float) ($line->get('field_brebo_contract_quantity')->value ?? 0) : 0;
        $unit = $line instanceof NodeInterface ? (string) ($line->get('field_brebo_unit')->value ?? '') : '';
        $form['scope']['line_' . $lineId] = [
          'selected' => ['#type' => 'checkbox', '#default_value' => isset($selectedKeys['line:' . $lineId])],
          'code' => ['#markup' => ''],
          'description' => ['#markup' => str_repeat('&nbsp;&nbsp;&nbsp;', ((int) $item['depth']) + 1) . htmlspecialchars($description)],
          'type' => ['#markup' => htmlspecialchars((string) $domain['rule_type'])],
          'quantity' => ['#markup' => number_format($quantity, 4, ',', '.')],
          'unit' => ['#markup' => htmlspecialchars($unit)],
          'scope_type' => ['#type' => 'hidden', '#value' => 'line'],
          'scope_ref' => ['#type' => 'hidden', '#value' => (string) $lineId],
        ];
      }
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['save'] = ['#type' => 'submit', '#value' => 'Scope opslaan', '#button_type' => 'primary'];
    $form['actions']['back'] = ['#type' => 'link', '#title' => 'Terug naar deelcalculaties', '#url' => Url::fromRoute('brebo_calculation.subcalculations', ['node' => $node->id()])];
    $form['#attached']['library'][] = 'brebo_calculation/workbench';
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $subId = (int) $form_state->getValue('subcalculation_id');
    $requested = [];
    foreach ((array) $form_state->getValue('scope') as $row) {
      if (!empty($row['selected']) && !empty($row['scope_type']) && isset($row['scope_ref'])) {
        $requested[$row['scope_type'] . ':' . $row['scope_ref']] = [$row['scope_type'], (string) $row['scope_ref']];
      }
    }
    $existing = $this->database->select('brebo_calculation_subcalculation_scope', 'ss')->fields('ss', ['id', 'scope_type', 'scope_ref'])
      ->condition('subcalculation_id', $subId)->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $existingKeys = [];
    foreach ($existing as $scope) {
      $key = $scope['scope_type'] . ':' . $scope['scope_ref'];
      $existingKeys[$key] = (int) $scope['id'];
      if (!isset($requested[$key])) {
        $this->database->delete('brebo_calculation_subcalculation_scope')->condition('id', (int) $scope['id'])->execute();
      }
    }
    foreach ($requested as $key => [$type, $ref]) {
      if (!isset($existingKeys[$key])) {
        $this->manager->addScope($subId, $type, $ref, 1.0, $this->currentUser());
      }
    }
    $this->messenger()->addStatus('Scope van de deelcalculatie opgeslagen.');
    $form_state->setRebuild(TRUE);
  }

}
