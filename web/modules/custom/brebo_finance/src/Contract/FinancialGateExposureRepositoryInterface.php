<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

/** Reads blocking finding payloads used to resolve financial gate exposure. */
interface FinancialGateExposureRepositoryInterface {

  /**
   * @param list<int> $findingIds
   * @return list<array{id:int,project_nid:int,control_code:string,source_type:string,source_id:int,payload:string}>
   */
  public function findings(int $projectId, array $findingIds): array;

}
