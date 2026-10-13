<?php

declare(strict_types=1);

use Brebo\Office\Calculation\OfficeCalcLaunchAuthorization;
use Brebo\Office\Intake\WebsiteCrmOperatorContext;

require_once __DIR__ . '/../src/Intake/WebsiteCrmOperatorContext.php';
require_once __DIR__ . '/../src/Calculation/OfficeCalcLaunchAuthorization.php';

$operator = new WebsiteCrmOperatorContext('office-user', 'session-hash', false);
foreach ([[null, true, true], [7, false, true], [7, true, false]] as [$actorId, $projectAllowed, $calcMatches]) {
  $denied = false;
  try {
    OfficeCalcLaunchAuthorization::requireAuthorized($operator, $actorId, 11, 22, $projectAllowed, $calcMatches);
  } catch (DomainException) {
    $denied = true;
  }
  if (!$denied) throw new RuntimeException('Unauthorized Calc launch allowed.');
}
if (OfficeCalcLaunchAuthorization::requireAuthorized($operator, 7, 11, 22, true, true) !== 7) {
  throw new RuntimeException('Authorized Calc launch denied.');
}
echo "Calc launch authorization contract passed.\n";
