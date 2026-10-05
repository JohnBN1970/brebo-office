<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Contract;

/** Persistence boundary for normalized clock registrations and session reads. */
interface ClockRegistrationRepositoryInterface {

  /** @param array<string,mixed> $fields */
  public function create(array $fields): int;

  /**
   * @return array{id:int,project_id:int,project_label:string,user_id:int,clock_in:string,status:string}|null
   */
  public function openForUser(int $userId): ?array;

  /**
   * @return array{id:int,project_id:int,user_id:int,clock_in:string,clock_out:?string,message_json:string}|null
   */
  public function registration(int $registrationId): ?array;

  /**
   * @return array{0:string,1:string}|null
   */
  public function plannedTimes(int $projectId, int $userId, string $date): ?array;

  /**
   * @return array{id:int,project_id:int,user_id:int,clock_in:string,clock_out:?string,message_json:string}|null
   */
  public function latestEarlyClockOut(
    int $userId,
    string $windowStart,
    string $windowEnd,
  ): ?array;

  /** @param array<string,mixed> $fields */
  public function update(int $registrationId, array $fields): void;

}
