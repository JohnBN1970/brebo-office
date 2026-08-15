<?php

declare(strict_types=1);

namespace Drupal\brebo_resident_service\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
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
    $query->groupBy('r.id');
    $query->groupBy('r.address_line');
    $query->groupBy('r.bag_nummeraanduiding_id');
    $query->groupBy('r.bag_verblijfsobject_id');
    $query->groupBy('r.access_status');
    $query->groupBy('r.occupancy_status');
    $query->groupBy('r.project_id');
    $query->orderBy('r.street')->orderBy('r.house_number')->orderBy('r.addition');

    $rows = [];
    $total = 0;
    $accessProblems = 0;
    $openCases = 0;
    foreach ($query->execute() as $residence) {
      $total++;
      if (in_array($residence->access_status, ['blocked', 'no_contact', 'refused'], TRUE)) {
        $accessProblems++;
      }
      $caseQuery = $this->database->select('brebo_resident_case', 'c')->condition('residence_id', (int) $residence->id)->condition('status', ['closed', 'cancelled'], 'NOT IN');
      $residenceOpenCases = (int) $caseQuery->countQuery()->execute()->fetchField();
      $openCases += $residenceOpenCases;
      $rows[] = [
        $residence->address_line,
        $residence->bag_verblijfsobject_id ?: $residence->bag_nummeraanduiding_id ?: '—',
        ucfirst(str_replace('_', ' ', (string) $residence->occupancy_status)),
        ucfirst(str_replace('_', ' ', (string) $residence->access_status)),
        (int) $residence->resident_count,
        $residenceOpenCases,
        $residence->project_id ?: '—',
      ];
    }

    return [
      'summary' => [
        '#theme' => 'item_list',
        '#items' => [
          $this->t('Woningen/gebruiksobjecten: @count', ['@count' => $total]),
          $this->t('Toegangsproblemen: @count', ['@count' => $accessProblems]),
          $this->t('Open bewonersdossiers: @count', ['@count' => $openCases]),
        ],
      ],
      'residences' => [
        '#type' => 'table',
        '#header' => ['Adres', 'BAG-identiteit', 'Bewoning', 'Toegang', 'Bewoners', 'Open dossiers', 'Project'],
        '#rows' => $rows,
        '#empty' => $this->t('Nog geen woningen aan dit gebouw gekoppeld.'),
      ],
      '#cache' => ['max-age' => 0],
    ];
  }
}
