<?php
declare(strict_types=1);

// A linked calculation alone must never cause a visible launch link.
$root = dirname(__DIR__);
$project = file_get_contents($root . '/public/office-project.php');
$access = file_get_contents($root . '/src/Calculation/OfficeCalcLaunchAccess.php');
if (!is_string($project) || !is_string($access)) {
    throw new RuntimeException('Missing Calc launch sources');
}
foreach ([
    "OfficeCalcLaunchAccess.php",
    "new \\Brebo\\Office\\Calculation\\OfficeCalcLaunchAccess",
    "->canLaunch((int)\$operator->operatorId, (int)\$projectId, (int)\$calcId)",
    "unset(\$launchIds[\$calcId])",
    "OFFICE_CALC_SAME_ORIGIN_PROXY_VERIFIED",
    "/office-test/calc-werkbank?project_id=",
] as $required) {
    if (!str_contains($project, $required)) {
        throw new RuntimeException('Unsafe or missing Calc project launch guard: ' . $required);
    }
}
if (!str_contains($access, 'OfficeNativeProjectAccess')
    || !str_contains($access, 'office_calc_project_access')
    || !str_contains($access, "ca.permission = :permission")) {
    throw new RuntimeException('Missing project and calculation authorization checks');
}
echo "CALC_PROJECT_LAUNCH_LINKS_CONTRACT=PASS\n";
