<?php

declare(strict_types=1);

use Brebo\Office\Calculation\OfficeCalculationPermission;
use Brebo\Office\Intake\WebsiteCrmOperatorContext;

require_once __DIR__ . '/../src/Intake/WebsiteCrmOperatorContext.php';
require_once __DIR__ . '/../src/Calculation/OfficeCalculationPermission.php';

foreach ([null, new WebsiteCrmOperatorContext('employee-1', 'session-1', true)] as $operator) {
  try {
    OfficeCalculationPermission::requireCalculator($operator, false);
    throw new RuntimeException('Calculator access granted without permission.');
  }
  catch (DomainException) {
    // Expected.
  }
}
$operator = new WebsiteCrmOperatorContext('employee-2', 'session-2', false);
if (OfficeCalculationPermission::requireCalculator($operator, true) !== $operator) {
  throw new RuntimeException('Explicit calculator permission was denied.');
}
echo "Independent calculation permission checks passed.\n";
