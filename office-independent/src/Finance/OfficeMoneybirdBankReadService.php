<?php
declare(strict_types=1);

namespace Brebo\Office\Finance;

/**
 * Read-only bank data boundary for BREBO Office.
 * The authenticated Integration API transport is injected by the caller.
 */
final class OfficeMoneybirdBankReadService {
  /** @param callable():array<string,mixed> $fetchBankSnapshot */
  public function read(callable $fetchBankSnapshot): array {
    $payload = $fetchBankSnapshot();
    if (!is_array($payload)) {
      throw new \UnexpectedValueException('Bank source returned invalid data');
    }
    return OfficeMoneybirdBankSnapshot::fromIntegrationApi($payload);
  }
}
