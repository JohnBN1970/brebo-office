<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$page = file_get_contents($root . '/public/office-personnel.php');
$issue = file_get_contents($root . '/public/office-onsite-invitation.php');
$revoke = file_get_contents($root . '/public/office-onsite-device-revoke.php');
foreach ([
  'employee-scoped section' => str_contains($page, 'id="medewerker-onsite"'),
  'native inventory' => str_contains($page, 'listDeviceCredentialsForManager'),
  'employee filtering' => str_contains($page, "(int)\$row['person_id'] === \$selected"),
  'activation feature gate' => str_contains($page, 'OFFICE_TEST_ONSITE_INVITATION_ISSUE_ENABLED'),
  'revocation feature gate' => str_contains($page, 'OFFICE_TEST_ONSITE_DEVICE_REVOKE_ENABLED'),
  'session CSRF' => str_contains($page, 'OnSiteOfficeInvitationCsrf::token'),
  'activation route' => str_contains($page, '/office-test/inzet/onsite/activatie-uitgeven'),
  'revocation route' => str_contains($page, '/office-test/inzet/onsite/apparaat-intrekken'),
  'issue server validation' => str_contains($issue, 'OnSiteOfficeInvitationCsrf::verify'),
  'revoke server validation' => str_contains($revoke, 'OnSiteOfficeInvitationCsrf::verify'),
] as $label => $valid) {
  if (!$valid) throw new RuntimeException('Personnel OnSite UI contract failed: '.$label);
}
echo "Personnel OnSite UI contract passed\n";
