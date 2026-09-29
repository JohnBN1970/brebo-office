<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Form;

use Drupal\brebo_calculation\Service\SubcalculationManager;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Edit the calculation scope of one reusable subcalculation. */
final class SubcalculationDetailForm extends FormBase {

  public function __construct(
    private readonly SubcalculationManager $manager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_calculation.subcalculation_manager'),
    );
  }

  public function getFormId(): string {
    return 'brebo_calculation_subcalculation_detail_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?int $calculation = NULL, ?int $subcalculation = NULL): array {
    $calculationId = (int) $calculation;
    if ($calculationId <= 0 || !$subcalculation) {
      throw new \InvalidArgumentException('Calculation and subcalculation expected.');
    }
    $sub = $this->manager->get($subcalculation);
    if (!$sub || (int) $sub['calculation_id'] !== $calculationId) {
      throw new \InvalidArgumentException('Subcalculation does not belong to this calculation.');
    }

    $form['subcalculation_id'] = ['#type' => 'hidden', '#value' => $subcalculation];
    $form['heading'] = ['#markup' => '<div class="brebo-calc-workbench__meta"><span><strong>' . htmlspecialchars((string) $sub['label']) . '</strong></span><span>' . htmlspecialchars((string) ($sub['unit_label'] ?: 'eenheid')) . '</span><span>' . htmlspecialchars((string) $sub['status']) . '</span></div>'];

    $selected = $this->manager->selectedScopes($subcalculation);
    $selectedKeys = [];
    foreach ($selected as $scope) {
      $selectedKeys[$scope['scope_type'] . ':' . $scope['scope_ref']] = TRUE;
    }

    $structure = $this->manager->structure($calculationId, (string) $sub['version']);
    $domains = $this->manager->rowDomains($calculationId, (string) $sub['version']);
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
        $rowId = (int) $domain['row_id'];
        $description = (string) ($domain['description'] ?? ('Regel ' . $rowId));
        $quantity = (float) ($domain['contract_quantity'] ?? 0);
        $unit = (string) ($domain['unit'] ?? '');
        $form['scope']['line_' . $rowId] = [
          'selected' => ['#type' => 'checkbox', '#default_value' => isset($selectedKeys['line:' . $rowId])],
          'code' => ['#markup' => ''],
          'description' => ['#markup' => str_repeat('&nbsp;&nbsp;&nbsp;', ((int) $item['depth']) + 1) . htmlspecialchars($description)],
          'type' => ['#markup' => htmlspecialchars((string) $domain['rule_type'])],
          'quantity' => ['#markup' => number_format($quantity, 4, ',', '.')],
          'unit' => ['#markup' => htmlspecialchars($unit)],
          'scope_type' => ['#type' => 'hidden', '#value' => 'line'],
          'scope_ref' => ['#type' => 'hidden', '#value' => (string) $rowId],
        ];
      }
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['save'] = ['#type' => 'submit', '#value' => 'Scope opslaan', '#button_type' => 'primary'];
    $form['actions']['back'] = ['#type' => 'link', '#title' => 'Terug naar deelcalculaties', '#url' => Url::fromRoute('brebo_calculation.subcalculations', ['calculation' => $calculationId])];
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
    $existing = $this->manager->selectedScopes($subId);
    $existingKeys = [];
    foreach ($existing as $scope) {
      $key = $scope['scope_type'] . ':' . $scope['scope_ref'];
      $existingKeys[$key] = (int) $scope['id'];
      if (!isset($requested[$key])) {
        $this->manager->removeScope((int) $scope['id']);
      }
    }
    foreach ($requested as $key => [$type, $ref]) {
      if (!isset($existingKeys[$key])) {
        $this->manager->addScope($subId, $type, $ref, 1.0, (int) $this->currentUser()->id());
      }
    }
    $this->messenger()->addStatus('Scope van de deelcalculatie opgeslagen.');
    $form_state->setRebuild(TRUE);
  }

}
