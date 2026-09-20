<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Controller;

use Drupal\brebo_finance\Service\FinancialCockpitBuilder;
use Drupal\brebo_finance\Service\FinancialProjectLedger;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Shows the client assignment and BREBO purchase orders for one project. */
final class ProjectOrdersController extends ControllerBase {

  public function __construct(
    private readonly FinancialProjectLedger $ledger,
    private readonly FinancialCockpitBuilder $cockpitBuilder,
    private readonly Connection $database,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_finance.financial_project_ledger'),
      $container->get('brebo_finance.financial_cockpit_builder'),
      $container->get('database'),
    );
  }

  public function title(NodeInterface $node): string {
    $this->assertProject($node);
    return (string) $this->t('Orders — @project', ['@project' => $node->label()]);
  }

  public function overview(NodeInterface $node): array {
    $this->assertProject($node);
    $projectId = (int) $node->id();
    $ledger = $this->ledger->build($projectId);
    $finance = $this->cockpitBuilder->build($projectId);
    $pipeline = is_array($finance['procurement_pipeline'] ?? NULL) ? $finance['procurement_pipeline'] : [];
    $commitments = is_array($ledger['commitments'] ?? NULL) ? $ledger['commitments'] : [];

    $purchaseOrderRows = [];
    foreach ($commitments as $row) {
      $purchaseOrderRows[] = [
        (string) ($row['commitment_number'] ?? '—'),
        (string) ($row['supplier_name'] ?? '—'),
        (string) ($row['status'] ?? '—'),
        $this->money($row['amount_ex_vat'] ?? NULL),
        $this->money($row['amount_inc_vat'] ?? NULL),
      ];
    }

    $assignmentRows = [];
    $contract = $this->loadLatestContract($projectId);
    if ($contract !== []) {
      $assignmentRows[] = [
        (string) ($contract['contract_number'] ?? '—'),
        (string) ($contract['client_ref'] ?? '—'),
        (string) ($contract['status'] ?? '—'),
        $this->money($contract['amount_ex_vat'] ?? NULL),
        $this->money($contract['amount_inc_vat'] ?? NULL),
      ];
    }

    return [
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-list-actions']],
        'new_outgoing' => [
          '#type' => 'link',
          '#title' => $this->t('Inkooporder aanmaken'),
          '#url' => Url::fromRoute('brebo_project_cockpit.order_add', ['node' => $projectId]),
          '#attributes' => ['class' => ['button', 'button--primary']],
        ],
        'ai_outgoing' => [
          '#type' => 'link',
          '#title' => $this->t('Inkooporder met AI voorbereiden'),
          '#url' => Url::fromRoute('brebo_project_cockpit.order_add', ['node' => $projectId], ['query' => ['mode' => 'ai']]),
          '#attributes' => ['class' => ['button']],
        ],
        'contracts' => [
          '#type' => 'link',
          '#title' => $this->t('Contracten'),
          '#url' => Url::fromRoute('brebo_project_cockpit.contracts', ['node' => $projectId]),
          '#attributes' => ['class' => ['button']],
        ],
      ],
      'kpis' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-procurement-kpis']],
        'assignment' => ['#markup' => $this->kpi('Opdrachtsom', $contract['amount_ex_vat'] ?? NULL, 'excl. btw')],
        'purchase_orders' => ['#markup' => $this->kpi('Inkooporders', $pipeline['committed_ex_vat'] ?? NULL, 'excl. btw')],
        'budget' => ['#markup' => $this->kpi('Werkbegroting', $pipeline['budget_ex_vat'] ?? $pipeline['current_budget_ex_vat'] ?? NULL, 'excl. btw')],
      ],
      'assignment' => [
        '#type' => 'details',
        '#title' => $this->t('Opdracht opdrachtgever (@count)', ['@count' => count($assignmentRows)]),
        '#open' => TRUE,
        'table' => [
          '#type' => 'table',
          '#header' => [$this->t('Opdrachtnummer'), $this->t('Referentie opdrachtgever'), $this->t('Status'), $this->t('Excl. btw'), $this->t('Incl. btw')],
          '#rows' => $assignmentRows,
          '#empty' => $this->t('Voor dit project is nog geen opdracht van de opdrachtgever geregistreerd.'),
        ],
      ],
      'purchase_orders' => [
        '#type' => 'details',
        '#title' => $this->t('Inkooporders (@count)', ['@count' => count($purchaseOrderRows)]),
        '#open' => TRUE,
        'table' => [
          '#type' => 'table',
          '#header' => [$this->t('Ordernummer'), $this->t('Leverancier / onderaannemer'), $this->t('Status'), $this->t('Excl. btw'), $this->t('Incl. btw')],
          '#rows' => $purchaseOrderRows,
          '#empty' => $this->t('Voor dit project zijn nog geen inkooporders geregistreerd.'),
        ],
      ],
      'explanation' => [
        '#type' => 'details',
        '#title' => $this->t('Toelichting'),
        '#open' => FALSE,
        'text' => ['#markup' => '<p>' . $this->t('De opdracht van de opdrachtgever legt de commerciële projectbasis vast. Inkooporders zijn opdrachten van BREBO aan leveranciers of onderaannemers en worden tegen de werkbegroting bewaakt.') . '</p>'],
      ],
      '#cache' => [
        'contexts' => ['user.permissions'],
        'tags' => ['node:' . $projectId],
        'max-age' => 0,
      ],
    ];
  }

  /** @return array<string, mixed> */
  private function loadLatestContract(int $projectId): array {
    if (!$this->database->schema()->tableExists('brebo_finance_project_contract')) {
      return [];
    }
    $row = $this->database->select('brebo_finance_project_contract', 'c')
      ->fields('c')
      ->condition('project_nid', $projectId)
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    return $row === FALSE ? [] : $row;
  }

  private function kpi(string $label, mixed $value, string $basis): string {
    return '<div class="brebo-procurement-kpi"><strong>' . $this->money($value) . '</strong><span>' . $label . ' · ' . $basis . '</span></div>';
  }

  private function money(mixed $value): string {
    if (!is_numeric($value)) {
      return '—';
    }
    return '€ ' . number_format((float) $value, 2, ',', '.');
  }

  private function assertProject(NodeInterface $node): void {
    if ($node->bundle() !== 'brebo_project') {
      throw new NotFoundHttpException('BREBO project does not exist.');
    }
  }

}
