<?php

declare(strict_types=1);

namespace Drupal\brebo_building_data\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use InvalidArgumentException;

/** Canonical many-to-many relation between BREBO projects and buildings. */
final class ProjectBuildingRepository {
  public function __construct(private readonly Connection $database,private readonly EntityTypeManagerInterface $entityTypeManager,private readonly TimeInterface $time) {}

  public function attach(int $projectNid,int $buildingNid,string $role='scope'):void{
    $this->assertNode($projectNid,'brebo_project');$this->assertNode($buildingNid,'brebo_building');$role=trim($role)?:'scope';$this->ensureStorage();
    $existing=$this->database->select('brebo_project_building','r')->fields('r',['id'])->condition('project_nid',$projectNid)->condition('building_nid',$buildingNid)->execute()->fetchField();$now=$this->time->getRequestTime();
    if($existing){$this->database->update('brebo_project_building')->fields(['relation_role'=>$role,'changed'=>$now])->condition('id',(int)$existing)->execute();return;}
    $this->database->insert('brebo_project_building')->fields(['project_nid'=>$projectNid,'building_nid'=>$buildingNid,'relation_role'=>$role,'created'=>$now,'changed'=>$now])->execute();
  }

  public function buildingsForProject(int $projectNid):array{$this->assertNode($projectNid,'brebo_project');$this->ensureStorage();return $this->database->select('brebo_project_building','r')->fields('r')->condition('project_nid',$projectNid)->orderBy('building_nid')->execute()->fetchAll(\PDO::FETCH_ASSOC);}
  public function projectsForBuilding(int $buildingNid):array{$this->assertNode($buildingNid,'brebo_building');$this->ensureStorage();return $this->database->select('brebo_project_building','r')->fields('r')->condition('building_nid',$buildingNid)->orderBy('project_nid')->execute()->fetchAll(\PDO::FETCH_ASSOC);}
  public function contains(int $projectNid,int $buildingNid):bool{$this->ensureStorage();return(bool)$this->database->select('brebo_project_building','r')->condition('project_nid',$projectNid)->condition('building_nid',$buildingNid)->countQuery()->execute()->fetchField();}

  private function assertNode(int $nid,string $bundle):void{$node=$this->entityTypeManager->getStorage('node')->load($nid);if(!$node instanceof NodeInterface||$node->bundle()!==$bundle)throw new InvalidArgumentException(sprintf('Node %d is not %s.',$nid,$bundle));}
  private function ensureStorage():void{$s=$this->database->schema();if($s->tableExists('brebo_project_building'))return;$s->createTable('brebo_project_building',['description'=>'Canonical project to building relations for BREBO Office.','fields'=>['id'=>['type'=>'serial','unsigned'=>TRUE,'not null'=>TRUE],'project_nid'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],'building_nid'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],'relation_role'=>['type'=>'varchar','length'=>32,'not null'=>TRUE,'default'=>'scope'],'created'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],'changed'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE]],'primary key'=>['id'],'unique keys'=>['project_building'=>['project_nid','building_nid']],'indexes'=>['project_nid'=>['project_nid'],'building_nid'=>['building_nid'],'relation_role'=>['relation_role']]]);}
}
