<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Controller;

use Drupal\brebo_contract_control\Service\ManagementActionEngine;
use Drupal\brebo_contract_control\Service\ManagementActionSourceResolver;
use Drupal\brebo_contract_control\Service\ManagementControlCenterService;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** BREBO Office directiecockpit voor control, risico en acties. */
final class ManagementControlCenterController extends ControllerBase {

  public function __construct(
    private readonly ManagementControlCenterService $controlCenter,
    private readonly ManagementActionEngine $actions,
    private readonly ManagementActionSourceResolver $sources,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_contract_control.management_control_center'),
      $container->get('brebo_contract_control.management_actions'),
      $container->get('brebo_contract_control.management_action_source'),
    );
  }

  public function overview(): array {
    $dashboard = $this->controlCenter->dashboard();
    $headline = (array) ($dashboard['headline'] ?? []);
    $overdue = $this->actions->escalateOverdue();
    $managementStatus = (string) ($dashboard['management_status'] ?? 'onder_controle');
    $cards = [
      $this->card('Geblokkeerde betalingen', 'EUR ' . number_format((float) ($headline['blocked_payment_value'] ?? 0), 0, ',', '.'), (float) ($headline['blocked_payment_value'] ?? 0) >= 100000 ? 'critical' : ((float) ($headline['blocked_payment_value'] ?? 0) > 0 ? 'attention' : 'ok'), 'blocked_payments'),
      $this->card('Controller-blootstelling', 'EUR ' . number_format((float) ($headline['controller_case_exposure'] ?? 0), 0, ',', '.'), (float) ($headline['controller_case_exposure'] ?? 0) > 0 ? 'attention' : 'ok', 'critical_cases'),
      $this->card('Kritieke dossiers', (string) ($headline['critical_controller_cases'] ?? 0), (int) ($headline['critical_controller_cases'] ?? 0) > 0 ? 'critical' : 'ok', 'critical_cases'),
      $this->card('Verlopen verplichtingen', (string) ($headline['overdue_contract_obligations'] ?? 0), (int) ($headline['overdue_contract_obligations'] ?? 0) > 0 ? 'attention' : 'ok', 'overdue_obligations'),
      $this->card('Portefeuillerisico', (string) ($headline['portfolio_risk_score'] ?? 0) . ' / 100', (int) ($headline['portfolio_risk_score'] ?? 0) >= 75 ? 'critical' : ((int) ($headline['portfolio_risk_score'] ?? 0) >= 50 ? 'attention' : 'ok'), 'portfolio_risk'),
      $this->card('Risicoleveranciers', (string) ($headline['suppliers_below_c_rating'] ?? 0), (int) ($headline['suppliers_below_c_rating'] ?? 0) > 0 ? 'attention' : 'ok', 'supplier_risk'),
    ];
    $rows = [];
    foreach ($overdue as $action) {
      $rows[] = [Link::fromTextAndUrl((string) ($action['title'] ?? ''), Url::fromRoute('brebo_contract_control.management_action_source', ['action_id' => (int) $action['id']]))->toRenderable(), strtoupper((string) ($action['severity'] ?? '')), (string) ($action['owner_uid'] ?? ''), !empty($action['due_at']) ? date('d-m-Y H:i', (int) $action['due_at']) : '', strtoupper((string) ($action['escalation'] ?? ''))];
    }
    $supplierRows = array_map(static fn(array $row): array => [(string) ($row['supplier_name'] ?? $row['supplier_id'] ?? ''), (string) ($row['tco_adjusted_score'] ?? '')], (array) ($dashboard['supplier_risk'] ?? []));
    $cardItems = [];
    foreach ($cards as $index => $card) {
      $cardItems['card_' . $index] = ['#type' => 'link', '#title' => ['#markup' => '<span class="brebo-control-kpi__label">' . htmlspecialchars($card['label']) . '</span><span class="brebo-control-kpi__value">' . htmlspecialchars($card['value']) . '</span><span class="brebo-control-kpi__hint">Bekijk acties &rarr;</span>'], '#url' => Url::fromRoute('brebo_contract_control.management_actions', ['action_key' => $card['action_key']]), '#attributes' => ['class' => ['brebo-control-kpi', 'brebo-control-kpi--' . $card['level']]]];
    }
    return ['#type' => 'container', '#attributes' => ['class' => ['brebo-management-control-center']], '#attached' => ['library' => ['brebo_contract_control/management_control_center']], 'hero' => ['#type' => 'container', '#attributes' => ['class' => ['brebo-control-hero']], 'title' => ['#markup' => '<h1>Management Control Center</h1><div class="brebo-control-muted">Directiebeeld van financieel, contractueel en operationeel controlrisico.</div>'], 'status' => ['#markup' => '<div class="brebo-control-status brebo-control-status--' . $this->statusClass($managementStatus) . '">Status: ' . htmlspecialchars(str_replace('_', ' ', strtoupper($managementStatus))) . '</div>']], 'cards' => ['#type' => 'container', '#attributes' => ['class' => ['brebo-control-kpi-grid']], 'items' => $cardItems], 'overdue_section' => ['#type' => 'container', '#attributes' => ['class' => ['brebo-control-section']], 'title' => ['#markup' => '<h2>Escalaties en verlopen acties</h2>'], 'table' => ['#type' => 'table', '#header' => ['Actie', 'Ernst', 'Eigenaar', 'Deadline', 'Escalatie'], '#rows' => $rows, '#empty' => 'Geen verlopen managementacties.']], 'supplier_section' => ['#type' => 'container', '#attributes' => ['class' => ['brebo-control-section']], 'title' => ['#markup' => '<h2>Leveranciersrisico</h2>'], 'table' => ['#type' => 'table', '#header' => ['Leverancier', 'TCO-score'], '#rows' => $supplierRows, '#empty' => 'Geen leveranciers onder de risicodrempel.']], '#cache' => ['max-age' => 0]];
  }

  public function actions(string $action_key = 'all'): array {
    $rows = [];
    foreach ($this->actions->openActions($action_key === 'all' ? NULL : $action_key) as $action) {
      $source = Link::fromTextAndUrl('Open bron', Url::fromRoute('brebo_contract_control.management_action_source', ['action_id' => (int) $action['id']]))->toRenderable();
      $rows[] = [(int) $action['id'], (string) $action['title'], strtoupper((string) $action['severity']), (string) $action['status'], (string) $action['owner_uid'], date('d-m-Y H:i', (int) $action['due_at']), (string) $action['source_type'], $source];
    }
    return ['#type' => 'container', '#attached' => ['library' => ['brebo_contract_control/management_control_center']], 'back' => Link::fromTextAndUrl('← Terug naar Control Center', Url::fromRoute('brebo_contract_control.management_control_center'))->toRenderable(), 'title' => ['#markup' => '<h1>Managementacties</h1><p>Filter: <strong>' . htmlspecialchars($action_key) . '</strong></p>'], 'table' => ['#type' => 'table', '#header' => ['ID', 'Actie', 'Ernst', 'Status', 'Eigenaar', 'Deadline', 'Bronsoort', 'Bron'], '#rows' => $rows, '#empty' => 'Geen open acties voor dit signaal.'], '#cache' => ['max-age' => 0]];
  }

  public function source(int $action_id): array {
    $resolved = $this->sources->resolve($action_id);
    $action = (array) $resolved['action'];
    $items = (array) $resolved['items'];
    $headers = $items !== [] ? array_keys((array) reset($items)) : [];
    $rows = array_map(static fn(array $item): array => array_map(static fn($value): string => is_scalar($value) || $value === NULL ? (string) $value : json_encode($value, JSON_UNESCAPED_SLASHES), array_values($item)), $items);
    return ['#type' => 'container', '#attached' => ['library' => ['brebo_contract_control/management_control_center']], 'back' => Link::fromTextAndUrl('← Terug naar managementacties', Url::fromRoute('brebo_contract_control.management_actions', ['action_key' => (string) ($action['action_key'] ?? 'all')]))->toRenderable(), 'title' => ['#markup' => '<h1>Control Source dossier</h1><p><strong>' . htmlspecialchars((string) ($action['title'] ?? '')) . '</strong><br>Bron: ' . htmlspecialchars((string) ($resolved['source_type'] ?? '')) . '</p>'], 'table' => ['#type' => 'table', '#header' => $headers, '#rows' => $rows, '#empty' => 'Geen onderliggende bronrecords gevonden.'], '#cache' => ['max-age' => 0]];
  }

  /** @return array{label:string,value:string,level:string,action_key:string} */
  private function card(string $label, string $value, string $level, string $actionKey): array { return ['label' => $label, 'value' => $value, 'level' => $level, 'action_key' => $actionKey]; }
  private function statusClass(string $status): string { return match ($status) { 'directie_ingrijpen' => 'critical', 'management_actie' => 'action', 'aandacht' => 'attention', default => 'ok' }; }
}
