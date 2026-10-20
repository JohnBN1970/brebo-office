<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$view = file_get_contents($root . '/public/office-projects.php');
$route = file_get_contents($root . '/public/office-onsite-invitation.php');
$router = file_get_contents($root . '/deployment/hostinger/office-test-index.php');
$revoke = file_get_contents($root . '/public/office-onsite-device-revoke.php');
$revocationAction = file_get_contents($root . '/src/Inzet/OnSiteOfficeDeviceRevocationAction.php');
$deviceInventory = file_get_contents($root . '/src/Inzet/OnSiteOfficeDeviceManagementRepository.php');
$csrf = file_get_contents($root . '/src/Inzet/OnSiteOfficeInvitationCsrf.php');
$action = file_get_contents($root . '/src/Inzet/OnSiteOfficeInvitationAction.php');
foreach ([
  'test-only feature flag' => str_contains($view, 'OFFICE_TEST_ONSITE_INVITATION_ISSUE_ENABLED')
    && str_contains($route, 'OFFICE_TEST_ONSITE_INVITATION_ISSUE_ENABLED'),
  'manager-only project team' => str_contains($view, 'OfficeInzetManageablePeopleRepository')
    && str_contains($action, "m.permission = 'manage'"),
  'session-bound CSRF' => str_contains($route, 'OnSiteOfficeInvitationCsrf::verify')
    && str_contains($view, 'OnSiteOfficeInvitationCsrf::token'),
  'explicit confirmation' => str_contains($route, "'ISSUE'") && str_contains($view, 'name="confirmation"'),
  'same-origin enforcement' => str_contains($route, 'HTTP_ORIGIN') && str_contains($route, 'hash_equals'),
  'verified Office session' => str_contains($route, 'currentOperator()'),
  'test-only router' => str_contains($router, "'/office-test/inzet/onsite/activatie-uitgeven'"),
  'revocation test-only route' => str_contains($router, "'/office-test/inzet/onsite/apparaat-intrekken'")
    && str_contains($revoke, 'OFFICE_TEST_ONSITE_DEVICE_REVOKE_ENABLED'),
  'revocation session and CSRF' => str_contains($revoke, 'currentOperator()')
    && str_contains($revoke, 'OnSiteOfficeInvitationCsrf::verify'),
  'revocation explicit confirmation' => str_contains($revoke, "'REVOKE'")
    && str_contains($view, 'Apparaat intrekken'),
  'revocation scoped to one credential' => str_contains($revocationAction, 'WHERE token_hash = ? AND person_id = ?')
    && str_contains($revocationAction, "m.permission = 'manage'"),
  'device inventory scoped to managers' => str_contains($deviceInventory, "m.permission = 'manage'")
    && str_contains($deviceInventory, 'listDeviceCredentialsForManager'),
  'separate CSRF purpose' => str_contains($csrf, 'onsite-office-invitation:v1:'),
  'one-time secret not logged' => !str_contains($route, 'error_log($invitation'),
] as $label => $valid) {
  if (!$valid) throw new RuntimeException('OnSite invitation UI contract failed: ' . $label);
}
echo "OnSite Office invitation UI static contract passed\n";
