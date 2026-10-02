<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\PerformanceLocationRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabasePerformanceLocationRepository implements PerformanceLocationRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function ensureStorage(): void {
    $s=$this->database->schema();
    if($s->tableExists('brebo_finance_performance_location')) return;
    $s->createTable('brebo_finance_performance_location',[
      'description'=>'Canonical building object location for a financial performance receipt.',
      'fields'=>[
        'id'=>['type'=>'serial','unsigned'=>TRUE,'not null'=>TRUE],
        'receipt_id'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],
        'project_nid'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],
        'building_nid'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],
        'object_id'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],
        'created'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],
        'created_by'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],
        'changed'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],
        'changed_by'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],
      ],
      'primary key'=>['id'],
      'unique keys'=>['receipt'=>['receipt_id']],
      'indexes'=>['project'=>['project_nid'],'building'=>['building_nid'],'object'=>['object_id']],
    ]);
  }

  public function save(int $receiptId,array $fields,int $createdAt,int $createdBy):void {
    $existing=$this->database->select('brebo_finance_performance_location','l')->fields('l',['id'])->condition('receipt_id',$receiptId)->execute()->fetchField();
    if($existing!==FALSE){
      $this->database->update('brebo_finance_performance_location')->fields($fields)->condition('id',(int)$existing)->execute();
      return;
    }
    $this->database->insert('brebo_finance_performance_location')->fields(['receipt_id'=>$receiptId,'created'=>$createdAt,'created_by'=>$createdBy]+$fields)->execute();
  }

  public function forReceipt(int $receiptId):?array {
    $row=$this->database->select('brebo_finance_performance_location','l')->fields('l')->condition('receipt_id',$receiptId)->execute()->fetchAssoc();
    return $row===FALSE?NULL:$row;
  }

}
