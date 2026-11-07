<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$project = file_get_contents($root . '/public/office-project.php');
$access = file_get_contents($root . '/src/Calculation/OfficeCalcLaunchAccess.php');
if (!is_string($project) || !is_string($access)) {
    throw new RuntimeException('Missing Calc launch sources');
}
foreach ([
    'OfficeCalcLaunchAccess.php',
    'new \\Brebo\\Office\\Calculation\\OfficeCalcLaunchAccess',
    '->canLaunch((int) $operator->operatorId, (int) $projectId, (int) $calcId)',
    'unset($launchIds[$calcId])',
    'OFFICE_CALC_SAME_ORIGIN_PROXY_VERIFIED',
    'OFFICE_CALC_SESSION_GRANTS_ENABLED',
    'OFFICE_CALC_GRANT_VALIDATION_ENABLED',
    '/office-test/calc-werkbank?project_id=',
    '$calcLaunchHtml . $scopeHtml',
] as $required) {
    if (!str_contains($project, $required)) {
        throw new RuntimeException('Unsafe or missing Calc project launch guard: ' . $required);
    }
}
foreach (['OfficeNativeProjectAccess', 'office_calc_project_access', 'ca.permission = :permission'] as $required) {
    if (!str_contains($access, $required)) {
        throw new RuntimeException('Missing Calc authorization check: ' . $required);
    }
}
echo "CALC_PROJECT_LAUNCH_LINKS_CONTRACT=PASS\n";
