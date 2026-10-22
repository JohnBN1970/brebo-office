<?php

declare(strict_types=1);

// Structural regression checks for the isolated Office building-to-Calc handoff.
// This test intentionally does not require a live database or activate Calc.
$root = dirname(__DIR__);
$reader = file_get_contents($root . '/src/Project/OfficeCalcBuildingTruthReader.php');
$link = file_get_contents($root . '/src/Project/OfficeHousingCalculationLink.php');
$project = file_get_contents($root . '/public/office-project.php');
if ($reader === false || $link === false || $project === false) {
  fwrite(STDERR, "Required Office project files missing.\n");
  exit(1);
}
$checks = [
  'building ownership' => str_contains($reader, 'office_project_buildings'),
  'housing allocation ownership' => str_contains($reader, 'office_project_housing_types'),
  'housing count source' => str_contains($reader, "pht.dwelling_count"),
  'no duplicated quantity entry' => !str_contains($project, 'Hoeveelheid per woning invoeren'),
  'manual quantity POST denied' => str_contains($project, 'Handmatige hoeveelhedeninvoer is uitgeschakeld.'),
  'calc ownership' => str_contains($link, 'office_calc_calculations'),
  'scope project lock' => str_contains($link, 'ORDER BY id FOR UPDATE'),
  'transaction rollback' => str_contains($link, '$this->db->rollBack()'),
  'test-only link flag' => str_contains($project, 'OFFICE_CALC_HOUSING_LINK_ENABLED'),
  'grant checked' => str_contains($project, 'canLaunch($operator->operatorId, $projectId, $calcId)'),
];
foreach ($checks as $name => $ok) {
  if (!$ok) {
    fwrite(STDERR, "FAIL: $name\n");
    exit(1);
  }
}
echo "Building-to-Calc structural contract: OK\n";
