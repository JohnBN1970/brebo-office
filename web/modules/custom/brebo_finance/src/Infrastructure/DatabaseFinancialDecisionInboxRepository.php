<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\FinancialDecisionInboxRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseFinancialDecisionInboxRepository implements FinancialDecisionInboxRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function pending(?int $projectId, int $now): array {
    $query=$this->database->select('brebo_finance_phase_gate_exception','e')
      ->fields('e')
      ->condition('status','requested')
      ->condition('expires_at',$now,'>')
      ->orderBy('created','ASC');
    if($projectId!==NULL) $query->condition('project_nid',$projectId);
    return array_values($query->execute()->fetchAll(\PDO::FETCH_ASSOC));
  }

}
