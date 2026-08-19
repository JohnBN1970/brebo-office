<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\brebo_building_data\Service\BuildingObjectRepository;
use Drupal\brebo_building_data\Service\ProjectBuildingRepository;
use Drupal\Core\Database\Connection;
use RuntimeException;

/** Stores the canonical project -> building -> object location of a performance. */
final class PerformanceLocationManager {
  public function __construct(
    private readonly Connection $database,
    private readonly ProjectBuildingRepository $projectBuildings,
    private readonly BuildingObjectRepository $objects,
  ) {}

  public function attach(int $receiptId,int $projectNid,int $buildingNid,int $objectId,int $userId):void{
    if(!$this->projectBuildings->contains($projectNid,$buildingNid))throw new RuntimeException('Selected building is not part of this project.');
    $object=$this->objects->load($objectId);if((int)$object['building_nid']!==$buildingNid)throw new RuntimeException('Selected object does not belong to the selected building.');
    $this->ensureStorage();$now=time();$existing=$this->database->select('brebo_finance_performance_location','l')->fields('l',['id'])->condition('receipt_id',$receiptId)->execute()->fetchField();
    $fields=['project_nid'=>$projectNid,'building_nid'=>$buildingNid,'object_id'=>$objectId,'changed'=>$now,'changed_by'=>$userId];
    if($existing){$this->database->update('brebo_finance_performance_location')->fields($fields)->condition('id',(int)$existing)->execute();return;}
    $this->database->insert('brebo_finance_performance_location')->fields(['receipt_id'=>$receiptId,'created'=>$now,'created_by'=>$userId]+$fields)->execute();
  }

  public function forReceipt(int $receiptId):?array{$this->ensureStorage();$row=$this->database->select('brebo_finance_performance_location','l')->fields('l')->condition('receipt_id',$receiptId)->execute()->fetchAssoc();return$row===false?null:$row;}

  private function ensureStorage():void{$s=$this->database->schema();if($s->tableExists('brebo_finance_performance_location'))return;$s->createTable('brebo_finance_performance_location',['description'=>'Canonical building object location for a financial performance receipt.','fields'=>['id'=>['type'=>'serial','unsigned'=>true,'not null'=>true],'receipt_id'=>['type'=>'int','unsigned'=>true,'not null'=>true],'project_nid'=>['type'=>'int','unsigned'=>true,'not null'=>true],'building_nid'=>['type'=>'int','unsigned'=>true,'not null'=>true],'object_id'=>['type'=>'int','unsigned'=>true,'not null'=>true],'created'=>['type'=>'int','unsigned'=>true,'not null'=>true],'created_by'=>['type'=>'int','unsigned'=>true,'not null'=>true],'changed'=>['type'=>'int','unsigned'=>true,'not null'=>true],'changed_by'=>['type'=>'int','unsigned'=>true,'not null'=>true]],'primary key'=>['id'],'unique keys'=>['receipt'=>['receipt_id']],'indexes'=>['project'=>['project_nid'],'building'=>['building_nid'],'object'=>['object_id']]]);}
}
