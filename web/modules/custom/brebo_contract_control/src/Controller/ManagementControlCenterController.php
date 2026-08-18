<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Controller;

use Drupal\brebo_contract_control\Service\ManagementActionEngine;
use Drupal\brebo_contract_control\Service\ManagementControlCenterService;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** BREBO Office directiecockpit voor control, risico en acties. */
final class ManagementControlCenterController extends ControllerBase {

  public function __construct(
    private readonly ManagementControlCenterService $controlCenter,
    private readonly ManagementActionEngine $actions,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_contract_control.management_control_center'),
      $container->get('brebo_contract_control.management_actions'),
    );
  }

  public function overview(): array {
    $dashboard = $this->controlCenter->dashboard();
    $headline = (array) ($dashboard['headline'] ?? []);
    $overdue = $this->actions->escalateOverdue();
    $managementStatus = (string) ($dashboard['management_status'] ?? 'onder_controle');

    $cards = [
      $this->card('Geblokkeerde betalingen', 'EUR ' . number_format((float) ($headline['blocked_payment_value'] ?? 0), 0, ',', '.'), (float) ($headline['blocked_payment_value'] ?? 0) >= 100000 ? 'critical' : ((float) ($headline['blocked_payment_value'] ?? 0) > 0 ? 'attention' : 'ok')),
      $this->card('Controller-blootstelling', 'EUR ' . number_format((float) ($headline['controller_case_exposure'] ?? 0), 0, ',', '.'), (float) ($headline['controller_case_exposure'] ?? 0) > 0 ? 'attention' : 'ok'),
      $this->card('Kritieke dossiers', (string) ($headline['critical_controller_cases'] ?? 0), (int) ($headline['critical_controller_cases'] ?? 0) > 0 ? 'critical' : 'ok'),
      $this->card('Verlopen verplichtingen', (string) ($headline['overdue_contract_obligations'] ?? 0), (int) ($headline['overdue_contract_obligations'] ?? 0) > 0 ? 'attention' : 'ok'),
      $this->card('Portefeuillerisico', (string) ($headline['portfolio_risk_score'] ?? 0) . ' / 100', (int) ($headline['portfolio_risk_score'] ?? 0) >= 75 ? 'critical' : ((int) ($headline['portfolio_risk_score'] ?? 0) >= 50 ? 'attention' : 'ok')),
      $this->card('Risicoleveranciers', (string) ($headline['suppliers_below_c_rating'] ?? 0), (int) ($headline['suppliers_below_c_rating'] ?? 0) > 0 ? 'attention' : 'ok'),
    ];

    $rows = [];
    foreach ($overdue as $action) {
      $rows[] = [
        ['data' => $action['title'] ?? ''],
        ['data' => strtoupper((string) ($action['severity'] ?? '')), 'class' => [in_array((string) ($action['severity'] ?? ''), ['critical', 'high'], TRUE) ? 'brebo-risk-critical' : '']],
        ['data' => (string) ($action['owner_uid'] ?? '')],
        ['data' => !empty($action['due_at']) ? date('d-m-Y H:i', (int) $action['due_at']) : ''],
        ['data' => strtoupper((string) ($action['escalation'] ?? ''))],
      ];
    }

    $supplierRows = array_map(static fn(array $row): array => [
      (string) ($row['supplier_name'] ?? $row['supplier_id'] ?? ''),
      (string) ($row['tco_adjusted_score'] ?? ''),
    ], (array) ($dashboard['supplier_risk'] ?? []));

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-management-control-center']],
      '#attached' => ['library' => ['brebo_contract_control/management_control_center']],
      'hero' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-control-hero']],
        'title' => ['#markup' => '<h1>Management Control Center</h1><div class="brebo-control-muted">Directiebeeld van financieel, contractueel en operationeel controlrisico.</div>'],
        'status' => ['#markup' => '<div class="brebo-control-status brebo-control-status--' . $this->statusClass($managementStatus) . '">Status: ' . htmlspecialchars(str_replace('_', ' ', strtoupper($managementStatus))) . '</div>'],
      ],
      'cards' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-control-kpi-grid']],
        'items' => array_reduce(array_keys($cards), function (array $carry, int $index) use ($cards): array {
          $carry['card_' . $index] = [
            '#markup' => '<div class="brebo-control-kpi brebo-control-kpi--' . $cards[$index]['level'] . '"><div class="brebo-control-kpi__label">' . htmlspecialchars($cards[$index]['label']) . '</div><div class="brebo-control-kpi__value">' . htmlspecialchars($cards[$index]['value']) . '</div></div>',
          ];
          return $carry;
        }, []),
      ],
      'overdue_section' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-control-section']],
        'title' => ['#markup' => '<h2>Escalaties en verlopen acties</h2>'],
        'table' => ['#type' => 'table', '#header' => ['Actie', 'Ernst', 'Eigenaar', 'Deadline', 'Escalatie'], '#rows' => $rows, '#empty' => 'Geen verlopen managementacties.'],
      ],
      'supplier_section' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-control-section']],
        'title' => ['#markup' => '<h2>Leveranciersrisico</h2>'],
        'table' => ['#type' => 'table', '#header' => ['Leverancier', 'TCO-score'], '#rows' => $supplierRows, '#empty' => 'Geen leveranciers onder de risicodrempel.'],
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

  /** @return array{label:string,value:string,level:string} */
  private function card(string $label, string $value, string $level): array {
    return ['label' => $label, 'value' => $value, 'level' => $level];
  }

  private function statusClass(string $status): string {
    return match ($status) {
      'directie_ingrijpen' => 'critical',
      'management_actie' => 'action',
      'aandacht' => 'attention',
      default => 'ok',
    };
  }
}
