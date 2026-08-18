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

    $cards = [
      ['label' => 'Geblokkeerde betalingen', 'value' => 'EUR ' . number_format((float) ($headline['blocked_payment_value'] ?? 0), 0, ',', '.')],
      ['label' => 'Controller-blootstelling', 'value' => 'EUR ' . number_format((float) ($headline['controller_case_exposure'] ?? 0), 0, ',', '.')],
      ['label' => 'Kritieke dossiers', 'value' => (string) ($headline['critical_controller_cases'] ?? 0)],
      ['label' => 'Verlopen verplichtingen', 'value' => (string) ($headline['overdue_contract_obligations'] ?? 0)],
      ['label' => 'Portefeuillerisico', 'value' => (string) ($headline['portfolio_risk_score'] ?? 0) . ' - ' . (string) ($headline['portfolio_risk_level'] ?? '')],
      ['label' => 'Risicoleveranciers', 'value' => (string) ($headline['suppliers_below_c_rating'] ?? 0)],
    ];

    $rows = [];
    foreach ($overdue as $action) {
      $rows[] = [
        $action['title'] ?? '',
        strtoupper((string) ($action['severity'] ?? '')),
        (string) ($action['owner_uid'] ?? ''),
        !empty($action['due_at']) ? date('d-m-Y H:i', (int) $action['due_at']) : '',
        strtoupper((string) ($action['escalation'] ?? '')),
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['brebo-management-control-center']],
      'title' => ['#markup' => '<h1>Management Control Center</h1><p>Directiebeeld van financieel, contractueel en operationeel controlrisico.</p>'],
      'status' => ['#markup' => '<h2>Status: ' . htmlspecialchars(str_replace('_', ' ', strtoupper((string) ($dashboard['management_status'] ?? 'onder_controle')))) . '</h2>'],
      'cards' => ['#theme' => 'item_list', '#items' => array_map(static fn(array $card): string => '<strong>' . htmlspecialchars($card['label']) . '</strong>: ' . htmlspecialchars($card['value']), $cards), '#attributes' => ['class' => ['brebo-control-kpis']]],
      'overdue_title' => ['#markup' => '<h2>Escalaties en verlopen acties</h2>'],
      'overdue' => [
        '#type' => 'table',
        '#header' => ['Actie', 'Ernst', 'Eigenaar', 'Deadline', 'Escalatie'],
        '#rows' => $rows,
        '#empty' => 'Geen verlopen managementacties.',
      ],
      'supplier_title' => ['#markup' => '<h2>Leveranciersrisico</h2>'],
      'supplier' => ['#type' => 'table', '#header' => ['Leverancier', 'TCO-score'], '#rows' => array_map(static fn(array $row): array => [(string) ($row['supplier_name'] ?? $row['supplier_id'] ?? ''), (string) ($row['tco_adjusted_score'] ?? '')], (array) ($dashboard['supplier_risk'] ?? [])), '#empty' => 'Geen leveranciers onder de risicodrempel.'],
      '#cache' => ['max-age' => 0],
    ];
  }
}
