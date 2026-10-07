<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Contract;

/** Resolves scalar node context needed by administration-aware services. */
interface AdministrationNodeContextSourceInterface {

  /**
   * @return array{bundle:string,project_id:int|null}|null
   */
  public function context(int $nodeId): ?array;

}
