<?php
declare(strict_types=1);
$router = file_get_contents(dirname(__DIR__) . '/deployment/hostinger/office-test-index.php');
$form = file_get_contents(dirname(__DIR__) . '/src/Inzet/OfficeInzetPersonnelNumberForm.php');
$handler = file_get_contents(dirname(__DIR__) . '/public/office-inzet-personnel-number.php');
if (!is_string($router) || !is_string($form) || !is_string($handler)) {
  throw new RuntimeException('Personnel route chain incomplete');
}
$route = '/office-test/inzet/medewerker/personeelsnummer';
if (!str_contains($router, "'".$route."' => 'office-inzet-personnel-number.php'")) {
  throw new RuntimeException('Personnel route is not registered');
}
if (!str_contains($form, "/inzet/medewerker/personeelsnummer")) {
  throw new RuntimeException('Personnel form action mismatch');
}
if (!str_contains($handler, "OfficeInzetPersonnelProfileAction")) {
  throw new RuntimeException('Personnel route lacks authenticated action');
}
$projects = file_get_contents(dirname(__DIR__) . '/public/office-projects.php');
if (!is_string($projects)
    || !str_contains($projects, "getenv('OFFICE_TEST_INZET_PERSONNEL_WRITE_ENABLED') === 'enabled'")
    || !str_contains($projects, "getenv('OFFICE_TEST_INZET_PERSONNEL_SCHEMA_VERIFIED') === 'enabled'")
    || !str_contains($projects, 'OfficeInzetManageablePeopleRepository')
    || !str_contains($projects, 'OfficeInzetPersonnelNumberForm::render')) {
  throw new RuntimeException('Personnel editor must load authorized project team independently');
}
if (!str_contains($projects, 'Personeelsnummer opgeslagen.')
    || !str_contains($handler, 'profile_saved=1')) {
  throw new RuntimeException('Personnel save confirmation and profile redirect disconnected');
}
echo "Personnel form to test router to POST handler chain OK\n";
