<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\brebo_building_data\Service\BuildingObjectRepository;
use Drupal\brebo_building_data\Contract\ProjectBuildingRepositoryInterface;
use Drupal\brebo_finance\Contract\PerformanceLocationRepositoryInterface;
use RuntimeException;

/** Stores the canonical project -> building -> object location of a performance. */
final class PerformanceLocationManager {
  public function __construct(
    private readonly PerformanceLocationRepositoryInterface $repository,
    private readonly ProjectBuildingRepositoryInterface $projectBuildings,
    private readonly BuildingObjectRepository $objects,
  ) {}

  public function attach(int $receiptId,int $projectNid,int $buildingNid,int $objectId,int $userId):void{
    if(!$this->projectBuildings->contains($projectNid,$buildingNid))throw new RuntimeException('Selected building is not part of this project.');
    $object=$this->objects->load($objectId);if((int)$object['building_nid']!==$buildingNid)throw new RuntimeException('Selected object does not belong to the selected building.');
    $this->repository->ensureStorage();$now=time();
    $fields=['project_nid'=>$projectNid,'building_nid'=>$buildingNid,'object_id'=>$objectId,'changed'=>$now,'changed_by'=>$userId];
    $this->repository->save($receiptId,$fields,$now,$userId);
  }

  public function forReceipt(int $receiptId):?array{$this->repository->ensureStorage();return $this->repository->forReceipt($receiptId);}
}
