<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Contract;

/** Persistence boundary for provisional project/building context. */
interface ProvisionalContextRepositoryInterface {

  /**
   * @return array{id:int,subject:string,from:string,received_at:string}|null
   */
  public function communication(int $communicationId): ?array;

  /** @param array<string,mixed> $values */
  public function createProject(array $values, string $revisionMessage): int;

  /** @param array<string,mixed> $values */
  public function createBuilding(array $values, string $revisionMessage): int;

}
