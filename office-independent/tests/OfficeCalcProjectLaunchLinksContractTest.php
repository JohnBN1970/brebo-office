<?php
declare(strict_types=1);
// Current Office project page does not advertise a Calc launch URL.
// Keep it fail-closed until a separately tested, authorized link is implemented.
$root = dirname(__DIR__);
$project = file_get_contents($root . '/public/office-project.php');
$access = file_get_contents($root . '/src/Calculation/OfficeCalcLaunchAccess.php');
if (!is_string($project) || !is_string($access)) {
    throw new RuntimeException('Missing Calc launch sources');
}
if (str_contains($project, '/office-test/calc-werkbank?project_id=')
    || str_contains($project, '/office-test/calc/start')) {
    throw new RuntimeException('Project page exposes Calc launch without explicit authorization contract');
}
if (!str_contains($project, 'Koppeling geregistreerd; openen nog niet beschikbaar')) {
    throw new RuntimeException('Calc project link is no longer safely disabled');
}
foreach (['OfficeNativeProjectAccess', 'office_calc_project_access', 'ca.permission = :permission'] as $required) {
    if (!str_contains($access, $required)) {
        throw new RuntimeException('Missing Calc launch authorization check: ' . $required);
    }
}
echo "CALC_PROJECT_LAUNCH_LINKS_CONTRACT=BLOCKED_SAFE\n";
