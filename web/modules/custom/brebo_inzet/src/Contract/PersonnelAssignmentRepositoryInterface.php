<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Contract;

interface PersonnelAssignmentRepositoryInterface {

  /**
   * @return array{
   *   id:int,revision_id:string,project_id:int,user_id:int,budget_line_id:int,
   *   planned_hours:float,actual_hours:float,actual_status:string,
   *   assignment_status:string,plan_date:string,start:string,end:string,
   *   hourly_cost:float,changed:int
   * }|null
   */
  public function assignment(int $assignmentId): ?array;

  public function setBudgetLine(int $assignmentId, int $budgetLineId): void;

  public function storeActualReview(
    int $assignmentId,
    float $hours,
    string $status,
    int $reviewedBy,
    string $reviewedAt,
  ): void;

}
