<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\FailureCostRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseFailureCostRepository implements FailureCostRepositoryInterface {
  public function __construct(private readonly Connection $database) {}
  public function create(array $fields):int{return (int)$this->database->insert('brebo_finance_failure_cost')->fields($fields)->execute();}
  public function load(int $id):?array{$r=$this->database->select('brebo_finance_failure_cost','f')->fields('f')->condition('id',$id)->execute()->fetchAssoc();return $r===FALSE?NULL:$r;}
  public function update(int $id,array $fields):void{$this->database->update('brebo_finance_failure_cost')->fields($fields)->condition('id',$id)->execute();}
  public function audit(array $fields):void{$this->database->insert('brebo_finance_audit')->fields($fields)->execute();}
}
