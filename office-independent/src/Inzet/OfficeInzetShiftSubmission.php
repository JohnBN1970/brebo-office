<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

use InvalidArgumentException;

/**
 * Server-side bridge from local planning form fields to Office's UTC shift
 * command. Authentication and project/person authorization remain the
 * responsibility of OfficeInzetShiftCommand; never trust client-side IDs.
 */
final class OfficeInzetShiftSubmission {
  public function __construct(private OfficeInzetShiftCommand $command) {}

  /** @param array<string,mixed> $form */
  public function createForOperator(int $operatorId, array $form): int {
    $start = $form['starts_at_local'] ?? null;
    $end = $form['ends_at_local'] ?? null;
    if (!is_string($start) || !is_string($end)) {
      throw new InvalidArgumentException('Both local shift times are required.');
    }
    $request = [
      'project_id' => $form['project_id'] ?? null,
      'person_id' => $form['person_id'] ?? null,
      'starts_at' => OfficeInzetLocalTime::toUtc($start),
      'ends_at' => OfficeInzetLocalTime::toUtc($end),
    ];
    return $this->command->createForOperator($operatorId, $request);
  }
}
