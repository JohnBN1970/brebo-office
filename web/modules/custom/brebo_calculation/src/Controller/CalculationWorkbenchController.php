<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Spreadsheet-like read model for the new calculation domain. */
final class CalculationWorkbenchController extends ControllerBase {

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $calculationEntityTypeManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('entity_type.manager'),
    );
  }

  public function title(NodeInterface $node): string {
    return $this->t('Calculatie · @label', ['@label' => $node->label()]);
  }

  public function workbench(NodeInterface $node): array {
    if ($node->bundle() !== 'brebo_calculation') {
      throw new \InvalidArgumentException('Calculation expected.');
    }

    $version = $this->latestVersion((int) $node->id());
    if ($version === NULL) {
      return [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-calc-workbench']],
        'empty' => ['#markup' => '<p>Deze calculatie heeft nog geen domeinversie. Voer eerst de migratie-audit uit.</p>'],
        '#attached' => ['library' => ['brebo_calculation/workbench']],
      ];
    }

    $structure = $this->database->select('brebo_calculation_structure', 's')
      ->fields('s')
      ->condition('calculation_id', (int) $node->id())
      ->condition('version', $version['version'])
      ->orderBy('sort_order')
      ->orderBy('depth')
      ->execute()->fetchAllAssoc('node_key', \PDO::FETCH_ASSOC);

    $rowRecords = $this->database->select('brebo_calculation_row_domain', 'r')
      ->fields('r')
      ->condition('calculation_id', (int) $node->id())
      ->condition('version', $version['version'])
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);

    $lineIds = array_map(static fn (array $r): int => (int) $r['calc_line_id'], $rowRecords);
    $lines = $lineIds ? $this->calculationEntityTypeManager->getStorage('node')->loadMultiple($lineIds) : [];
    $byParagraph = [];
    foreach ($rowRecords as $record) {
      $byParagraph[$record['paragraph_key']][] = $record;
    }

    $rows = [];
    $grandTotal = 0.0;
    foreach ($structure as $key => $item) {
      $depth = (int) $item['depth'];
      $rows[] = [
        'class' => ['brebo-calc-workbench__structure', 'depth-' . $depth, 'type-' . $item['node_type']],
        'data' => [
          $item['code'] ?: '—',
          ['data' => ['#markup' => '<strong>' . str_repeat('&nbsp;&nbsp;&nbsp;', $depth) . htmlspecialchars($item['label']) . '</strong>']],
          $item['location_ref'] ?: '—',
          strtoupper((string) $item['classification_system']),
          '', '', '', '', '', '', '', '',
        ],
      ];

      foreach ($byParagraph[$key] ?? [] as $domain) {
        $line = $lines[(int) $domain['calc_line_id']] ?? NULL;
        $quantity = $line instanceof NodeInterface ? (float) ($line->get('field_brebo_contract_quantity')->value ?? 0) : 0.0;
        $unit = $line instanceof NodeInterface ? (string) ($line->get('field_brebo_unit')->value ?? '') : '';
        $description = $line instanceof NodeInterface ? (string) ($line->get('field_brebo_line_description')->value ?? $line->label()) : ('Regel ' . $domain['calc_line_id']);
        $labour = (float) $domain['labour_unit_cost'];
        $material = (float) $domain['material_unit_cost'];
        $equipment = (float) $domain['equipment_unit_cost'];
        $subcontracting = (float) $domain['subcontracting_unit_cost'];
        $other = (float) $domain['other_unit_cost'];
        $unitCost = $labour + $material + $equipment + $subcontracting + $other;
        $total = $quantity * $unitCost;
        if ($domain['rule_type'] !== 'option' && $domain['rule_type'] !== 'note') {
          $grandTotal += $total;
        }
        $rows[] = [
          'class' => ['brebo-calc-workbench__line', 'rule-' . $domain['rule_type']],
          'data' => [
            '',
            $description,
            $domain['location_ref'] ?: '—',
            $domain['rule_type'],
            $this->number($quantity, 4),
            $unit,
            $this->money($labour),
            $this->money($material),
            $this->money($equipment),
            $this->money($subcontracting),
            $this->money($other),
            $this->money($unitCost),
            $this->money($total),
          ],
        ];
      }
    }

    $locked = $version['locked_at'] !== NULL;
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-calc-workbench']],
      'meta' => [
        '#markup' => '<div class="brebo-calc-workbench__meta"><span><strong>Versie</strong> ' . htmlspecialchars($version['version']) . '</span><span><strong>Status</strong> ' . htmlspecialchars($version['status']) . '</span><span><strong>Classificatie</strong> ' . htmlspecialchars(strtoupper($version['classification_system'])) . '</span><span class="' . ($locked ? 'is-locked' : 'is-open') . '">' . ($locked ? '🔒 Vergrendeld' : '● Bewerkbaar') . '</span></div>',
      ],
      'grid' => [
        '#type' => 'table',
        '#header' => ['Code', 'Omschrijving', 'Locatie', 'Type', 'Aantal', 'EH', 'Arbeid', 'Materiaal', 'Materieel', 'Onderaanneming', 'Overig', 'Kostprijs/EH', 'Totaal'],
        '#rows' => $rows,
        '#attributes' => ['class' => ['brebo-calc-workbench__grid']],
        '#sticky' => TRUE,
        '#empty' => $this->t('Geen calculatieregels gevonden.'),
      ],
      'footer' => [
        '#markup' => '<div class="brebo-calc-workbench__total"><span>Calculatietotaal excl. opties</span><strong>' . $this->money($grandTotal) . '</strong></div>',
      ],
      '#attached' => ['library' => ['brebo_calculation/workbench']],
      '#cache' => ['max-age' => 0],
    ];
  }

  public function parameters(NodeInterface $node): array {
    $version = $this->latestVersion((int) $node->id());
    if ($version === NULL) {
      return ['#markup' => '<p>Nog geen domeinversie beschikbaar.</p>'];
    }
    $rows = [
      ['Versie', $version['version']],
      ['Status', $version['status']],
      ['Classificatiesysteem', strtoupper($version['classification_system'])],
      ['Prijsmodel', $version['pricing_mode']],
      ['Commerciële methode', $version['commercial_method']],
      ['Algemene kosten', $this->number((float) $version['general_cost_pct'], 2) . ' %'],
      ['Risico', $this->number((float) $version['risk_pct'], 2) . ' %'],
      ['Winst', $this->number((float) $version['profit_pct'], 2) . ' %'],
      ['Enkele marge', $this->number((float) $version['single_margin_pct'], 2) . ' %'],
      ['Commerciële correctie', $this->money((float) $version['commercial_adjustment'])],
      ['Prijsdatum', $version['price_date'] ?: '—'],
      ['Prijsniveau', $version['price_level'] ?: '—'],
      ['Vergrendeld', $version['locked_at'] !== NULL ? 'Ja' : 'Nee'],
    ];
    return [
      '#type' => 'table',
      '#header' => ['Parameter', 'Waarde'],
      '#rows' => $rows,
      '#attributes' => ['class' => ['brebo-calc-parameters']],
      '#attached' => ['library' => ['brebo_calculation/workbench']],
      '#cache' => ['max-age' => 0],
    ];
  }

  /** @return array<string, mixed>|null */
  private function latestVersion(int $calculationId): ?array {
    $record = $this->database->select('brebo_calculation_version', 'v')
      ->fields('v')
      ->condition('calculation_id', $calculationId)
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()->fetchAssoc();
    return $record ?: NULL;
  }

  private function money(float $value): string {
    return '€ ' . number_format($value, 2, ',', '.');
  }

  private function number(float $value, int $decimals): string {
    return number_format($value, $decimals, ',', '.');
  }

}
