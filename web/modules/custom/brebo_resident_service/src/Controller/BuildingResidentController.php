<?php

declare(strict_types=1);

namespace Drupal\brebo_resident_service\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class BuildingResidentController extends ControllerBase {

  public function __construct(private readonly Connection $database) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('database'));
  }

  public function title(NodeInterface $node): string {
    return $this->t('Woningen & bewoners — @building', ['@building' => $node->label()]);
  }

  public function overview(NodeInterface $node): array {
    if ($node->bundle() !== 'brebo_building') {
      throw new NotFoundHttpException();
    }
    $buildingNid = (int) $node->id();
    $query = $this->database->select('brebo_residence', 'r');
    $query->leftJoin('brebo_resident', 'p', 'p.residence_id = r.id');
    $query->fields('r', ['id', 'address_line', 'bag_nummeraanduiding_id', 'bag_verblijfsobject_id', 'access_status', 'occupancy_status', 'project_id']);
    $query->addExpression('COUNT(DISTINCT p.id)', 'resident_count');
    $query->condition('r.building_nid', $buildingNid);
    foreach (['r.id','r.address_line','r.bag_nummeraanduiding_id','r.bag_verblijfsobject_id','r.access_status','r.occupancy_status','r.project_id'] as $group) {
      $query->groupBy($group);
    }
    $query->orderBy('r.street')->orderBy('r.house_number')->orderBy('r.addition');

    $rows = [];
    $total = $accessProblems = $openCases = $vacant = 0;
    foreach ($query->execute() as $residence) {
      $total++;
      if (in_array($residence->occupancy_status, ['vacant', 'temporarily_vacant'], TRUE)) {
        $vacant++;
      }
      $accessProblem = in_array($residence->access_status, ['blocked', 'no_contact', 'refused'], TRUE);
      if ($accessProblem) {
        $accessProblems++;
      }
      $caseQuery = $this->database->select('brebo_resident_case', 'c')->condition('residence_id', (int) $residence->id)->condition('status', ['closed', 'cancelled'], 'NOT IN');
      $residenceOpenCases = (int) $caseQuery->countQuery()->execute()->fetchField();
      $openCases += $residenceOpenCases;
      $addressLink = Link::fromTextAndUrl((string) $residence->address_line, Url::fromRoute('brebo_resident_service.residence_detail', ['residence_id' => (int) $residence->id]))->toRenderable();
      $accessLabel = ucfirst(str_replace('_', ' ', (string) $residence->access_status));
      $rows[] = [
        ['data' => $addressLink],
        $residence->bag_verblijfsobject_id ?: $residence->bag_nummeraanduiding_id ?: '—',
        ucfirst(str_replace('_', ' ', (string) $residence->occupancy_status)),
        ['data' => ['#markup' => '<span class="brebo-status ' . ($accessProblem ? 'brebo-status--critical' : 'brebo-status--neutral') . '">' . htmlspecialchars($accessLabel, ENT_QUOTES, 'UTF-8') . '</span>']],
        (int) $residence->resident_count,
        $residenceOpenCases,
        $residence->project_id ?: '—',
      ];
    }

    return [
      '#type' => 'container', '#attributes' => ['class' => ['brebo-cockpit']],
      'header' => ['#markup' => '<div class="brebo-cockpit__header"><div><p class="brebo-cockpit__intro">Permanente BAG-gebruiksobjecten en bewoners/servicecontext van dit gebouw. Projecttijdelijke sturing blijft op projectniveau.</p></div></div>'],
      'kpis' => [
        '#type' => 'container', '#attributes' => ['class' => ['brebo-kpis']],
        'total' => ['#markup' => '<div class="brebo-kpi brebo-kpi--neutral"><span class="brebo-kpi__value">' . $total . '</span><span class="brebo-kpi__label">Woningen / gebruiksobjecten</span></div>'],
        'access' => ['#markup' => '<div class="brebo-kpi ' . ($accessProblems ? 'brebo-kpi--critical' : 'brebo-kpi--positive') . '"><span class="brebo-kpi__value">' . $accessProblems . '</span><span class="brebo-kpi__label">Toegangsproblemen</span></div>'],
        'cases' => ['#markup' => '<div class="brebo-kpi ' . ($openCases ? 'brebo-kpi--attention' : 'brebo-kpi--positive') . '"><span class="brebo-kpi__value">' . $openCases . '</span><span class="brebo-kpi__label">Open bewonersdossiers</span></div>'],
        'vacant' => ['#markup' => '<div class="brebo-kpi brebo-kpi--neutral"><span class="brebo-kpi__value">' . $vacant . '</span><span class="brebo-kpi__label">Leegstaand</span></div>'],
      ],
      'residences' => [
        '#type' => 'container', '#attributes' => ['class' => ['brebo-section']],
        'header' => ['#markup' => '<div class="brebo-section__header"><h2 class="brebo-section__title">Woningen & gebruiksobjecten</h2></div>'],
        'table_wrap' => ['#type' => 'container', '#attributes' => ['class' => ['brebo-table-wrap']], 'table' => [
          '#type' => 'table', '#header' => ['Adres', 'BAG-identiteit', 'Bewoning', 'Toegang', 'Bewoners', 'Open dossiers', 'Project'], '#rows' => $rows,
          '#empty' => $this->t('Nog geen woningen aan dit gebouw gekoppeld.'),
        ]],
      ],
      '#cache' => ['max-age' => 0],
    ];
  }
}
