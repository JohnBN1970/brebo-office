<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

require_once __DIR__ . '/../src/Intake/WebsiteCrmOperatorContext.php';
require_once __DIR__ . '/../src/Intake/WebsiteOfficeCalculationAccessPolicy.php';

// Contract smoke test: no production DB connection and no migration execution.
// This tests fail-closed behavior at the policy boundary, not SQL integration.
final class FakeCalculationStatement extends \PDOStatement {
  public array $params = [];
  public function __construct(private readonly bool $allowed) {}
  public function execute(?array $params = null): bool {
    $this->params = $params ?? [];
    return true;
  }
  public function fetchColumn(int $column = 0): mixed {
    return $this->allowed ? 1 : false;
  }
}

final class FakeCalculationPdo extends \PDO {
  public ?FakeCalculationStatement $last = null;
  public function __construct(private readonly bool $allowed) {}
  public function prepare(string $query, array $options = []): \PDOStatement|false {
    $this->last = new FakeCalculationStatement($this->allowed);
    return $this->last;
  }
}

function check(bool $ok, string $message): void {
  if (!$ok) {
    throw new \RuntimeException($message);
  }
}

$operator = new WebsiteCrmOperatorContext('operator-a', 'session-a', false);
foreach ([0, -1] as $invalidId) {
  try {
    (new WebsiteOfficeCalculationAccessPolicy(new FakeCalculationPdo(true)))
      ->requireCalculationAccess($operator, $invalidId);
    throw new \RuntimeException('Invalid calculation ID was allowed.');
  } catch (\DomainException $expected) {
    check($expected->getMessage() === 'Valid calculation required.', 'Unexpected invalid-ID rejection.');
  }
}
$denied = new FakeCalculationPdo(false);
try {
  (new WebsiteOfficeCalculationAccessPolicy($denied))->requireCalculationAccess($operator, 42);
  throw new \RuntimeException('Missing authorization was allowed.');
} catch (\DomainException $expected) {
  check($expected->getMessage() === 'Calculation access denied.', 'Unexpected denial reason.');
}
$allowed = new FakeCalculationPdo(true);
(new WebsiteOfficeCalculationAccessPolicy($allowed))->requireCalculationAccess($operator, 42);
check($allowed->last?->params === [':operator_id' => 'operator-a', ':calculation_id' => 42], 'Identity or calculation parameters incorrect.');
echo "Calc authorization policy boundary: OK\n";
