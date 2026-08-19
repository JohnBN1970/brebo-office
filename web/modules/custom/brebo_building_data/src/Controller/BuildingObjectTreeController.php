<?php

declare(strict_types=1);

namespace Drupal\brebo_building_data\Controller;

use Drupal\brebo_building_data\Service\BuildingObjectRepository;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/** Read-only API for the canonical BREBO building object hierarchy. */
final class BuildingObjectTreeController extends ControllerBase {
  public function __construct(private readonly BuildingObjectRepository $objects) {}
  public static function create(ContainerInterface $container): static { return new static($container->get('brebo_building_data.object_repository')); }
  public function tree(int $building_nid): JsonResponse {
    return new JsonResponse(['building_nid'=>$building_nid,'objects'=>$this->objects->tree($building_nid)],200,['Cache-Control'=>'private, no-store']);
  }
}
