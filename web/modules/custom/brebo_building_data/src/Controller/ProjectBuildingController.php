<?php

declare(strict_types=1);

namespace Drupal\brebo_building_data\Controller;

use Drupal\brebo_building_data\Service\BuildingObjectRepository;
use Drupal\brebo_building_data\Service\ProjectBuildingRepository;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/** Read-only project-to-building scope API. */
final class ProjectBuildingController extends ControllerBase {
  public function __construct(private readonly ProjectBuildingRepository $relations,private readonly BuildingObjectRepository $objects) {}
  public static function create(ContainerInterface $container): static { return new static($container->get('brebo_building_data.project_building_repository'),$container->get('brebo_building_data.object_repository')); }
  public function list(int $project_nid): JsonResponse {
    $rows=$this->relations->buildingsForProject($project_nid);$buildings=[];
    foreach($rows as $row){$buildingNid=(int)$row['building_nid'];$buildings[]=['building_nid'=>$buildingNid,'relation_role'=>$row['relation_role'],'objects'=>$this->objects->tree($buildingNid)];}
    return new JsonResponse(['project_nid'=>$project_nid,'buildings'=>$buildings],200,['Cache-Control'=>'private, no-store']);
  }
}
