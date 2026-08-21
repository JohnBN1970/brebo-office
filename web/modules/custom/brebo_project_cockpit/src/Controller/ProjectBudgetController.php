<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Shows the execution budget and its commercial source for a project.
 */
final class ProjectBudgetController extends ControllerBase {

  public function __construct(
    private readonly DateFormatterInterface $dateFormatter,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('date.formatter'),
    );
  }

  public function title(NodeInterface $node): string {
    $this->assertProject($node);
    return (string) $this->t('Begroting — @project', ['@project' => $node->label()]);
  }

  public function overview(NodeInterface $node): array {
    $this->assertProject($node);
    $storage = $this->entityTypeManager()->getStorage('node');

    $package_ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_work_package')
      ->condition('field_brebo_project_ref.target_id', $node->id())
      ->execute();

    $budget_rows = [];
    $calculation_rows = [];
    $calculation_ids = [];

    if ($package_ids) {
      $budget_ids = $storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'brebo_work_budget')
        ->condition('field_brebo_package_ref.target_id', array_values($package_ids), 'IN')
        ->sort('changed', 'DESC')
        ->execute();

      foreach ($storage->loadMultiple($budget_ids) as $budget) {
        if (!$budget instanceof NodeInterface) {
          continue;
        }
        $source = $budget->get('field_brebo_calculation_ref')->entity;
        if ($source instanceof NodeInterface) {
          $calculation_ids[(int) $source->id()] = (int) $source->id();
        }
        $package = $budget->get('field_brebo_package_ref')->entity;
        $budget_rows[] = [
          ['data' => Link::fromTextAndUrl($budget->label(), Url::fromRoute('brebo_office_core.work_budget_dashboard', ['node' => $budget->id()]))->toRenderable()],
          $package instanceof NodeInterface ? $package->label() : '—',
          $this->value($budget, 'field_brebo_budget_status'),
          $source instanceof NodeInterface ? $source->label() : '—',
          $this->dateFormatter->format($budget->getChangedTime(), 'short'),
        ];
      }

      $project_calculation_ids = $storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'brebo_calculation')
        ->condition('field_brebo_package_ref.target_id', array_values($package_ids), 'IN')
        ->sort('changed', 'DESC')
        ->execute();
      foreach ($project_calculation_ids as $id) {
        $calculation_ids[(int) $id] = (int) $id;
      }
    }

    foreach ($storage->loadMultiple($calculation_ids) as $calculation) {
      if (!$calculation instanceof NodeInterface) {
        continue;
      }
      $package = $calculation->get('field_brebo_package_ref')->entity;
      $calculation_rows[] = [
        ['data' => Link::fromTextAndUrl($calculation->label(), Url::fromRoute('brebo_office_core.calculation_dashboard', ['node' => $calculation->id()]))->toRenderable()],
        $this->value($calculation, 'field_brebo_calc_code'),
        $package instanceof NodeInterface ? $package->label() : '—',
        $this->value($calculation, 'field_brebo_calc_status'),
        ['data' => Link::fromTextAndUrl($this->t('Open calculatiewerkbank'), Url::fromRoute('brebo_calculation.workbench', ['node' => $calculation->id()]))->toRenderable()],
      ];
    }

    return [
      'principle' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['brebo-budget-principle']],
        'title' => ['#markup' => '<h2>' . $this->t('Werkbegroting is leidend') . '</h2>'],
        'text' => ['#markup' => '<p>' . $this->t('De werkbegroting is de uitvoeringswaarheid van het project. De broncalculatie en offerte blijven beschikbaar als onveranderlijke commerciële herkomst voor herleiding, vergelijking en nacalculatie.') . '</p>'],
      ],
      'budgets' => [
        '#type' => 'table',
        '#caption' => $this->t('Werkbegroting — leidend voor uitvoering'),
        '#header' => [$this->t('Werkbegroting'), $this->t('Werkpakket'), $this->t('Status'), $this->t('Broncalculatie'), $this->t('Gewijzigd')],
        '#rows' => $budget_rows,
        '#empty' => $this->t('Voor dit project is nog geen werkbegroting vastgesteld.'),
      ],
      'sources' => [
        '#type' => 'details',
        '#title' => $this->t('Broncalculatie / offerte — referentie en herleiding'),
        '#open' => TRUE,
        'table' => [
          '#type' => 'table',
          '#header' => [$this->t('Calculatie'), $this->t('Code'), $this->t('Werkpakket'), $this->t('Status'), $this->t('Herleiding')],
          '#rows' => $calculation_rows,
          '#empty' => $this->t('Voor dit project is nog geen broncalculatie gekoppeld.'),
        ],
      ],
      '#cache' => [
        'contexts' => ['user.permissions'],
        'tags' => ['node:' . $node->id(), 'node_list:brebo_work_package', 'node_list:brebo_work_budget', 'node_list:brebo_calculation'],
      ],
    ];
  }

  private function value(NodeInterface $node, string $field): string {
    if (!$node->hasField($field) || $node->get($field)->isEmpty()) {
      return '—';
    }
    return trim((string) $node->get($field)->value) ?: '—';
  }

  private function assertProject(NodeInterface $node): void {
    if ($node->bundle() !== 'brebo_project') {
      throw new NotFoundHttpException('BREBO project does not exist.');
    }
  }

}
