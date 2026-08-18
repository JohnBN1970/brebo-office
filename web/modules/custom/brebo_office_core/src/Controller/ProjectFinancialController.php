<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Controller;

use Drupal\brebo_office_core\Service\ProjectFinancialControl;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Live financial cockpit for one BREBO project.
 */
final class ProjectFinancialController extends ControllerBase {

  public function __construct(
    private readonly ProjectFinancialControl $financialControl,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_office_core.project_financial_control'));
  }

  public function title(NodeInterface $node): string {
    $this->assertProject($node);
    return (string) $this->t('Financiële cockpit · @project', ['@project' => $node->label()]);
  }

  public function overview(NodeInterface $node): array {
    $this->assertProject($node);
    $result = $this->financialControl->analyze($node);
    $money = static fn (float $value): string => '€ ' . number_format($value, 2, ',', '.');

    $rows = [];
    foreach ($result['rows'] as $row) {
      $rows[] = [
        $row['label'],
        number_format((float) $row['budget_hours'], 2, ',', '.'),
        number_format((float) $row['actual_hours'], 2, ',', '.'),
        number_format((float) $row['forecast_hours'], 2, ',', '.'),
        $money((float) $row['labor_rate']),
        $money((float) $row['actual_labor_cost']),
        $money((float) $row['forecast_labor_cost']),
        $row['status'],
      ];
    }

    return [
      'status' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['messages', $result['status'] === 'Akkoord' ? 'messages--status' : 'messages--warning']],
        'text' => ['#markup' => '<strong>' . $this->t('Projectstatus: @status', ['@status' => $result['status']]) . '</strong>'],
      ],
      'summary' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Werkbegrotingen'), $this->t('Regels'), $this->t('Budget kostprijs'),
          $this->t('Werkelijk arbeid'), $this->t('Prognose kostprijs'), $this->t('Afwijking'),
        ],
        '#rows' => [[
          $result['work_budgets'], $result['lines'], $money((float) $result['budget_cost']),
          $money((float) $result['actual_labor_cost']), $money((float) $result['forecast_cost']),
          $money((float) $result['variance']),
        ]],
      ],
      'hours' => [
        '#type' => 'table',
        '#header' => [$this->t('Budgeturen'), $this->t('Goedgekeurd werkelijk'), $this->t('Prognose einduren')],
        '#rows' => [[
          number_format((float) $result['budget_hours'], 2, ',', '.'),
          number_format((float) $result['actual_hours'], 2, ',', '.'),
          number_format((float) $result['forecast_hours'], 2, ',', '.'),
        ]],
      ],
      'scope' => [
        '#type' => 'container', '#attributes' => ['class' => ['messages', 'messages--status']],
        'text' => ['#markup' => $this->t('<strong>Datadekking:</strong> @scope', ['@scope' => $result['actual_scope']])],
      ],
      'signals_heading' => ['#markup' => '<h2>' . $this->t('Digitale controller · signalen') . '</h2>'],
      'signals' => [
        '#theme' => 'item_list',
        '#items' => $result['signals'] ?: [$this->t('Geen financiële afwijkingen uit de beschikbare projectdata.')],
      ],
      'labor_heading' => ['#markup' => '<h2>' . $this->t('Arbeidsprognose per werkbegrotingsregel') . '</h2>'],
      'labor' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Regel'), $this->t('Budget u'), $this->t('Werkelijk u'), $this->t('Prognose u'),
          $this->t('Kostprijs/u'), $this->t('Werkelijk €'), $this->t('Prognose €'), $this->t('Signaal'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('Nog geen werkbegrotingsregels beschikbaar.'),
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

  private function assertProject(NodeInterface $node): void {
    if ($node->bundle() !== 'brebo_project') {
      throw new NotFoundHttpException();
    }
  }

}
