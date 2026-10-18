<?php
declare(strict_types=1);

/**
 * Static deployment-contract gate: the isolated Office-test router must
 * expose the Inzet GET/POST paths and all directly loaded runtime classes.
 * No HTTP request or database write is performed by this test.
 */
$root = dirname(__DIR__);
$router = file_get_contents($root.'/deployment/hostinger/office-test-index.php');
$page = file_get_contents($root.'/public/office-projects.php');
$post = file_get_contents($root.'/public/office-inzet-shift-create.php');
if ($router === false || $page === false || $post === false) throw new RuntimeException('Office route files missing');
foreach ([
    "'/office-test/projecten' => 'office-projects.php'",
    "'/office-test/inzet/dienst/nieuw' => 'office-inzet-shift-create.php'",
] as $route) {
    if (!str_contains($router,$route)) throw new RuntimeException('Missing Office-test route: '.$route);
}
foreach ([
    "OfficeMount::isTest()",
    "OFFICE_TEST_INZET_SHIFT_WRITE_ENABLED",
    "OFFICE_TEST_INZET_SHIFTS_SCHEMA_VERIFIED",
    "REQUEST_METHOD",
    "HTTP_ORIGIN",
    "OfficeInzetAuthenticatedShiftAction",
] as $gate) {
    if (!str_contains($post,$gate)) throw new RuntimeException('Missing shift POST gate: '.$gate);
}
foreach ([
    "OFFICE_TEST_INZET_SHIFT_FORM_PREVIEW",
    "OFFICE_TEST_INZET_SHIFT_WRITE_ENABLED",
    "OfficeInzetSessionCsrf::token",
    "OfficeInzetWeekShiftReadRepository",
    "OfficeInzetShiftForm::renderEditable",
] as $gate) {
    if (!str_contains($page,$gate)) throw new RuntimeException('Missing Office shift UI gate: '.$gate);
}
foreach ([
    'src/Intake/OfficeMount.php',
    'src/Intake/WebsiteCrmOperatorContext.php',
    'src/Intake/WebsiteCrmOperatorSessionProviderInterface.php',
    'src/Intake/WebsiteOfficeCrmSessionRepository.php',
    'src/Intake/WebsiteOfficeCrmCookieSessionProvider.php',
    'src/Inzet/OfficeInzetDashboard.php',
    'src/Inzet/OfficeInzetPlanningWorkbench.php',
    'src/Inzet/OfficeInzetWeekPlanning.php',
    'src/Inzet/OfficeInzetWeekShiftReadRepository.php',
    'src/Inzet/OfficeInzetShiftForm.php',
    'src/Inzet/OfficeInzetManageablePeopleRepository.php',
    'src/Inzet/OfficeInzetSessionCsrf.php',
    'src/Inzet/OfficeInzetAuthenticatedShiftAction.php',
    'src/Inzet/OfficeInzetShiftPostHandler.php',
    'src/Inzet/OfficeInzetShiftSubmission.php',
    'src/Inzet/OfficeInzetLocalTime.php',
    'src/Inzet/OfficeInzetShiftCsrf.php',
    'src/Inzet/OfficeInzetShiftCommand.php',
    'src/Inzet/OfficeInzetShiftWriter.php',
    'src/Inzet/OfficeInzetShiftAssignmentControl.php',
    'public/office-home.php',
    'public/office-login.php',
    'public/office-logout.php',
    'public/office-project.php',
] as $file) {
    if (!is_file($root.'/'.$file)) throw new RuntimeException('Missing deployed dependency: '.$file);
}
echo "Office-test Inzet GET/POST router wiring and required files OK\n";
