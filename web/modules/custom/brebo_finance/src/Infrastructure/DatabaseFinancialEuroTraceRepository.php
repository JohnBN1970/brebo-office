<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\FinancialEuroTraceRepositoryInterface;
use Drupal\Core\Database\Connection;
use UnexpectedValueException;

final class DatabaseFinancialEuroTraceRepository implements FinancialEuroTraceRepositoryInterface {
  public function __construct(private readonly Connection $database) {}
  public function one(string $table,int $id):array{if(!$this->database->schema()->tableExists($table))throw new UnexpectedValueException('Financial source table does not exist.');$r=$this->database->select($table,'x')->fields('x')->condition('id',$id)->execute()->fetchAssoc();if($r===FALSE)throw new UnexpectedValueException('Financial source record does not exist.');return $r;}
  public function many(string $table,string $field,int $value):array{if(!$this->database->schema()->tableExists($table)||!$this->database->schema()->fieldExists($table,$field))return [];return array_map(static fn(object $r):array=>(array)$r,$this->database->select($table,'x')->fields('x')->condition($field,$value)->execute()->fetchAll());}
  public function manyIn(string $table,string $field,array $values):array{if($values===[]||!$this->database->schema()->tableExists($table)||!$this->database->schema()->fieldExists($table,$field))return [];return array_map(static fn(object $r):array=>(array)$r,$this->database->select($table,'x')->fields('x')->condition($field,$values,'IN')->execute()->fetchAll());}
  public function audit(int $projectNid,array $types,array $ids):array{if(!$this->database->schema()->tableExists('brebo_finance_audit')||$ids===[])return [];$q=$this->database->select('brebo_finance_audit','a')->fields('a')->condition('project_nid',$projectNid)->condition('entity_type',$types,'IN')->condition('entity_id',array_values(array_unique($ids)),'IN')->orderBy('created','ASC');return array_map(static fn(object $r):array=>(array)$r,$q->execute()->fetchAll());}
}
