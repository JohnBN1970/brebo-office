<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\FinancialScenarioRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseFinancialScenarioRepository implements FinancialScenarioRepositoryInterface {
  public function __construct(private readonly Connection $database) {}
  public function create(array $fields):int{return (int)$this->database->insert('brebo_finance_scenario')->fields($fields)->execute();}
  public function scenario(int $id):?array{$r=$this->database->select('brebo_finance_scenario','s')->fields('s')->condition('id',$id)->execute()->fetchAssoc();return $r===FALSE?NULL:$r;}
  public function forecast(int $id):?array{$r=$this->database->select('brebo_finance_forecast_snapshot','f')->fields('f')->condition('id',$id)->execute()->fetchAssoc();return $r===FALSE?NULL:$r;}
  public function createSnapshot(array $fields):int{return (int)$this->database->insert('brebo_finance_scenario_snapshot')->fields($fields)->execute();}
  public function updateScenario(int $id,array $fields):void{$this->database->update('brebo_finance_scenario')->fields($fields)->condition('id',$id)->execute();}
}
