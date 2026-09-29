<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

interface CommitmentNumberIssuerInterface {

  /**
   * @return array<string,mixed>
   *   Numbering receipt including at least the issued number.
   */
  public function issue(int $projectNid, int $commitmentId, int $year): array;

}
