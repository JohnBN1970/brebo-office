<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Project/OfficeProjectIdentity.php';
require_once dirname(__DIR__) . '/src/Project/OfficeCanonicalProjectContext.php';

use Brebo\Office\Project\OfficeCanonicalProjectContext;

$payload = ['contract' => 'brebo-office-calc-context-v1', 'project' => ['id' => 42, 'code' => 'BREBO-2026-042', 'title' => 'Testproject', 'status' => 'preparation']];
$context = OfficeCanonicalProjectContext::fromPayload($payload, 42);
if ($context->identity->projectId !== 42 || $context->title !== 'Testproject') {
  throw new RuntimeException('Canonical project context was not parsed correctly.');
}
$invalid = [];
$invalid[] = [$payload, 43];
$badContract = $payload; $badContract['contract'] = 'unknown'; $invalid[] = [$badContract, 42];
$badId = $payload; $badId['project']['id'] = '42'; $invalid[] = [$badId, 42];
$missing = $payload; unset($missing['project']['status']); $invalid[] = [$missing, 42];
foreach ($invalid as [$candidate, $requested]) {
  try {
    OfficeCanonicalProjectContext::fromPayload($candidate, $requested);
    throw new RuntimeException('Invalid project context accepted.');
  } catch (InvalidArgumentException $expected) {
  }
}
echo "Canonical project context validation passed.\n";
