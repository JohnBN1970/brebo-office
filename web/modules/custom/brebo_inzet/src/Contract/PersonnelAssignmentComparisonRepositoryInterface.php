<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Contract;

/** Read boundary for planned-versus-actual personnel-hour comparison. */
interface PersonnelAssignmentComparisonRepositoryInterface {

  /**
   * @return array{
   *   id:int,
   *   date:string,
   *   project_id:int,
   *   user_id:int,
   *   planned_hours:float,
   *   start:string,
   *   end:string
   * }|null
   */
  public function assignment(int $assignmentId): ?array;

  /**
   * @return list<array{clock_in:string,clock_out:?string}>
   */
  public function clockSessions(int $projectId, int $userId, string $beforeUtc): array;

  public function timezoneName(): string;

}
