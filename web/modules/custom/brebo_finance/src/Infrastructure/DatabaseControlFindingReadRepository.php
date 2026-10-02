<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\ControlFindingReadRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseControlFindingReadRepository implements ControlFindingReadRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function openForProject(int $projectId): array {
    return array_values($this->database->select('brebo_finance_control_finding','f')
      ->fields('f',[
        'id','control_code','origin','severity','status','source_type','source_id',
        'title','cause','consequence','control_measure','owner_uid','due_date',
        'payload','detected','last_seen',
      ])
      ->condition('project_nid',$projectId)
      ->condition('status',['open','pending_verification'],'IN')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC));
  }

}
